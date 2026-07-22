<?php

declare(strict_types=1);

namespace App\Tests\Integration\Modules\Booking\Infrastructure\Http;

use App\Modules\Booking\Domain\Entity\BookingFolder;
use App\Modules\Booking\Domain\Repository\BookingFolderRepositoryInterface;
use App\Modules\Core\Domain\Entity\CoreCredential;
use App\Modules\Core\Domain\Repository\CoreCredentialRepositoryInterface;
use App\Modules\Core\Domain\Security\PasswordHasherInterface;
use App\Modules\Party\Domain\Entity\PartyAccount;
use App\Modules\Party\Domain\Repository\PartyAccountRepositoryInterface;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Infrastructure\Logging\RequestIdSubscriber;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

final class CreateBookingControllerTest extends WebTestCase
{
    public function test_create_valid_returns_201_with_location_and_is_readable_via_get(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);
        $ctx = $this->seedFolderContext('CreateOk');

        $body = $this->validPayload($ctx, [
            'totalAchatAmount' => 10_000,
            'totalVenteAmount' => 12_000,
            'margeAgenceAmount' => 1_500,
            'margeDistributeurAmount' => 500,
            'paidAmount' => 0,
        ]);

        $client->request(
            'POST',
            '/api/v1/bookings',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
            content: json_encode($body, JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(201);
        self::assertTrue($client->getResponse()->headers->has(RequestIdSubscriber::HEADER_NAME));

        $location = $client->getResponse()->headers->get('Location');
        self::assertNotNull($location);
        self::assertMatchesRegularExpression('#^/api/v1/bookings/[0-9a-fA-F-]{36}$#', $location);

        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayNotHasKey('id', $payload);
        self::assertIsString($payload['publicId']);
        self::assertSame('/api/v1/bookings/'.$payload['publicId'], $location);
        self::assertSame($ctx['folderId'], $payload['folderId']);
        self::assertSame(
            [
                'serviceTypeCode' => 'hotel',
                'statusCode' => 'draft',
                'channelCode' => 'backoffice',
            ],
            $payload['status'],
        );
        self::assertIsArray($payload['montants']);
        /** @var array<string, mixed> $montants */
        $montants = $payload['montants'];
        self::assertSame(['amount' => 10_000, 'currencyCode' => 'TND'], $montants['totalAchatAmount']);
        self::assertSame(['amount' => 12_000, 'currencyCode' => 'TND'], $montants['totalVenteAmount']);
        self::assertSame('unpaid', $montants['paymentStatus']);

        $client->request(
            'GET',
            $location,
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
        );
        self::assertResponseStatusCodeSame(200);

        /** @var array<string, mixed> $getPayload */
        $getPayload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($payload, $getPayload);
    }

    public function test_unknown_service_type_code_returns_422(): void
    {
        $this->assertUnknownReferentialReturns422(
            ['serviceTypeCode' => 'not_a_real_service'],
            'booking.unknown_service_type',
            'serviceTypeCode',
            'not_a_real_service',
        );
    }

    public function test_unknown_status_code_returns_422(): void
    {
        $this->assertUnknownReferentialReturns422(
            ['statusCode' => 'not_a_real_status'],
            'booking.unknown_status',
            'statusCode',
            'not_a_real_status',
        );
    }

    public function test_unknown_channel_code_returns_422(): void
    {
        $this->assertUnknownReferentialReturns422(
            ['channelCode' => 'not_a_real_channel'],
            'booking.unknown_channel',
            'channelCode',
            'not_a_real_channel',
        );
    }

    public function test_unknown_achat_currency_code_returns_422(): void
    {
        $this->assertUnknownReferentialReturns422(
            ['achatCurrencyCode' => 'ZZZ'],
            'booking.unknown_currency',
            'achatCurrencyCode',
            'ZZZ',
        );
    }

    public function test_unknown_vente_currency_code_returns_422(): void
    {
        $this->assertUnknownReferentialReturns422(
            ['venteCurrencyCode' => 'ZZZ'],
            'booking.unknown_currency',
            'venteCurrencyCode',
            'ZZZ',
        );
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function assertUnknownReferentialReturns422(
        array $overrides,
        string $errorCode,
        string $field,
        string $code,
    ): void {
        $client = static::createClient();
        $token = $this->authenticate($client);
        $ctx = $this->seedFolderContext('UnkRef');

        $client->request(
            'POST',
            '/api/v1/bookings',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_ACCEPT_LANGUAGE' => 'en',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
            content: json_encode($this->validPayload($ctx, $overrides), JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(422);
        self::assertTrue($client->getResponse()->headers->has(RequestIdSubscriber::HEADER_NAME));

        /** @var array{error: array{code: string, message: string, context: array<string, mixed>}} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($errorCode, $payload['error']['code']);
        self::assertNotSame('', $payload['error']['message']);
        self::assertNotSame($errorCode, $payload['error']['message']);
        self::assertSame($field, $payload['error']['context']['field']);
        self::assertSame($code, $payload['error']['context']['code']);
    }

    public function test_end_date_before_start_date_returns_400_domain(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);
        $ctx = $this->seedFolderContext('CreateBadDates');

        $client->request(
            'POST',
            '/api/v1/bookings',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_ACCEPT_LANGUAGE' => 'fr',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
            content: json_encode(
                $this->validPayload($ctx, [
                    'startDate' => '2026-09-10',
                    'endDate' => '2026-09-01',
                ]),
                JSON_THROW_ON_ERROR,
            ),
        );

        self::assertResponseStatusCodeSame(400);
        self::assertTrue($client->getResponse()->headers->has(RequestIdSubscriber::HEADER_NAME));

        /** @var array{error: array{code: string, message: string}} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('booking.invalid_dates', $payload['error']['code']);
        self::assertSame(
            'La date de fin doit être postérieure ou égale à la date de début.',
            $payload['error']['message'],
        );
    }

    public function test_missing_folder_id_returns_422(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);
        $ctx = $this->seedFolderContext('CreateNoFolder');

        $body = $this->validPayload($ctx);
        unset($body['folderId']);

        $client->request(
            'POST',
            '/api/v1/bookings',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
            content: json_encode($body, JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(422);

        /** @var array{error: array{code: string, violations: list<array{field: string}>}} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('validation_failed', $payload['error']['code']);
        $fields = array_column($payload['error']['violations'], 'field');
        self::assertContains('folderId', $fields);
    }

    public function test_non_integer_folder_id_returns_422(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);
        $ctx = $this->seedFolderContext('CreateBadFolder');

        $client->request(
            'POST',
            '/api/v1/bookings',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
            content: json_encode(
                $this->validPayload($ctx, ['folderId' => 'not-an-int']),
                JSON_THROW_ON_ERROR,
            ),
        );

        self::assertResponseStatusCodeSame(422);

        /** @var array{error: array{code: string, violations: list<array{field: string}>}} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('validation_failed', $payload['error']['code']);
        $fields = array_column($payload['error']['violations'], 'field');
        self::assertContains('folderId', $fields);
    }

    public function test_create_without_jwt_returns_401(): void
    {
        $client = static::createClient();

        $client->request(
            'POST',
            '/api/v1/bookings',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: json_encode(['folderId' => 1], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(401);
    }

    /**
     * @param array{folderId: int, customerId: int, officeId: int} $ctx
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function validPayload(array $ctx, array $overrides = []): array
    {
        return array_merge([
            'folderId' => $ctx['folderId'],
            'serviceTypeCode' => 'hotel',
            'statusCode' => 'draft',
            'customerAccountId' => $ctx['customerId'],
            'supplierAccountId' => null,
            'officeAccountId' => $ctx['officeId'],
            'startDate' => '2026-09-01',
            'endDate' => '2026-09-03',
            'channelCode' => 'backoffice',
            'achatCurrencyCode' => 'TND',
            'venteCurrencyCode' => 'TND',
            'achatExchangeRate' => '1',
            'venteExchangeRate' => '1',
            'totalAchatAmount' => 0,
            'totalVenteAmount' => 0,
            'margeAgenceAmount' => 0,
            'margeDistributeurAmount' => 0,
            'paidAmount' => 0,
        ], $overrides);
    }

    private function authenticate(KernelBrowser $client): string
    {
        $container = static::getContainer();
        /** @var UnitOfWork $unitOfWork */
        $unitOfWork = $container->get(UnitOfWork::class);
        $suffix = bin2hex(random_bytes(4));
        $email = 'http.booking.create.'.$suffix.'@example.com';
        $password = 'Http-Booking-Create-'.$suffix;

        /** @var PartyAccountRepositoryInterface $accounts */
        $accounts = $container->get(PartyAccountRepositoryInterface::class);
        /** @var CoreCredentialRepositoryInterface $credentials */
        $credentials = $container->get(CoreCredentialRepositoryInterface::class);
        /** @var PasswordHasherInterface $hasher */
        $hasher = $container->get(PasswordHasherInterface::class);

        $account = PartyAccount::createPerson('Http Booking Create '.$suffix, Email::fromString($email));
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
     * @return array{folderId: int, customerId: int, officeId: int}
     */
    private function seedFolderContext(string $label): array
    {
        $container = static::getContainer();
        /** @var UnitOfWork $unitOfWork */
        $unitOfWork = $container->get(UnitOfWork::class);
        $suffix = bin2hex(random_bytes(4));

        /** @var PartyAccountRepositoryInterface $accounts */
        $accounts = $container->get(PartyAccountRepositoryInterface::class);
        /** @var BookingFolderRepositoryInterface $folders */
        $folders = $container->get(BookingFolderRepositoryInterface::class);

        $customer = PartyAccount::createOrganization(
            $label.' Cust '.$suffix,
            Email::fromString('bkcreate.cust.'.$suffix.'@example.com'),
        );
        $office = PartyAccount::createOrganization(
            $label.' Off '.$suffix,
            Email::fromString('bkcreate.off.'.$suffix.'@example.com'),
        );
        $accounts->save($customer);
        $unitOfWork->commit();
        $accounts->save($office);
        $unitOfWork->commit();

        $folder = BookingFolder::create(
            'BC-'.substr($label, 0, 8).'-'.$suffix,
            (int) $customer->id(),
            (int) $office->id(),
        );
        $folders->save($folder);
        $unitOfWork->commit();

        return [
            'folderId' => (int) $folder->id(),
            'customerId' => (int) $customer->id(),
            'officeId' => (int) $office->id(),
        ];
    }
}
