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

final class AssignBookingSettlementControllerTest extends WebTestCase
{
    public function test_assign_settlement_returns_201_without_internal_id(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);
        $ctx = $this->seedBooking('SettleOk');

        $client->request(
            'POST',
            '/api/v1/bookings/'.$ctx['publicId'].'/settlements',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
            content: json_encode([
                'beneficiaryAccountId' => $ctx['beneficiaryAccountId'],
                'beneficiaryRole' => 'supplier',
                'amountOwedMinor' => 8_000,
                'currencyCode' => 'TND',
                'amountSettledDirectMinor' => 1_000,
                'rate' => '12.500',
                'resalePriceAmountMinor' => 9_000,
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(201);
        self::assertTrue($client->getResponse()->headers->has(RequestIdSubscriber::HEADER_NAME));
        self::assertFalse($client->getResponse()->headers->has('Location'));

        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayNotHasKey('id', $payload);
        self::assertArrayNotHasKey('bookingId', $payload);
        self::assertSame($ctx['beneficiaryAccountId'], $payload['beneficiaryAccountId']);
        self::assertSame('supplier', $payload['beneficiaryRole']);
        self::assertSame(8_000, $payload['amountOwedMinor']);
        self::assertSame(1_000, $payload['amountSettledDirectMinor']);
        self::assertSame('TND', $payload['currencyCode']);
        self::assertSame('12.500', $payload['rate']);
        self::assertSame(9_000, $payload['resalePriceAmountMinor']);
    }

    public function test_assign_settlement_on_missing_booking_returns_404(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);

        $client->request(
            'POST',
            '/api/v1/bookings/00000000-0000-4000-8000-000000000081/settlements',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
            content: json_encode([
                'beneficiaryAccountId' => 1,
                'beneficiaryRole' => 'supplier',
                'amountOwedMinor' => 100,
                'currencyCode' => 'TND',
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(404);
        /** @var array{error: array{code: string}} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('booking.not_found', $payload['error']['code']);
    }

    public function test_duplicate_active_settlement_returns_409(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);
        $ctx = $this->seedBooking('SettleDup');

        $body = [
            'beneficiaryAccountId' => $ctx['beneficiaryAccountId'],
            'beneficiaryRole' => 'main_agency',
            'amountOwedMinor' => 3_000,
            'currencyCode' => 'TND',
        ];

        $client->request(
            'POST',
            '/api/v1/bookings/'.$ctx['publicId'].'/settlements',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
            content: json_encode($body, JSON_THROW_ON_ERROR),
        );
        self::assertResponseStatusCodeSame(201);

        $client->request(
            'POST',
            '/api/v1/bookings/'.$ctx['publicId'].'/settlements',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_ACCEPT_LANGUAGE' => 'en',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
            content: json_encode($body, JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(409);
        /** @var array{error: array{code: string, context: array<string, mixed>}} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('booking_settlement.already_active', $payload['error']['code']);
        self::assertSame($ctx['bookingId'], $payload['error']['context']['booking_id']);
        self::assertSame('main_agency', $payload['error']['context']['beneficiary_role']);
        self::assertSame($ctx['beneficiaryAccountId'], $payload['error']['context']['beneficiary_account_id']);
    }

    public function test_invalid_currency_code_returns_422(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);
        $ctx = $this->seedBooking('SettleBadCur');

        $client->request(
            'POST',
            '/api/v1/bookings/'.$ctx['publicId'].'/settlements',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_ACCEPT_LANGUAGE' => 'en',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
            content: json_encode([
                'beneficiaryAccountId' => $ctx['beneficiaryAccountId'],
                'beneficiaryRole' => 'distributor',
                'amountOwedMinor' => 500,
                // Length=3 passe la validation DTO ; Money refuse le format lettre.
                'currencyCode' => '12X',
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(422);
        /** @var array{error: array{code: string, context: array<string, mixed>}} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('money.invalid_currency_code', $payload['error']['code']);
        self::assertSame('12X', $payload['error']['context']['value']);
    }

    public function test_invalid_beneficiary_role_returns_422_not_500(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);
        $ctx = $this->seedBooking('SettleBadRole');

        $client->request(
            'POST',
            '/api/v1/bookings/'.$ctx['publicId'].'/settlements',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
            content: json_encode([
                'beneficiaryAccountId' => $ctx['beneficiaryAccountId'],
                'beneficiaryRole' => 'not_a_real_role',
                'amountOwedMinor' => 100,
                'currencyCode' => 'TND',
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(422);
        /** @var array{error: array{code: string}} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('validation_failed', $payload['error']['code']);
    }

    public function test_malformed_settlement_body_returns_422(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);
        $ctx = $this->seedBooking('Settle422');

        $client->request(
            'POST',
            '/api/v1/bookings/'.$ctx['publicId'].'/settlements',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
            content: json_encode([
                'beneficiaryAccountId' => 'nope',
                'beneficiaryRole' => 'supplier',
                'amountOwedMinor' => 100,
                'currencyCode' => 'TND',
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(422);
        /** @var array{error: array{code: string}} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('validation_failed', $payload['error']['code']);
    }

    public function test_assign_settlement_without_jwt_returns_401(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/v1/bookings/00000000-0000-4000-8000-000000000082/settlements',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode([
                'beneficiaryAccountId' => 1,
                'beneficiaryRole' => 'supplier',
                'amountOwedMinor' => 100,
                'currencyCode' => 'TND',
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
        $email = 'http.bk.settle.'.$suffix.'@example.com';
        $password = 'Http-Bk-Settle-'.$suffix;

        /** @var PartyAccountRepositoryInterface $accounts */
        $accounts = $container->get(PartyAccountRepositoryInterface::class);
        /** @var CoreCredentialRepositoryInterface $credentials */
        $credentials = $container->get(CoreCredentialRepositoryInterface::class);
        /** @var PasswordHasherInterface $hasher */
        $hasher = $container->get(PasswordHasherInterface::class);

        $account = PartyAccount::createPerson('Http Bk Settle '.$suffix, Email::fromString($email));
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
     * @return array{publicId: string, bookingId: int, beneficiaryAccountId: int}
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
            'St Cust '.$label.' '.$suffix,
            Email::fromString('st.cust.'.$suffix.'@example.com'),
        );
        $office = PartyAccount::createOrganization(
            'St Off '.$label.' '.$suffix,
            Email::fromString('st.off.'.$suffix.'@example.com'),
        );
        $beneficiary = PartyAccount::createOrganization(
            'St Ben '.$label.' '.$suffix,
            Email::fromString('st.ben.'.$suffix.'@example.com'),
        );
        $accounts->save($customer);
        $unitOfWork->commit();
        $accounts->save($office);
        $unitOfWork->commit();
        $accounts->save($beneficiary);
        $unitOfWork->commit();

        $folder = BookingFolder::create('ST-'.$suffix, (int) $customer->id(), (int) $office->id());
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

        return [
            'publicId' => $booking->publicId()->toString(),
            'bookingId' => (int) $booking->id(),
            'beneficiaryAccountId' => (int) $beneficiary->id(),
        ];
    }
}
