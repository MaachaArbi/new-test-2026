<?php

declare(strict_types=1);

namespace App\Tests\Integration\Modules\Booking\Infrastructure\Http;

use App\Modules\Booking\Application\AddBookingHotelRoom\AddBookingHotelRoomCommand;
use App\Modules\Booking\Application\AddBookingHotelRoom\AddBookingHotelRoomHandler;
use App\Modules\Booking\Application\AssertBookingServiceType;
use App\Modules\Booking\Application\BookingReferentialValidator;
use App\Modules\Booking\Application\CreateBooking\CreateBookingCommand;
use App\Modules\Booking\Application\CreateBooking\CreateBookingHandler;
use App\Modules\Booking\Domain\Entity\BookingFolder;
use App\Modules\Booking\Domain\Repository\BookingFolderRepositoryInterface;
use App\Modules\Booking\Domain\Repository\BookingHotelRoomRepositoryInterface;
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

final class CreateBookingCancellationPolicyControllerTest extends WebTestCase
{
    public function test_create_policy_returns_201_with_functional_id(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);
        $booking = $this->seedBooking('hotel', 'PolOk');

        $client->request(
            'POST',
            '/api/v1/bookings/'.$booking['publicId'].'/cancellation-policy',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
            content: '{}',
        );

        self::assertResponseStatusCodeSame(201);
        self::assertTrue($client->getResponse()->headers->has(RequestIdSubscriber::HEADER_NAME));
        self::assertFalse($client->getResponse()->headers->has('Location'));

        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('id', $payload);
        self::assertIsInt($payload['id']);
        self::assertGreaterThan(0, $payload['id']);
        self::assertNull($payload['roomId']);
        self::assertArrayNotHasKey('bookingId', $payload);
        self::assertCount(2, $payload);
    }

    public function test_create_policy_on_missing_booking_returns_404(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);

        $client->request(
            'POST',
            '/api/v1/bookings/00000000-0000-4000-8000-000000000011/cancellation-policy',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
            content: '{}',
        );

        self::assertResponseStatusCodeSame(404);
    }

    public function test_second_whole_booking_policy_returns_409(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);
        $booking = $this->seedBooking('hotel', 'PolDup');

        $client->request(
            'POST',
            '/api/v1/bookings/'.$booking['publicId'].'/cancellation-policy',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
            content: '{}',
        );
        self::assertResponseStatusCodeSame(201);

        $client->request(
            'POST',
            '/api/v1/bookings/'.$booking['publicId'].'/cancellation-policy',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_ACCEPT_LANGUAGE' => 'en',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
            content: '{}',
        );

        self::assertResponseStatusCodeSame(409);
        /** @var array{error: array{code: string}} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('booking_cancellation_policy.already_exists', $payload['error']['code']);
    }

    public function test_room_mismatch_returns_422(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);
        $bookingA = $this->seedBooking('hotel', 'PolRmA');
        $bookingB = $this->seedBooking('hotel', 'PolRmB');
        $roomOnB = $this->addHotelRoom($bookingB['bookingId']);

        $client->request(
            'POST',
            '/api/v1/bookings/'.$bookingA['publicId'].'/cancellation-policy',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_ACCEPT_LANGUAGE' => 'en',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
            content: json_encode(['roomId' => $roomOnB], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(422);
        /** @var array{error: array{code: string}} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('booking_cancellation_policy.room_mismatch', $payload['error']['code']);
    }

    public function test_malformed_room_id_returns_422(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);
        $booking = $this->seedBooking('hotel', 'Pol422');

        $client->request(
            'POST',
            '/api/v1/bookings/'.$booking['publicId'].'/cancellation-policy',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
            content: json_encode(['roomId' => 'abc'], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(422);
    }

    public function test_create_policy_without_jwt_returns_401(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/v1/bookings/00000000-0000-4000-8000-000000000010/cancellation-policy',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: '{}',
        );

        self::assertResponseStatusCodeSame(401);
    }

    private function authenticate(KernelBrowser $client): string
    {
        $container = static::getContainer();
        /** @var UnitOfWork $unitOfWork */
        $unitOfWork = $container->get(UnitOfWork::class);
        $suffix = bin2hex(random_bytes(4));
        $email = 'http.bk.pol.'.$suffix.'@example.com';
        $password = 'Http-Bk-Pol-'.$suffix;

        /** @var PartyAccountRepositoryInterface $accounts */
        $accounts = $container->get(PartyAccountRepositoryInterface::class);
        /** @var CoreCredentialRepositoryInterface $credentials */
        $credentials = $container->get(CoreCredentialRepositoryInterface::class);
        /** @var PasswordHasherInterface $hasher */
        $hasher = $container->get(PasswordHasherInterface::class);

        $account = PartyAccount::createPerson('Http Bk Pol '.$suffix, Email::fromString($email));
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
            content: json_encode(['email' => $email, 'password' => $password], JSON_THROW_ON_ERROR),
        );
        self::assertResponseStatusCodeSame(200);

        /** @var array{token: string} $body */
        $body = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        return $body['token'];
    }

    /**
     * @return array{publicId: string, bookingId: int}
     */
    private function seedBooking(string $serviceTypeCode, string $label): array
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
            'Pol Cust '.$label.' '.$suffix,
            Email::fromString('pol.cust.'.$suffix.'@example.com'),
        );
        $office = PartyAccount::createOrganization(
            'Pol Off '.$label.' '.$suffix,
            Email::fromString('pol.off.'.$suffix.'@example.com'),
        );
        $accounts->save($customer);
        $unitOfWork->commit();
        $accounts->save($office);
        $unitOfWork->commit();

        $folder = BookingFolder::create('POL-'.$suffix, (int) $customer->id(), (int) $office->id());
        $folders->save($folder);
        $unitOfWork->commit();

        $booking = (new CreateBookingHandler($bookings, new BookingReferentialValidator($connection), $unitOfWork))(
            new CreateBookingCommand(
                folderId: (int) $folder->id(),
                serviceTypeCode: $serviceTypeCode,
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

        return [
            'publicId' => $booking->publicId()->toString(),
            'bookingId' => (int) $booking->id(),
        ];
    }

    private function addHotelRoom(int $bookingId): int
    {
        $container = static::getContainer();
        /** @var UnitOfWork $unitOfWork */
        $unitOfWork = $container->get(UnitOfWork::class);
        /** @var BookingRepositoryInterface $bookings */
        $bookings = $container->get(BookingRepositoryInterface::class);
        /** @var Connection $connection */
        $connection = $container->get(Connection::class);
        /** @var BookingHotelRoomRepositoryInterface $rooms */
        $rooms = $container->get(BookingHotelRoomRepositoryInterface::class);

        $handler = new AddBookingHotelRoomHandler(
            new AssertBookingServiceType($connection),
            $rooms,

            $unitOfWork
);
        $room = $handler(new AddBookingHotelRoomCommand($bookingId, 'Double'));

        return (int) $room->id();
    }
}
