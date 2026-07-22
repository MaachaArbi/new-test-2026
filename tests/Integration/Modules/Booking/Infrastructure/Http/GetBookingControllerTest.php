<?php

declare(strict_types=1);

namespace App\Tests\Integration\Modules\Booking\Infrastructure\Http;

use App\Modules\Booking\Application\CreateBooking\CreateBookingCommand;
use App\Modules\Booking\Application\BookingReferentialValidator;
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
use Doctrine\DBAL\Connection;
use App\Shared\Infrastructure\Logging\RequestIdSubscriber;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

final class GetBookingControllerTest extends WebTestCase
{
    public function test_get_existing_booking_returns_200_without_internal_id(): void
    {
        $client = static::createClient();
        $auth = $this->createAuthenticatedSession($client);
        $seeded = $this->seedBooking();

        $client->request(
            'GET',
            '/api/v1/bookings/'.$seeded['publicId'],
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$auth['token'],
            ],
        );

        self::assertResponseStatusCodeSame(200);
        self::assertTrue($client->getResponse()->headers->has(RequestIdSubscriber::HEADER_NAME));

        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertArrayNotHasKey('id', $payload);
        self::assertSame($seeded['publicId'], $payload['publicId']);
        self::assertSame($seeded['bookingDate'], $payload['bookingDate']);
        self::assertSame($seeded['folderId'], $payload['folderId']);
        self::assertSame(
            [
                'serviceTypeCode' => 'hotel',
                'statusCode' => 'draft',
                'channelCode' => 'backoffice',
            ],
            $payload['status'],
        );
        self::assertSame($seeded['customerId'], $payload['customerAccountId']);
        self::assertNull($payload['supplierAccountId']);
        self::assertSame($seeded['officeId'], $payload['officeAccountId']);
        self::assertSame('2026-09-01', $payload['startDate']);
        self::assertSame('2026-09-03', $payload['endDate']);

        self::assertIsArray($payload['montants']);
        /** @var array<string, mixed> $montants */
        $montants = $payload['montants'];
        self::assertSame('TND', $montants['achatCurrencyCode']);
        self::assertSame('TND', $montants['venteCurrencyCode']);
        self::assertSame(
            ['amount' => 10_000, 'currencyCode' => 'TND'],
            $montants['totalAchatAmount'],
        );
        self::assertSame(
            ['amount' => 12_000, 'currencyCode' => 'TND'],
            $montants['totalVenteAmount'],
        );
        self::assertSame(
            ['amount' => 1_500, 'currencyCode' => 'TND'],
            $montants['margeAgenceAmount'],
        );
        self::assertSame(
            ['amount' => 500, 'currencyCode' => 'TND'],
            $montants['margeDistributeurAmount'],
        );
        self::assertSame(
            ['amount' => 0, 'currencyCode' => 'TND'],
            $montants['paidAmount'],
        );
        self::assertSame('unpaid', $montants['paymentStatus']);

        self::assertSame(
            [
                'isOnRequest' => false,
                'assignedAgentAccountId' => null,
                'isLocked' => false,
                'isDisputed' => false,
                'supplierStatusLabel' => null,
            ],
            $payload['workflow'],
        );
    }

    public function test_get_missing_booking_returns_404(): void
    {
        $missingPublicId = '00000000-0000-4000-8000-000000000088';

        $client = static::createClient();
        $auth = $this->createAuthenticatedSession($client);

        $client->request(
            'GET',
            '/api/v1/bookings/'.$missingPublicId,
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_ACCEPT_LANGUAGE' => 'fr',
                'HTTP_AUTHORIZATION' => 'Bearer '.$auth['token'],
            ],
        );

        self::assertResponseStatusCodeSame(404);
        self::assertTrue($client->getResponse()->headers->has(RequestIdSubscriber::HEADER_NAME));

        /** @var array{error: array{code: string, message: string, context: array<string, mixed>}} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('booking.not_found', $payload['error']['code']);
        self::assertSame('Réservation introuvable.', $payload['error']['message']);
        self::assertSame($missingPublicId, $payload['error']['context']['public_id']);
    }

    public function test_get_without_jwt_returns_401(): void
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/api/v1/bookings/00000000-0000-4000-8000-000000000077',
            server: [
                'HTTP_ACCEPT' => 'application/json',
            ],
        );

        self::assertResponseStatusCodeSame(401);
    }

    /**
     * @return array{token: string}
     */
    private function createAuthenticatedSession(KernelBrowser $client): array
    {
        $container = static::getContainer();
        /** @var UnitOfWork $unitOfWork */
        $unitOfWork = $container->get(UnitOfWork::class);
        $suffix = bin2hex(random_bytes(4));
        $email = 'http.booking.get.'.$suffix.'@example.com';
        $password = 'Http-Booking-Get-'.$suffix;

        /** @var PartyAccountRepositoryInterface $accounts */
        $accounts = $container->get(PartyAccountRepositoryInterface::class);
        /** @var CoreCredentialRepositoryInterface $credentials */
        $credentials = $container->get(CoreCredentialRepositoryInterface::class);
        /** @var PasswordHasherInterface $hasher */
        $hasher = $container->get(PasswordHasherInterface::class);

        $account = PartyAccount::createPerson('Http Booking Get '.$suffix, Email::fromString($email));
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

        return ['token' => $loginBody['token']];
    }

    /**
     * @return array{
     *     publicId: string,
     *     bookingDate: string,
     *     folderId: int,
     *     customerId: int,
     *     officeId: int
     * }
     */
    private function seedBooking(): array
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
            'BkGet Cust '.$suffix,
            Email::fromString('bkget.cust.'.$suffix.'@example.com'),
        );
        $office = PartyAccount::createOrganization(
            'BkGet Off '.$suffix,
            Email::fromString('bkget.off.'.$suffix.'@example.com'),
        );
        $accounts->save($customer);
        $unitOfWork->commit();
        $accounts->save($office);
        $unitOfWork->commit();

        $folder = BookingFolder::create(
            'BKGET-'.$suffix,
            (int) $customer->id(),
            (int) $office->id(),
        );
        $folders->save($folder);
        $unitOfWork->commit();

        $booking = (new CreateBookingHandler($bookings, new BookingReferentialValidator($connection), $unitOfWork))(new CreateBookingCommand(
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
        ));

        return [
            'publicId' => $booking->publicId()->toString(),
            'bookingDate' => $booking->bookingDate()->format('Y-m-d'),
            'folderId' => (int) $folder->id(),
            'customerId' => (int) $customer->id(),
            'officeId' => (int) $office->id(),
        ];
    }
}
