<?php

declare(strict_types=1);

namespace App\Tests\Integration\Modules\Booking\Infrastructure\Http;

use App\Modules\Booking\Application\BookingReferentialValidator;
use App\Modules\Booking\Application\CreateBooking\CreateBookingCommand;
use App\Modules\Booking\Application\CreateBooking\CreateBookingHandler;
use App\Modules\Booking\Domain\Entity\BookingFolder;
use App\Modules\Booking\Domain\Repository\BookingFolderRepositoryInterface;
use App\Modules\Booking\Domain\Repository\BookingRepositoryInterface;
use App\Modules\Core\Domain\Entity\CoreCredential;
use App\Modules\Core\Domain\Repository\CoreCredentialRepositoryInterface;
use App\Modules\Core\Domain\Security\PasswordHasherInterface;
use App\Modules\Party\Domain\Entity\PartyAccount;
use App\Modules\Party\Domain\Repository\PartyAccountRepositoryInterface;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Infrastructure\Logging\RequestIdSubscriber;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

final class AddBookingTravelerControllerTest extends WebTestCase
{
    public function test_add_traveler_returns_201_with_business_fields_without_internal_id(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);
        $booking = $this->seedBooking('AddTravOk');

        $client->request(
            'POST',
            '/api/v1/bookings/'.$booking['publicId'].'/travelers',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
            content: json_encode([
                'firstName' => 'Amira',
                'lastName' => 'Ben Ali',
                'civility' => 'Ms',
                'email' => 'amira@example.com',
                'isPaxLeader' => true,
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(201);
        self::assertTrue($client->getResponse()->headers->has(RequestIdSubscriber::HEADER_NAME));
        self::assertFalse($client->getResponse()->headers->has('Location'));

        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertArrayNotHasKey('id', $payload);
        self::assertArrayNotHasKey('bookingId', $payload);
        self::assertSame('Amira', $payload['firstName']);
        self::assertSame('Ben Ali', $payload['lastName']);
        self::assertSame('Ms', $payload['civility']);
        self::assertSame('amira@example.com', $payload['email']);
        self::assertTrue($payload['isPaxLeader']);
        self::assertNull($payload['phone']);
        self::assertNull($payload['age']);
        self::assertNull($payload['birthDate']);
    }

    public function test_add_traveler_on_missing_booking_returns_404(): void
    {
        $missingPublicId = '00000000-0000-4000-8000-000000000066';

        $client = static::createClient();
        $token = $this->authenticate($client);

        $client->request(
            'POST',
            '/api/v1/bookings/'.$missingPublicId.'/travelers',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_ACCEPT_LANGUAGE' => 'fr',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
            content: json_encode([
                'firstName' => 'Amira',
                'lastName' => 'Ben Ali',
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(404);

        /** @var array{error: array{code: string, message: string, context: array<string, mixed>}} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('booking.not_found', $payload['error']['code']);
        self::assertSame($missingPublicId, $payload['error']['context']['public_id']);
    }

    public function test_second_pax_leader_returns_409_conflict(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);
        $booking = $this->seedBooking('AddTravPax');

        $bodyLeader = json_encode([
            'firstName' => 'Leader',
            'lastName' => 'One',
            'isPaxLeader' => true,
        ], JSON_THROW_ON_ERROR);

        $client->request(
            'POST',
            '/api/v1/bookings/'.$booking['publicId'].'/travelers',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
            content: $bodyLeader,
        );
        self::assertResponseStatusCodeSame(201);

        $client->request(
            'POST',
            '/api/v1/bookings/'.$booking['publicId'].'/travelers',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_ACCEPT_LANGUAGE' => 'en',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
            content: json_encode([
                'firstName' => 'Leader',
                'lastName' => 'Two',
                'isPaxLeader' => true,
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(409);

        /** @var array{error: array{code: string, message: string}} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('booking_traveler.pax_leader_already_set', $payload['error']['code']);
        self::assertSame('A pax leader traveler is already set for this booking.', $payload['error']['message']);
    }

    public function test_missing_first_name_returns_422(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);
        $booking = $this->seedBooking('AddTrav422');

        $client->request(
            'POST',
            '/api/v1/bookings/'.$booking['publicId'].'/travelers',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
            content: json_encode([
                'lastName' => 'OnlyLast',
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(422);
    }

    public function test_add_traveler_without_jwt_returns_401(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/v1/bookings/00000000-0000-4000-8000-000000000055/travelers',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode([
                'firstName' => 'Amira',
                'lastName' => 'Ben Ali',
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(401);
    }

    private function authenticate(KernelBrowser $client): string
    {
        $container = static::getContainer();
        /** @var UnitOfWork $unitOfWork */
        $unitOfWork = $container->get(UnitOfWork::class);
        $suffix = bin2hex(random_bytes(4));
        $email = 'http.booking.trav.'.$suffix.'@example.com';
        $password = 'Http-Booking-Trav-'.$suffix;

        /** @var PartyAccountRepositoryInterface $accounts */
        $accounts = $container->get(PartyAccountRepositoryInterface::class);
        /** @var CoreCredentialRepositoryInterface $credentials */
        $credentials = $container->get(CoreCredentialRepositoryInterface::class);
        /** @var PasswordHasherInterface $hasher */
        $hasher = $container->get(PasswordHasherInterface::class);

        $account = PartyAccount::createPerson('Http Booking Trav '.$suffix, Email::fromString($email));
        $accounts->save($account);
        $unitOfWork->commit();

        $credentials->save(CoreCredential::createLocal(
            accountId: (int) $account->id(),
            passwordHash: $hasher->hash($password),
            isPrimary: true,
        ));
        $unitOfWork->commit();

        $client->request(
            'POST',
            '/api/v1/auth/login',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode(
                ['email' => $email, 'password' => $password],
                JSON_THROW_ON_ERROR,
            ),
        );
        self::assertResponseStatusCodeSame(200);

        /** @var array{token: string} $loginBody */
        $loginBody = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        return $loginBody['token'];
    }

    /**
     * @return array{publicId: string}
     */
    private function seedBooking(string $label): array
    {
        $container = static::getContainer();
        /** @var UnitOfWork $unitOfWork */
        $unitOfWork = $container->get(UnitOfWork::class);
        $suffix = bin2hex(random_bytes(4));

        /** @var PartyAccountRepositoryInterface $accounts */
        $accounts = $container->get(PartyAccountRepositoryInterface::class);
        /** @var BookingFolderRepositoryInterface $folders */
        $folders = $container->get(BookingFolderRepositoryInterface::class);
        /** @var BookingRepositoryInterface $bookings */
        $bookings = $container->get(BookingRepositoryInterface::class);
        /** @var Connection $connection */
        $connection = $container->get(Connection::class);

        $customer = PartyAccount::createOrganization(
            'BkTrav Cust '.$label.' '.$suffix,
            Email::fromString('bktrav.cust.'.$suffix.'@example.com'),
        );
        $office = PartyAccount::createOrganization(
            'BkTrav Off '.$label.' '.$suffix,
            Email::fromString('bktrav.off.'.$suffix.'@example.com'),
        );
        $accounts->save($customer);
        $unitOfWork->commit();
        $accounts->save($office);
        $unitOfWork->commit();

        $folder = BookingFolder::create(
            'BKTRAV-'.$suffix,
            (int) $customer->id(),
            (int) $office->id(),
        );
        $folders->save($folder);
        $unitOfWork->commit();

        $booking = (new CreateBookingHandler($bookings, new BookingReferentialValidator($connection), $unitOfWork))(
            new CreateBookingCommand(
                folderId: (int) $folder->id(),
                serviceTypeCode: 'hotel',
                statusCode: 'draft',
                customerAccountId: (int) $customer->id(),
                supplierAccountId: null,
                officeAccountId: (int) $office->id(),
                startDate: '2026-09-01',
                endDate: '2026-09-03',
                achatCurrencyCode: 'TND',
                venteCurrencyCode: 'TND',
                achatExchangeRate: '1',
                venteExchangeRate: '1',
                totalAchatAmount: 10_000,
                totalVenteAmount: 12_000,
                margeAgenceAmount: 1_500,
                margeDistributeurAmount: 500,
                paidAmount: 0,
            ),
        );

        return ['publicId' => $booking->publicId()->toString()];
    }
}
