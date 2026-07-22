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

final class AssignBookingPayerSplitControllerTest extends WebTestCase
{
    public function test_assign_payer_split_returns_201_without_internal_id(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);
        $ctx = $this->seedBooking('SplitOk', totalVente: 12_000);

        $client->request(
            'POST',
            '/api/v1/bookings/'.$ctx['publicId'].'/payer-splits',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
            content: json_encode([
                'payerAccountId' => $ctx['payerAccountId'],
                'amountMinor' => 4_000,
                'currencyCode' => 'TND',
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(201);
        self::assertTrue($client->getResponse()->headers->has(RequestIdSubscriber::HEADER_NAME));
        self::assertFalse($client->getResponse()->headers->has('Location'));

        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayNotHasKey('id', $payload);
        self::assertArrayNotHasKey('bookingId', $payload);
        self::assertSame($ctx['payerAccountId'], $payload['payerAccountId']);
        self::assertSame(4_000, $payload['amountMinor']);
        self::assertSame('TND', $payload['currencyCode']);
    }

    public function test_assign_payer_split_on_missing_booking_returns_404(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);

        $client->request(
            'POST',
            '/api/v1/bookings/00000000-0000-4000-8000-000000000091/payer-splits',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
            content: json_encode([
                'payerAccountId' => 1,
                'amountMinor' => 100,
                'currencyCode' => 'TND',
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(404);
        /** @var array{error: array{code: string}} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('booking.not_found', $payload['error']['code']);
    }

    public function test_exceeds_total_returns_422_with_context(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);
        $ctx = $this->seedBooking('SplitExceed', totalVente: 5_000);

        $client->request(
            'POST',
            '/api/v1/bookings/'.$ctx['publicId'].'/payer-splits',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_ACCEPT_LANGUAGE' => 'en',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
            content: json_encode([
                'payerAccountId' => $ctx['payerAccountId'],
                'amountMinor' => 5_001,
                'currencyCode' => 'TND',
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(422);
        /** @var array{error: array{code: string, message: string, context: array<string, mixed>}} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('booking_payer_split.exceeds_total', $payload['error']['code']);
        self::assertSame($ctx['bookingId'], $payload['error']['context']['booking_id']);
        self::assertSame(0, $payload['error']['context']['already_allocated_minor']);
        self::assertSame(5_001, $payload['error']['context']['requested_minor']);
        self::assertSame(5_000, $payload['error']['context']['allowed_total_minor']);
        self::assertNotSame('', $payload['error']['message']);
        self::assertNotSame('booking_payer_split.exceeds_total', $payload['error']['message']);
    }

    public function test_currency_mismatch_returns_422(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);
        $ctx = $this->seedBooking('SplitCurMis', totalVente: 12_000);

        $client->request(
            'POST',
            '/api/v1/bookings/'.$ctx['publicId'].'/payer-splits',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_ACCEPT_LANGUAGE' => 'en',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
            content: json_encode([
                'payerAccountId' => $ctx['payerAccountId'],
                'amountMinor' => 1_000,
                'currencyCode' => 'EUR',
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(422);
        /** @var array{error: array{code: string, context: array<string, mixed>}} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('booking_payer_split.currency_mismatch', $payload['error']['code']);
        self::assertSame('TND', $payload['error']['context']['expected_currency']);
        self::assertSame('EUR', $payload['error']['context']['actual_currency']);
    }

    public function test_duplicate_active_payer_split_returns_409(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);
        $ctx = $this->seedBooking('SplitDup', totalVente: 12_000);

        $body = [
            'payerAccountId' => $ctx['payerAccountId'],
            'amountMinor' => 2_000,
            'currencyCode' => 'TND',
        ];

        $client->request(
            'POST',
            '/api/v1/bookings/'.$ctx['publicId'].'/payer-splits',
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
            '/api/v1/bookings/'.$ctx['publicId'].'/payer-splits',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_ACCEPT_LANGUAGE' => 'en',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
            content: json_encode($body, JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(409);
        /** @var array{error: array{code: string}} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('booking_payer_split.already_active', $payload['error']['code']);
    }

    public function test_malformed_payer_split_body_returns_422(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);
        $ctx = $this->seedBooking('Split422', totalVente: 12_000);

        $client->request(
            'POST',
            '/api/v1/bookings/'.$ctx['publicId'].'/payer-splits',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
            content: json_encode([
                'payerAccountId' => 'bad',
                'amountMinor' => 100,
                'currencyCode' => 'TND',
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(422);
        /** @var array{error: array{code: string}} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('validation_failed', $payload['error']['code']);
    }

    public function test_assign_payer_split_without_jwt_returns_401(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/v1/bookings/00000000-0000-4000-8000-000000000092/payer-splits',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode([
                'payerAccountId' => 1,
                'amountMinor' => 100,
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
        $email = 'http.bk.paysplit.'.$suffix.'@example.com';
        $password = 'Http-Bk-PaySplit-'.$suffix;

        /** @var PartyAccountRepositoryInterface $accounts */
        $accounts = $container->get(PartyAccountRepositoryInterface::class);
        /** @var CoreCredentialRepositoryInterface $credentials */
        $credentials = $container->get(CoreCredentialRepositoryInterface::class);
        /** @var PasswordHasherInterface $hasher */
        $hasher = $container->get(PasswordHasherInterface::class);

        $account = PartyAccount::createPerson('Http Bk PaySplit '.$suffix, Email::fromString($email));
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
     * @return array{publicId: string, bookingId: int, payerAccountId: int}
     */
    private function seedBooking(string $label, int $totalVente): array
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
            'Ps Cust '.$label.' '.$suffix,
            Email::fromString('ps.cust.'.$suffix.'@example.com'),
        );
        $office = PartyAccount::createOrganization(
            'Ps Off '.$label.' '.$suffix,
            Email::fromString('ps.off.'.$suffix.'@example.com'),
        );
        $payer = PartyAccount::createOrganization(
            'Ps Pay '.$label.' '.$suffix,
            Email::fromString('ps.pay.'.$suffix.'@example.com'),
        );
        $accounts->save($customer);
        $unitOfWork->commit();
        $accounts->save($office);
        $unitOfWork->commit();
        $accounts->save($payer);
        $unitOfWork->commit();

        $folder = BookingFolder::create('PS-'.$suffix, (int) $customer->id(), (int) $office->id());
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
                totalVenteAmount: $totalVente,
                margeAgenceAmount: 1_500,
                margeDistributeurAmount: 500,
                paidAmount: 0,
            ),
        );

        return [
            'publicId' => $booking->publicId()->toString(),
            'bookingId' => (int) $booking->id(),
            'payerAccountId' => (int) $payer->id(),
        ];
    }
}
