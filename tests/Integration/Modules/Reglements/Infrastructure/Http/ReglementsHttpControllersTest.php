<?php

declare(strict_types=1);

namespace App\Tests\Integration\Modules\Reglements\Infrastructure\Http;

use App\Modules\Core\Domain\Entity\CoreCredential;
use App\Modules\Core\Domain\Repository\CoreCredentialRepositoryInterface;
use App\Modules\Core\Domain\Security\PasswordHasherInterface;
use App\Modules\Party\Domain\Entity\PartyAccount;
use App\Modules\Party\Domain\Repository\PartyAccountRepositoryInterface;
use App\Modules\Reglements\Domain\Entity\ReglementLedgerEntry;
use App\Modules\Reglements\Domain\Repository\ReglementEntryTypeRepositoryInterface;
use App\Modules\Reglements\Domain\Repository\ReglementLedgerEntryRepositoryInterface;
use App\Modules\Reglements\Domain\Repository\ReglementPaymentMethodRepositoryInterface;
use App\Modules\Reglements\Domain\ValueObject\InstrumentPartyRole;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Infrastructure\Logging\RequestIdSubscriber;
use App\Shared\Infrastructure\Persistence\UnitOfWork;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * HTTP Règlements — 6 endpoints + chaîne e2e.
 */
final class ReglementsHttpControllersTest extends WebTestCase
{
    public function test_create_instrument_returns_201_without_internal_id(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);
        $ctx = $this->seedPartyAndPaymentMethod();

        $client->request(
            'POST',
            '/api/v1/reglements/instruments',
            server: $this->jsonHeaders($token),
            content: json_encode([
                'partyAccountId' => $ctx['partyId'],
                'partyRole' => 'client',
                'currencyCode' => 'TND',
                'paymentMethodId' => $ctx['paymentMethodId'],
                'amountMinor' => 25_000,
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(201);
        self::assertTrue($client->getResponse()->headers->has(RequestIdSubscriber::HEADER_NAME));
        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayNotHasKey('id', $payload);
        self::assertArrayHasKey('publicId', $payload);
        self::assertSame(25_000, $payload['amountMinor']);
        self::assertSame('active', $payload['statusCode']);
    }

    public function test_create_instrument_malformed_returns_422(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);

        $client->request(
            'POST',
            '/api/v1/reglements/instruments',
            server: $this->jsonHeaders($token),
            content: json_encode(['partyRole' => 'client'], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(422);
    }

    public function test_create_instrument_without_jwt_returns_401(): void
    {
        $client = static::createClient();
        $client->request(
            'POST',
            '/api/v1/reglements/instruments',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ],
            content: '{}',
        );
        self::assertResponseStatusCodeSame(401);
    }

    public function test_transition_status_ok_and_unchanged_conflict(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);
        $instrument = $this->createInstrumentViaHttp($client, $token);

        $client->request(
            'PATCH',
            '/api/v1/reglements/instruments/'.$instrument['publicId'].'/status',
            server: $this->jsonHeaders($token),
            content: json_encode(['status' => 'cancelled', 'reason' => 'test'], JSON_THROW_ON_ERROR),
        );
        self::assertResponseStatusCodeSame(200);
        /** @var array{statusCode: string} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('cancelled', $payload['statusCode']);

        $client->request(
            'PATCH',
            '/api/v1/reglements/instruments/'.$instrument['publicId'].'/status',
            server: $this->jsonHeaders($token),
            content: json_encode(['status' => 'cancelled'], JSON_THROW_ON_ERROR),
        );
        self::assertResponseStatusCodeSame(409);
        /** @var array{error: array{code: string}} $err */
        $err = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('reglement_instrument.status_unchanged', $err['error']['code']);
    }

    public function test_transition_missing_instrument_returns_404(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);

        $client->request(
            'PATCH',
            '/api/v1/reglements/instruments/00000000-0000-4000-8000-000000000009/status',
            server: $this->jsonHeaders($token),
            content: json_encode(['status' => 'cancelled'], JSON_THROW_ON_ERROR),
        );
        self::assertResponseStatusCodeSame(404);
        /** @var array{error: array{code: string}} $err */
        $err = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('reglement_instrument.not_found', $err['error']['code']);
    }

    public function test_post_credit_201_and_rejects_non_active(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);
        $instrument = $this->createInstrumentViaHttp($client, $token, 12_000);

        $client->request(
            'POST',
            '/api/v1/reglements/instruments/'.$instrument['publicId'].'/credit',
            server: $this->jsonHeaders($token),
        );
        self::assertResponseStatusCodeSame(201);
        /** @var array<string, mixed> $credit */
        $credit = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayNotHasKey('id', $credit);
        self::assertSame(-12_000, $credit['amountMinor']);
        self::assertArrayHasKey('publicId', $credit);

        $other = $this->createInstrumentViaHttp($client, $token, 5_000);
        $client->request(
            'PATCH',
            '/api/v1/reglements/instruments/'.$other['publicId'].'/status',
            server: $this->jsonHeaders($token),
            content: json_encode(['status' => 'returned'], JSON_THROW_ON_ERROR),
        );
        $client->request(
            'POST',
            '/api/v1/reglements/instruments/'.$other['publicId'].'/credit',
            server: $this->jsonHeaders($token),
        );
        self::assertResponseStatusCodeSame(409);
        /** @var array{error: array{code: string}} $err */
        $err = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('reglement_instrument.not_active', $err['error']['code']);
    }

    public function test_matching_create_unmatch_and_exceeds_credit(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);
        $book = $this->seedDebitAndCreditViaApp(100_000, 30_000);

        $client->request(
            'POST',
            '/api/v1/reglements/matchings',
            server: $this->jsonHeaders($token),
            content: json_encode([
                'debitEntryPublicId' => $book['debitPublicId'],
                'creditEntryPublicId' => $book['creditPublicId'],
                'matchedAmountMinor' => 30_001,
            ], JSON_THROW_ON_ERROR),
        );
        self::assertResponseStatusCodeSame(422);
        /** @var array{error: array{code: string}} $err */
        $err = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('reglement_matching.exceeds_credit', $err['error']['code']);

        $client->request(
            'POST',
            '/api/v1/reglements/matchings',
            server: $this->jsonHeaders($token),
            content: json_encode([
                'debitEntryPublicId' => $book['debitPublicId'],
                'creditEntryPublicId' => $book['creditPublicId'],
                'matchedAmountMinor' => 20_000,
            ], JSON_THROW_ON_ERROR),
        );
        self::assertResponseStatusCodeSame(201);
        /** @var array{publicId: string, isActive: bool} $matching */
        $matching = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($matching['isActive']);
        self::assertArrayNotHasKey('id', $matching);

        $client->request(
            'DELETE',
            '/api/v1/reglements/matchings/'.$matching['publicId'],
            server: $this->jsonHeaders($token),
        );
        self::assertResponseStatusCodeSame(200);
        /** @var array{isActive: bool} $unmatched */
        $unmatched = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertFalse($unmatched['isActive']);
    }

    public function test_matching_not_found_and_malformed(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);

        $client->request(
            'DELETE',
            '/api/v1/reglements/matchings/00000000-0000-4000-8000-000000000009',
            server: $this->jsonHeaders($token),
        );
        self::assertResponseStatusCodeSame(404);

        $client->request(
            'POST',
            '/api/v1/reglements/matchings',
            server: $this->jsonHeaders($token),
            content: json_encode(['debitEntryPublicId' => '00000000-0000-4000-8000-000000000001'], JSON_THROW_ON_ERROR),
        );
        self::assertResponseStatusCodeSame(422);
    }

    public function test_balance_get_and_party_404(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);
        $book = $this->seedDebitAndCreditViaApp(50_000, 10_000);

        $client->request(
            'GET',
            '/api/v1/party-accounts/'.$book['partyPublicId'].'/reglements/balance',
            server: $this->jsonHeaders($token),
        );
        self::assertResponseStatusCodeSame(200);
        /** @var array{balances: list<array{balanceMinor: int, entryCount: int}>} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertCount(1, $payload['balances']);
        self::assertSame(40_000, $payload['balances'][0]['balanceMinor']);
        self::assertSame(2, $payload['balances'][0]['entryCount']);
        self::assertArrayNotHasKey('lastEntryId', $payload['balances'][0]);

        $client->request(
            'GET',
            '/api/v1/party-accounts/00000000-0000-4000-8000-000000000009/reglements/balance',
            server: $this->jsonHeaders($token),
        );
        self::assertResponseStatusCodeSame(404);
    }

    public function test_http_end_to_end_instrument_credit_matching_balance(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);
        $ctx = $this->seedPartyAndPaymentMethod();

        /** @var ReglementEntryTypeRepositoryInterface $entryTypes */
        $entryTypes = static::getContainer()->get(ReglementEntryTypeRepositoryInterface::class);
        $obligationType = $entryTypes->findByCode('obligation_vente');
        self::assertNotNull($obligationType);

        /** @var UnitOfWork $uow */
        $uow = static::getContainer()->get(UnitOfWork::class);
        /** @var ReglementLedgerEntryRepositoryInterface $ledger */
        $ledger = static::getContainer()->get(ReglementLedgerEntryRepositoryInterface::class);
        $debit = ReglementLedgerEntry::post(
            partyAccountId: $ctx['partyId'],
            partyRole: InstrumentPartyRole::Client,
            currencyCode: 'TND',
            entryTypeId: (int) $obligationType->id(),
            amountMinor: 100_000,
            effectiveDate: new DateTimeImmutable('today'),
            bookingId: 1,
        );
        $ledger->append($debit);
        $uow->commit();

        $client->request(
            'POST',
            '/api/v1/reglements/instruments',
            server: $this->jsonHeaders($token),
            content: json_encode([
                'partyAccountId' => $ctx['partyId'],
                'partyRole' => 'client',
                'currencyCode' => 'TND',
                'paymentMethodId' => $ctx['paymentMethodId'],
                'amountMinor' => 60_000,
            ], JSON_THROW_ON_ERROR),
        );
        self::assertResponseStatusCodeSame(201);
        /** @var array{publicId: string} $instrument */
        $instrument = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $client->request(
            'POST',
            '/api/v1/reglements/instruments/'.$instrument['publicId'].'/credit',
            server: $this->jsonHeaders($token),
        );
        self::assertResponseStatusCodeSame(201);
        /** @var array{publicId: string} $credit */
        $credit = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $client->request(
            'POST',
            '/api/v1/reglements/matchings',
            server: $this->jsonHeaders($token),
            content: json_encode([
                'debitEntryPublicId' => $debit->publicId()->toString(),
                'creditEntryPublicId' => $credit['publicId'],
                'matchedAmountMinor' => 60_000,
            ], JSON_THROW_ON_ERROR),
        );
        self::assertResponseStatusCodeSame(201);

        $client->request(
            'GET',
            '/api/v1/party-accounts/'.$ctx['partyPublicId'].'/reglements/balance?partyRole=client&currencyCode=TND',
            server: $this->jsonHeaders($token),
        );
        self::assertResponseStatusCodeSame(200);
        /** @var array{balances: list<array{balanceMinor: int, entryCount: int}>} $balance */
        $balance = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(40_000, $balance['balances'][0]['balanceMinor']);
        self::assertSame(2, $balance['balances'][0]['entryCount']);
    }

    public function test_all_reglements_http_responses_never_expose_id_or_last_entry_id(): void
    {
        $client = static::createClient();
        $token = $this->authenticate($client);
        $book = $this->seedDebitAndCreditViaApp(80_000, 50_000);
        $ctx = $this->seedPartyAndPaymentMethod();

        $responses = [];

        $client->request(
            'POST',
            '/api/v1/reglements/instruments',
            server: $this->jsonHeaders($token),
            content: json_encode([
                'partyAccountId' => $ctx['partyId'],
                'partyRole' => 'client',
                'currencyCode' => 'TND',
                'paymentMethodId' => $ctx['paymentMethodId'],
                'amountMinor' => 50_000,
            ], JSON_THROW_ON_ERROR),
        );
        self::assertResponseStatusCodeSame(201);
        /** @var array{publicId: string} $instrument */
        $instrument = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $responses[] = $instrument;

        $client->request(
            'PATCH',
            '/api/v1/reglements/instruments/'.$instrument['publicId'].'/status',
            server: $this->jsonHeaders($token),
            content: json_encode(['status' => 'cancelled', 'reason' => 'scan'], JSON_THROW_ON_ERROR),
        );
        self::assertResponseStatusCodeSame(200);
        $responses[] = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $instrument2 = $this->createInstrumentViaHttp($client, $token, 50_000);
        $client->request(
            'POST',
            '/api/v1/reglements/instruments/'.$instrument2['publicId'].'/credit',
            server: $this->jsonHeaders($token),
        );
        self::assertResponseStatusCodeSame(201);
        /** @var array{publicId: string} $creditPayload */
        $creditPayload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $responses[] = $creditPayload;

        // Credit was on a different party than $book — use book's entries for matching.
        $client->request(
            'POST',
            '/api/v1/reglements/matchings',
            server: $this->jsonHeaders($token),
            content: json_encode([
                'debitEntryPublicId' => $book['debitPublicId'],
                'creditEntryPublicId' => $book['creditPublicId'],
                'matchedAmountMinor' => 50_000,
            ], JSON_THROW_ON_ERROR),
        );
        self::assertResponseStatusCodeSame(201);
        /** @var array{publicId: string} $matching */
        $matching = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $responses[] = $matching;

        $client->request(
            'DELETE',
            '/api/v1/reglements/matchings/'.$matching['publicId'],
            server: $this->jsonHeaders($token),
        );
        self::assertResponseStatusCodeSame(200);
        $responses[] = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $client->request(
            'GET',
            '/api/v1/party-accounts/'.$book['partyPublicId'].'/reglements/balance',
            server: $this->jsonHeaders($token),
        );
        self::assertResponseStatusCodeSame(200);
        $responses[] = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        foreach ($responses as $payload) {
            self::assertIsArray($payload);
            $this->assertNoForbiddenKeys($payload);
        }
    }

    /**
     * @param array<mixed> $node
     */
    private function assertNoForbiddenKeys(array $node): void
    {
        foreach ($node as $key => $value) {
            if (is_string($key)) {
                self::assertNotSame('id', $key, 'Réponse HTTP Règlements expose une clé id');
                self::assertNotSame('lastEntryId', $key, 'Réponse HTTP Règlements expose lastEntryId');
            }
            if (is_array($value)) {
                $this->assertNoForbiddenKeys($value);
            }
        }
    }

    /**
     * @return array{CONTENT_TYPE: string, HTTP_ACCEPT: string, HTTP_AUTHORIZATION: string}
     */
    private function jsonHeaders(string $token): array
    {
        return [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
        ];
    }

    private function authenticate(KernelBrowser $client): string
    {
        $container = static::getContainer();
        /** @var UnitOfWork $unitOfWork */
        $unitOfWork = $container->get(UnitOfWork::class);
        $suffix = bin2hex(random_bytes(4));
        $email = 'http.reg.'.$suffix.'@example.com';
        $password = 'Http-Reg-'.$suffix;

        /** @var PartyAccountRepositoryInterface $accounts */
        $accounts = $container->get(PartyAccountRepositoryInterface::class);
        /** @var CoreCredentialRepositoryInterface $credentials */
        $credentials = $container->get(CoreCredentialRepositoryInterface::class);
        /** @var PasswordHasherInterface $hasher */
        $hasher = $container->get(PasswordHasherInterface::class);

        $account = PartyAccount::createPerson('Http Reg '.$suffix, Email::fromString($email));
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
     * @return array{partyId: int, partyPublicId: string, paymentMethodId: int}
     */
    private function seedPartyAndPaymentMethod(): array
    {
        $container = static::getContainer();
        /** @var UnitOfWork $unitOfWork */
        $unitOfWork = $container->get(UnitOfWork::class);
        /** @var PartyAccountRepositoryInterface $accounts */
        $accounts = $container->get(PartyAccountRepositoryInterface::class);
        /** @var ReglementPaymentMethodRepositoryInterface $methods */
        $methods = $container->get(ReglementPaymentMethodRepositoryInterface::class);

        $suffix = bin2hex(random_bytes(4));
        $party = PartyAccount::createOrganization(
            'Reg Http '.$suffix,
            Email::fromString('reg.http.'.$suffix.'@example.com'),
        );
        $accounts->save($party);
        $unitOfWork->commit();

        $method = $methods->findByCode('CB');
        self::assertNotNull($method);

        return [
            'partyId' => (int) $party->id(),
            'partyPublicId' => $party->publicId()->toString(),
            'paymentMethodId' => (int) $method->id(),
        ];
    }

    /**
     * @return array{publicId: string}
     */
    private function createInstrumentViaHttp(
        KernelBrowser $client,
        string $token,
        int $amountMinor = 25_000,
    ): array {
        $ctx = $this->seedPartyAndPaymentMethod();
        $client->request(
            'POST',
            '/api/v1/reglements/instruments',
            server: $this->jsonHeaders($token),
            content: json_encode([
                'partyAccountId' => $ctx['partyId'],
                'partyRole' => 'client',
                'currencyCode' => 'TND',
                'paymentMethodId' => $ctx['paymentMethodId'],
                'amountMinor' => $amountMinor,
            ], JSON_THROW_ON_ERROR),
        );
        self::assertResponseStatusCodeSame(201);
        /** @var array{publicId: string} $payload */
        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        return $payload;
    }

    /**
     * @return array{debitId: int, creditId: int, debitPublicId: string, creditPublicId: string, partyId: int, partyPublicId: string}
     */
    private function seedDebitAndCreditViaApp(int $debit, int $credit): array
    {
        $container = static::getContainer();
        /** @var UnitOfWork $unitOfWork */
        $unitOfWork = $container->get(UnitOfWork::class);
        /** @var PartyAccountRepositoryInterface $accounts */
        $accounts = $container->get(PartyAccountRepositoryInterface::class);
        /** @var ReglementEntryTypeRepositoryInterface $types */
        $types = $container->get(ReglementEntryTypeRepositoryInterface::class);
        /** @var ReglementLedgerEntryRepositoryInterface $ledger */
        $ledger = $container->get(ReglementLedgerEntryRepositoryInterface::class);

        $suffix = bin2hex(random_bytes(4));
        $party = PartyAccount::createOrganization(
            'Match Http '.$suffix,
            Email::fromString('match.http.'.$suffix.'@example.com'),
        );
        $accounts->save($party);
        $unitOfWork->commit();

        $obligationType = $types->findByCode('obligation_vente');
        $creditType = $types->findByCode('reglement_client');
        self::assertNotNull($obligationType);
        self::assertNotNull($creditType);

        $debitEntry = ReglementLedgerEntry::post(
            partyAccountId: (int) $party->id(),
            partyRole: InstrumentPartyRole::Client,
            currencyCode: 'TND',
            entryTypeId: (int) $obligationType->id(),
            amountMinor: $debit,
            effectiveDate: new DateTimeImmutable('today'),
            bookingId: 1,
        );
        $ledger->append($debitEntry);

        $creditEntry = ReglementLedgerEntry::post(
            partyAccountId: (int) $party->id(),
            partyRole: InstrumentPartyRole::Client,
            currencyCode: 'TND',
            entryTypeId: (int) $creditType->id(),
            amountMinor: -$credit,
            effectiveDate: new DateTimeImmutable('today'),
            invoiceId: 1,
        );
        $ledger->append($creditEntry);
        $unitOfWork->commit();

        return [
            'debitId' => (int) $debitEntry->id(),
            'creditId' => (int) $creditEntry->id(),
            'debitPublicId' => $debitEntry->publicId()->toString(),
            'creditPublicId' => $creditEntry->publicId()->toString(),
            'partyId' => (int) $party->id(),
            'partyPublicId' => $party->publicId()->toString(),
        ];
    }
}
