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

final class SetBookingCarRentalDetailControllerTest extends WebTestCase
{
    public function test_set_car_rental_detail_returns_200_without_internal_id(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);
        $booking = $this->seedBooking('car_rental', 'CarOk');

        $client->request(
            'PUT',
            '/api/v1/bookings/'.$booking['publicId'].'/car-rental-detail',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
            content: json_encode([
                'vehicleCategory' => 'Mini I10 ou similaire',
                'vehicleBrandModel' => 'Hyundai i10',
                'pickupAt' => '2026-10-01T09:00:00+01:00',
                'dropoffAt' => '2026-10-05T09:00:00+01:00',
                'pickupLocation' => 'TUN Airport',
                'dropoffLocation' => 'TUN Airport',
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(200);
        self::assertTrue($client->getResponse()->headers->has(RequestIdSubscriber::HEADER_NAME));
        self::assertFalse($client->getResponse()->headers->has('Location'));

        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayNotHasKey('id', $payload);
        self::assertArrayNotHasKey('bookingId', $payload);
        self::assertSame('Mini I10 ou similaire', $payload['vehicleCategory']);
        self::assertSame('Hyundai i10', $payload['vehicleBrandModel']);
        self::assertSame('TUN Airport', $payload['pickupLocation']);
        self::assertSame('TUN Airport', $payload['dropoffLocation']);
        self::assertIsString($payload['pickupAt']);
        self::assertIsString($payload['dropoffAt']);
    }

    public function test_set_car_rental_on_missing_booking_returns_404(): void
    {
        $missing = '00000000-0000-4000-8000-000000000022';
        $client = static::createClient();
        $token = $this->authenticate($client);

        $client->request(
            'PUT',
            '/api/v1/bookings/'.$missing.'/car-rental-detail',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
            content: json_encode(['vehicleCategory' => 'SUV'], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(404);
    }

    public function test_set_car_rental_on_hotel_booking_returns_409(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);
        $booking = $this->seedBooking('hotel', 'CarMismatch');

        $client->request(
            'PUT',
            '/api/v1/bookings/'.$booking['publicId'].'/car-rental-detail',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_ACCEPT_LANGUAGE' => 'en',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
            content: json_encode(['vehicleCategory' => 'Should Fail'], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(409);
        /** @var array{error: array{code: string, context: array<string, mixed>}} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('booking.service_type_mismatch', $payload['error']['code']);
        self::assertSame('car_rental', $payload['error']['context']['extension_code']);
        self::assertSame('hotel', $payload['error']['context']['actual_service_type']);
    }

    public function test_malformed_pickup_at_returns_422(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);
        $booking = $this->seedBooking('car_rental', 'Car422');

        $client->request(
            'PUT',
            '/api/v1/bookings/'.$booking['publicId'].'/car-rental-detail',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
            content: json_encode(['pickupAt' => 'not-a-datetime'], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(422);
    }

    public function test_set_car_rental_without_jwt_returns_401(): void
    {
        $client = static::createClient();

        $client->request(
            'PUT',
            '/api/v1/bookings/00000000-0000-4000-8000-000000000021/car-rental-detail',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode(['vehicleCategory' => 'SUV'], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(401);
    }

    private function authenticate(KernelBrowser $client): string
    {
        $container = static::getContainer();
        /** @var UnitOfWork $unitOfWork */
        $unitOfWork = $container->get(UnitOfWork::class);
        $suffix = bin2hex(random_bytes(4));
        $email = 'http.bk.car.'.$suffix.'@example.com';
        $password = 'Http-Bk-Car-'.$suffix;

        /** @var PartyAccountRepositoryInterface $accounts */
        $accounts = $container->get(PartyAccountRepositoryInterface::class);
        /** @var CoreCredentialRepositoryInterface $credentials */
        $credentials = $container->get(CoreCredentialRepositoryInterface::class);
        /** @var PasswordHasherInterface $hasher */
        $hasher = $container->get(PasswordHasherInterface::class);

        $account = PartyAccount::createPerson('Http Bk Car '.$suffix, Email::fromString($email));
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
     * @return array{publicId: string}
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
            'Car Cust '.$label.' '.$suffix,
            Email::fromString('car.cust.'.$suffix.'@example.com'),
        );
        $office = PartyAccount::createOrganization(
            'Car Off '.$label.' '.$suffix,
            Email::fromString('car.off.'.$suffix.'@example.com'),
        );
        $accounts->save($customer);
        $unitOfWork->commit();
        $accounts->save($office);
        $unitOfWork->commit();

        $folder = BookingFolder::create('CAR-'.$suffix, (int) $customer->id(), (int) $office->id());
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

        return ['publicId' => $booking->publicId()->toString()];
    }
}
