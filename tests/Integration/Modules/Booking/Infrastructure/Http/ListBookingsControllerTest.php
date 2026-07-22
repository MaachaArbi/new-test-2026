<?php

declare(strict_types=1);

namespace App\Tests\Integration\Modules\Booking\Infrastructure\Http;

use App\Modules\Booking\Application\BookingReferentialValidator;
use App\Modules\Booking\Application\CreateBooking\CreateBookingCommand;
use App\Modules\Booking\Application\CreateBooking\CreateBookingHandler;
use App\Shared\Application\ListPagination;
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

final class ListBookingsControllerTest extends WebTestCase
{
    public function test_list_without_filters_returns_paginated_structure(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);
        $suffix = bin2hex(random_bytes(4));
        $ctx = $this->seedContext($suffix);

        $this->createBooking($ctx, 'hotel', 'draft', 10_000);
        $this->createBooking($ctx, 'hotel', 'draft', 11_000);
        $this->createBooking($ctx, 'hotel', 'draft', 12_000);

        $client->request(
            'GET',
            '/api/v1/bookings?limit=2&page=1&folderId='.$ctx['folderId'],
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
        );

        self::assertResponseStatusCodeSame(200);
        self::assertTrue($client->getResponse()->headers->has(RequestIdSubscriber::HEADER_NAME));

        /** @var array{data: list<array<string, mixed>>, meta: array{page: int, limit: int, total: int, totalPages: int}} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(1, $payload['meta']['page']);
        self::assertSame(2, $payload['meta']['limit']);
        self::assertSame(3, $payload['meta']['total']);
        self::assertSame(2, $payload['meta']['totalPages']);
        self::assertCount(2, $payload['data']);

        foreach ($payload['data'] as $item) {
            self::assertArrayNotHasKey('id', $item);
            self::assertArrayHasKey('publicId', $item);
            self::assertArrayHasKey('bookingDate', $item);
            self::assertArrayHasKey('serviceTypeCode', $item);
            self::assertArrayHasKey('statusCode', $item);
            self::assertArrayHasKey('customerAccountId', $item);
            self::assertArrayHasKey('totalVenteAmount', $item);
            self::assertIsArray($item['totalVenteAmount']);
            self::assertArrayHasKey('amount', $item['totalVenteAmount']);
            self::assertArrayHasKey('currencyCode', $item['totalVenteAmount']);
            self::assertCount(6, $item);
        }

        $client->request(
            'GET',
            '/api/v1/bookings?limit=2&page=2&folderId='.$ctx['folderId'],
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
        );
        self::assertResponseStatusCodeSame(200);

        /** @var array{data: list<array<string, mixed>>, meta: array{total: int, totalPages: int}} $page2 */
        $page2 = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(3, $page2['meta']['total']);
        self::assertSame(2, $page2['meta']['totalPages']);
        self::assertCount(1, $page2['data']);
    }

    public function test_filter_by_booking_date_range_excludes_out_of_range(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);
        $suffix = bin2hex(random_bytes(4));
        $ctx = $this->seedContext($suffix);

        $inJuly = $this->createBooking($ctx, 'hotel', 'draft', 20_000);
        $inAugust = $this->createBooking($ctx, 'hotel', 'draft', 21_000);
        $inSeptember = $this->createBooking($ctx, 'hotel', 'draft', 22_000);

        $this->moveBookingDate($inJuly['publicId'], '2026-07-15');
        $this->moveBookingDate($inAugust['publicId'], '2026-08-15');
        $this->moveBookingDate($inSeptember['publicId'], '2026-09-15');

        $client->request(
            'GET',
            '/api/v1/bookings?folderId='.$ctx['folderId']
                .'&bookingDateFrom=2026-08-01&bookingDateTo=2026-08-31',
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
        );

        self::assertResponseStatusCodeSame(200);

        /** @var array{data: list<array{publicId: string, bookingDate: string}>, meta: array{total: int}} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(1, $payload['meta']['total']);
        self::assertCount(1, $payload['data']);
        self::assertSame($inAugust['publicId'], $payload['data'][0]['publicId']);
        self::assertSame('2026-08-15', $payload['data'][0]['bookingDate']);

        $returnedIds = array_column($payload['data'], 'publicId');
        self::assertNotContains($inJuly['publicId'], $returnedIds);
        self::assertNotContains($inSeptember['publicId'], $returnedIds);
    }

    public function test_filter_by_service_status_folder_and_customer(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);
        $suffix = bin2hex(random_bytes(4));
        $ctxA = $this->seedContext($suffix.'a');
        $ctxB = $this->seedContext($suffix.'b');

        $match = $this->createBooking($ctxA, 'hotel', 'draft', 30_000);
        $this->createBooking($ctxA, 'flight', 'draft', 31_000);
        $this->createBooking($ctxA, 'hotel', 'confirmed', 32_000);
        $this->createBooking($ctxB, 'hotel', 'draft', 33_000);

        $client->request(
            'GET',
            '/api/v1/bookings'
                .'?folderId='.$ctxA['folderId']
                .'&customerAccountId='.$ctxA['customerId']
                .'&serviceTypeCode=hotel'
                .'&statusCode=draft',
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
        );

        self::assertResponseStatusCodeSame(200);

        /** @var array{data: list<array{publicId: string, serviceTypeCode: string, statusCode: string, customerAccountId: int}>, meta: array{total: int}} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(1, $payload['meta']['total']);
        self::assertCount(1, $payload['data']);
        self::assertSame($match['publicId'], $payload['data'][0]['publicId']);
        self::assertSame('hotel', $payload['data'][0]['serviceTypeCode']);
        self::assertSame('draft', $payload['data'][0]['statusCode']);
        self::assertSame($ctxA['customerId'], $payload['data'][0]['customerAccountId']);
    }

    /**
     * Trace informative EXPLAIN — pas d'assertion bloquante sur le format du plan.
     * Documente si le filtre booking_date déclenche le partition pruning.
     */
    public function test_explain_date_filter_partition_pruning_trace(): void
    {
        $client = static::createClient();
        /** @var Connection $connection */
        $connection = static::getContainer()->get(Connection::class);

        $sql = <<<'SQL'
EXPLAIN (FORMAT TEXT)
SELECT public_id, booking_date, service_type_code, status_code,
       customer_account_id, total_vente_amount, vente_currency_code
FROM booking
WHERE booking_date >= '2026-08-01'
  AND booking_date <= '2026-08-31'
ORDER BY booking_date ASC, id ASC
LIMIT 20 OFFSET 0
SQL;

        $rows = $connection->fetchAllAssociative($sql);
        $planLines = [];
        foreach ($rows as $row) {
            $line = $row['QUERY PLAN'] ?? null;
            if (is_string($line)) {
                $planLines[] = $line;
            }
        }
        $plan = implode("\n", $planLines);

        fwrite(STDERR, "\n=== EXPLAIN partition pruning (booking_date Aug 2026) ===\n".$plan."\n===\n");

        // Soft observation only — format EXPLAIN peut varier selon PG / stats.
        $mentionsAugust = str_contains($plan, 'booking_y2026m08');
        $mentionsJuly = str_contains($plan, 'booking_y2026m07');
        $mentionsSeptember = str_contains($plan, 'booking_y2026m09');

        fwrite(
            STDERR,
            sprintf(
                "Pruning observation: august=%s july=%s september=%s\n",
                $mentionsAugust ? 'yes' : 'no',
                $mentionsJuly ? 'yes' : 'no',
                $mentionsSeptember ? 'yes' : 'no',
            ),
        );

        self::assertNotSame('', $plan);
        // Si le plan cite explicitement les partitions enfants, août seul est attendu.
        if ($mentionsAugust || $mentionsJuly || $mentionsSeptember) {
            self::assertTrue($mentionsAugust, 'Expected August partition in EXPLAIN plan when cited.');
            self::assertFalse($mentionsJuly, 'July partition should be pruned for Aug date filter.');
            self::assertFalse($mentionsSeptember, 'September partition should be pruned for Aug date filter.');
        }
    }

    public function test_limit_above_max_returns_422(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);

        $client->request(
            'GET',
            '/api/v1/bookings?limit='.(ListPagination::MAX_LIMIT + 1),
            server: [
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            ],
        );

        self::assertResponseStatusCodeSame(422);
    }

    public function test_list_without_jwt_returns_401(): void
    {
        $client = static::createClient();

        $client->request(
            'GET',
            '/api/v1/bookings',
            server: [
                'HTTP_ACCEPT' => 'application/json',
            ],
        );

        self::assertResponseStatusCodeSame(401);
    }

    private function authenticate(KernelBrowser $client): string
    {
        $container = static::getContainer();
        /** @var UnitOfWork $unitOfWork */
        $unitOfWork = $container->get(UnitOfWork::class);
        $suffix = bin2hex(random_bytes(4));
        $email = 'http.booking.list.'.$suffix.'@example.com';
        $password = 'Http-Booking-List-'.$suffix;

        /** @var PartyAccountRepositoryInterface $accounts */
        $accounts = $container->get(PartyAccountRepositoryInterface::class);
        /** @var CoreCredentialRepositoryInterface $credentials */
        $credentials = $container->get(CoreCredentialRepositoryInterface::class);
        /** @var PasswordHasherInterface $hasher */
        $hasher = $container->get(PasswordHasherInterface::class);

        $account = PartyAccount::createPerson('Http Booking List '.$suffix, Email::fromString($email));
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
    private function seedContext(string $suffix): array
    {
        $container = static::getContainer();
        /** @var UnitOfWork $unitOfWork */
        $unitOfWork = $container->get(UnitOfWork::class);

        /** @var PartyAccountRepositoryInterface $accounts */
        $accounts = $container->get(PartyAccountRepositoryInterface::class);
        /** @var BookingFolderRepositoryInterface $folders */
        $folders = $container->get(BookingFolderRepositoryInterface::class);

        $customer = PartyAccount::createOrganization(
            'BkList Cust '.$suffix,
            Email::fromString('bklist.cust.'.$suffix.'@example.com'),
        );
        $office = PartyAccount::createOrganization(
            'BkList Off '.$suffix,
            Email::fromString('bklist.off.'.$suffix.'@example.com'),
        );
        $accounts->save($customer);
        $unitOfWork->commit();
        $accounts->save($office);
        $unitOfWork->commit();

        $folder = BookingFolder::create(
            'BKLIST-'.$suffix,
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

    /**
     * @param array{folderId: int, customerId: int, officeId: int} $ctx
     *
     * @return array{publicId: string}
     */
    private function createBooking(array $ctx, string $serviceTypeCode, string $statusCode, int $venteAmount): array
    {
        $container = static::getContainer();
        /** @var UnitOfWork $unitOfWork */
        $unitOfWork = $container->get(UnitOfWork::class);

        /** @var BookingRepositoryInterface $bookings */
        $bookings = $container->get(BookingRepositoryInterface::class);
        /** @var Connection $connection */
        $connection = $container->get(Connection::class);

        $booking = (new CreateBookingHandler($bookings, new BookingReferentialValidator($connection), $unitOfWork))(
            new CreateBookingCommand(
                folderId: $ctx['folderId'],
                serviceTypeCode: $serviceTypeCode,
                statusCode: $statusCode,
                customerAccountId: $ctx['customerId'],
                supplierAccountId: null,
                officeAccountId: $ctx['officeId'],
                startDate: '2026-09-01',
                endDate: '2026-09-03',
                achatCurrencyCode: 'TND',
                venteCurrencyCode: 'TND',
                achatExchangeRate: '1',
                venteExchangeRate: '1',
                totalAchatAmount: $venteAmount - 2_000,
                totalVenteAmount: $venteAmount,
                margeAgenceAmount: 1_500,
                margeDistributeurAmount: 500,
                paidAmount: 0,
            ),
        );

        return ['publicId' => $booking->publicId()->toString()];
    }

    /**
     * Déplace une ligne entre partitions RANGE (clé de partition = booking_date).
     */
    private function moveBookingDate(string $publicId, string $bookingDate): void
    {
        /** @var Connection $connection */
        $connection = static::getContainer()->get(Connection::class);

        $affected = $connection->executeStatement(
            'UPDATE booking SET booking_date = :bookingDate WHERE public_id = :publicId',
            [
                'bookingDate' => $bookingDate,
                'publicId' => $publicId,
            ],
        );

        self::assertSame(1, $affected);
    }
}
