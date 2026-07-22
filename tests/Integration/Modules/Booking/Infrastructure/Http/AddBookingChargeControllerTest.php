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
use App\Shared\Infrastructure\Persistence\UnitOfWork;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AddBookingChargeControllerTest extends WebTestCase
{
    public function test_add_charge_returns_201_without_internal_id(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);
        $booking = $this->seedBooking('ChargeOk');

        $client->request(
            'POST',
            '/api/v1/bookings/'.$booking['publicId'].'/charges',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
            content: json_encode([
                'chargeTypeCode' => 'fare',
                'achatAmountMinor' => 5_000,
                'achatCurrencyCode' => 'TND',
                'venteAmountMinor' => 6_000,
                'venteCurrencyCode' => 'TND',
                'label' => 'Base fare',
                'metadata' => ['source' => 'http'],
                'sortOrder' => 2,
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(201);
        self::assertTrue($client->getResponse()->headers->has(RequestIdSubscriber::HEADER_NAME));
        self::assertFalse($client->getResponse()->headers->has('Location'));

        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayNotHasKey('id', $payload);
        self::assertArrayNotHasKey('bookingId', $payload);
        self::assertSame('fare', $payload['chargeTypeCode']);
        self::assertSame(5_000, $payload['achatAmountMinor']);
        self::assertSame('TND', $payload['achatCurrencyCode']);
        self::assertSame(6_000, $payload['venteAmountMinor']);
        self::assertSame('TND', $payload['venteCurrencyCode']);
        self::assertSame('Base fare', $payload['label']);
        self::assertSame(['source' => 'http'], $payload['metadata']);
        self::assertSame(2, $payload['sortOrder']);
        self::assertNull($payload['travelerId']);
        self::assertNull($payload['segmentId']);
    }

    public function test_add_charge_on_missing_booking_returns_404(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);

        $client->request(
            'POST',
            '/api/v1/bookings/00000000-0000-4000-8000-000000000071/charges',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
            content: json_encode($this->validChargeBody(), JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(404);
        /** @var array{error: array{code: string}} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('booking.not_found', $payload['error']['code']);
    }

    public function test_unknown_charge_type_returns_422(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);
        $booking = $this->seedBooking('ChargeUnkType');

        $body = $this->validChargeBody();
        $body['chargeTypeCode'] = 'not_a_real_charge_type';

        $client->request(
            'POST',
            '/api/v1/bookings/'.$booking['publicId'].'/charges',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_ACCEPT_LANGUAGE' => 'en',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
            content: json_encode($body, JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(422);
        /** @var array{error: array{code: string, context: array<string, mixed>}} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('booking.unknown_charge_type', $payload['error']['code']);
        self::assertSame('not_a_real_charge_type', $payload['error']['context']['code']);
    }

    public function test_traveler_mismatch_returns_422(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);
        $booking = $this->seedBooking('ChargeTravMismatch');

        $body = $this->validChargeBody();
        $body['travelerId'] = 999_999_991;

        $client->request(
            'POST',
            '/api/v1/bookings/'.$booking['publicId'].'/charges',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_ACCEPT_LANGUAGE' => 'en',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
            content: json_encode($body, JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(422);
        /** @var array{error: array{code: string, context: array<string, mixed>}} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('booking_charge.traveler_mismatch', $payload['error']['code']);
        self::assertSame(999_999_991, $payload['error']['context']['traveler_id']);
        self::assertSame($booking['bookingId'], $payload['error']['context']['booking_id']);
    }

    public function test_malformed_charge_body_returns_422(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);
        $booking = $this->seedBooking('Charge422');

        $client->request(
            'POST',
            '/api/v1/bookings/'.$booking['publicId'].'/charges',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
            content: json_encode([
                'chargeTypeCode' => 'fare',
                'achatAmountMinor' => 'not-an-int',
                'achatCurrencyCode' => 'TND',
                'venteAmountMinor' => 100,
                'venteCurrencyCode' => 'TND',
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(422);
        /** @var array{error: array{code: string}} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('validation_failed', $payload['error']['code']);
    }

    public function test_add_charge_without_jwt_returns_401(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/v1/bookings/00000000-0000-4000-8000-000000000072/charges',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode($this->validChargeBody(), JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(401);
    }

    /**
     * @return array<string, mixed>
     */
    private function validChargeBody(): array
    {
        return [
            'chargeTypeCode' => 'fare',
            'achatAmountMinor' => 1_000,
            'achatCurrencyCode' => 'TND',
            'venteAmountMinor' => 1_200,
            'venteCurrencyCode' => 'TND',
        ];
    }

    private function authenticate(KernelBrowser $client): string
    {
        $container = static::getContainer();
        /** @var UnitOfWork $unitOfWork */
        $unitOfWork = $container->get(UnitOfWork::class);
        $suffix = bin2hex(random_bytes(4));
        $email = 'http.bk.charge.'.$suffix.'@example.com';
        $password = 'Http-Bk-Charge-'.$suffix;

        /** @var PartyAccountRepositoryInterface $accounts */
        $accounts = $container->get(PartyAccountRepositoryInterface::class);
        /** @var CoreCredentialRepositoryInterface $credentials */
        $credentials = $container->get(CoreCredentialRepositoryInterface::class);
        /** @var PasswordHasherInterface $hasher */
        $hasher = $container->get(PasswordHasherInterface::class);

        $account = PartyAccount::createPerson('Http Bk Charge '.$suffix, Email::fromString($email));
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
            'Ch Cust '.$label.' '.$suffix,
            Email::fromString('ch.cust.'.$suffix.'@example.com'),
        );
        $office = PartyAccount::createOrganization(
            'Ch Off '.$label.' '.$suffix,
            Email::fromString('ch.off.'.$suffix.'@example.com'),
        );
        $accounts->save($customer);
        $unitOfWork->commit();
        $accounts->save($office);
        $unitOfWork->commit();

        $folder = BookingFolder::create('CH-'.$suffix, (int) $customer->id(), (int) $office->id());
        $folders->save($folder);
        $unitOfWork->commit();

        $booking = (new CreateBookingHandler($bookings, new BookingReferentialValidator($connection), $unitOfWork))(
            new CreateBookingCommand(
                folderId: (int) $folder->id(),
                serviceTypeCode: 'flight',
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
}
