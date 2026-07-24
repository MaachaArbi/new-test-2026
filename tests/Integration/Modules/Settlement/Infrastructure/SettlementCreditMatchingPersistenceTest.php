<?php

declare(strict_types=1);

namespace App\Tests\Integration\Modules\Settlement\Infrastructure;

use App\Modules\Party\Domain\Entity\PartyAccount;
use App\Modules\Party\Domain\Repository\PartyAccountRepositoryInterface;
use App\Modules\Settlement\Application\CreateSettlementInstrument\CreateSettlementInstrumentCommand;
use App\Modules\Settlement\Application\CreateSettlementInstrument\CreateSettlementInstrumentHandler;
use App\Modules\Settlement\Application\CreateSettlementMatching\CreateSettlementMatchingCommand;
use App\Modules\Settlement\Application\CreateSettlementMatching\CreateSettlementMatchingHandler;
use App\Modules\Settlement\Application\PostSettlementCreditFromInstrument\PostSettlementCreditFromInstrumentCommand;
use App\Modules\Settlement\Application\PostSettlementCreditFromInstrument\PostSettlementCreditFromInstrumentHandler;
use App\Modules\Settlement\Application\SettlementReferentialValidator;
use App\Modules\Settlement\Application\TransitionSettlementInstrumentStatus\TransitionSettlementInstrumentStatusCommand;
use App\Modules\Settlement\Application\TransitionSettlementInstrumentStatus\TransitionSettlementInstrumentStatusHandler;
use App\Modules\Settlement\Application\UnmatchSettlementMatching\UnmatchSettlementMatchingCommand;
use App\Modules\Settlement\Application\UnmatchSettlementMatching\UnmatchSettlementMatchingHandler;
use App\Modules\Settlement\Domain\Entity\SettlementLedgerEntry;
use App\Modules\Settlement\Domain\Exception\SettlementInstrumentNotActiveException;
use App\Modules\Settlement\Domain\Exception\SettlementMatchingBookMismatchException;
use App\Modules\Settlement\Domain\Exception\SettlementMatchingExceedsCreditException;
use App\Modules\Settlement\Domain\Exception\SettlementMatchingExceedsDebitException;
use App\Modules\Settlement\Domain\Repository\SettlementEntryTypeRepositoryInterface;
use App\Modules\Settlement\Domain\Repository\SettlementInstrumentRepositoryInterface;
use App\Modules\Settlement\Domain\Repository\SettlementLedgerEntryRepositoryInterface;
use App\Modules\Settlement\Domain\Repository\SettlementMatchingRepositoryInterface;
use App\Modules\Settlement\Domain\Repository\SettlementPaymentMethodRepositoryInterface;
use App\Modules\Settlement\Domain\ValueObject\InstrumentPartyRole;
use App\Modules\Settlement\Infrastructure\Persistence\DoctrineSettlementMatchingRepository;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Infrastructure\Persistence\UnitOfWork;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * PostgreSQL réel — crédit instrument + lettrage.
 */
final class SettlementCreditMatchingPersistenceTest extends KernelTestCase
{
    private UnitOfWork $unitOfWork;

    private Connection $connection;

    private PartyAccountRepositoryInterface $accountRepository;

    private SettlementPaymentMethodRepositoryInterface $paymentMethodRepository;

    private SettlementEntryTypeRepositoryInterface $entryTypeRepository;

    private SettlementInstrumentRepositoryInterface $instrumentRepository;

    private SettlementLedgerEntryRepositoryInterface $ledgerRepository;

    private SettlementMatchingRepositoryInterface $matchingRepository;

    private CreateSettlementInstrumentHandler $createInstrumentHandler;

    private TransitionSettlementInstrumentStatusHandler $transitionInstrumentHandler;

    private PostSettlementCreditFromInstrumentHandler $postCreditHandler;

    private CreateSettlementMatchingHandler $createMatchingHandler;

    private UnmatchSettlementMatchingHandler $unmatchHandler;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var UnitOfWork $unitOfWork */
        $unitOfWork = $container->get(UnitOfWork::class);
        $this->unitOfWork = $unitOfWork;

        /** @var Connection $connection */
        $connection = $container->get(Connection::class);
        $this->connection = $connection;

        /** @var PartyAccountRepositoryInterface $accountRepository */
        $accountRepository = $container->get(PartyAccountRepositoryInterface::class);
        $this->accountRepository = $accountRepository;

        /** @var SettlementPaymentMethodRepositoryInterface $paymentMethodRepository */
        $paymentMethodRepository = $container->get(SettlementPaymentMethodRepositoryInterface::class);
        $this->paymentMethodRepository = $paymentMethodRepository;

        /** @var SettlementEntryTypeRepositoryInterface $entryTypeRepository */
        $entryTypeRepository = $container->get(SettlementEntryTypeRepositoryInterface::class);
        $this->entryTypeRepository = $entryTypeRepository;

        /** @var SettlementInstrumentRepositoryInterface $instrumentRepository */
        $instrumentRepository = $container->get(SettlementInstrumentRepositoryInterface::class);
        $this->instrumentRepository = $instrumentRepository;

        /** @var SettlementLedgerEntryRepositoryInterface $ledgerRepository */
        $ledgerRepository = $container->get(SettlementLedgerEntryRepositoryInterface::class);
        $this->ledgerRepository = $ledgerRepository;

        /** @var SettlementMatchingRepositoryInterface $matchingRepository */
        $matchingRepository = $container->get(SettlementMatchingRepositoryInterface::class);
        $this->matchingRepository = $matchingRepository;

        $this->createInstrumentHandler = new CreateSettlementInstrumentHandler(
            $this->instrumentRepository,
            new SettlementReferentialValidator($this->connection),
            $this->unitOfWork,
        );
        $this->transitionInstrumentHandler = new TransitionSettlementInstrumentStatusHandler(
            $this->instrumentRepository,
            $this->unitOfWork,
        );
        $this->postCreditHandler = new PostSettlementCreditFromInstrumentHandler(
            $this->instrumentRepository,
            $this->entryTypeRepository,
            $this->ledgerRepository,
            $this->unitOfWork,
        );
        $this->createMatchingHandler = new CreateSettlementMatchingHandler(
            $this->ledgerRepository,
            $this->matchingRepository,
            $this->unitOfWork,
        );
        $this->unmatchHandler = new UnmatchSettlementMatchingHandler(
            $this->matchingRepository,
            $this->unitOfWork,
        );
    }

    public function test_credit_from_client_instrument_posts_negative_customer_payment(): void
    {
        $instrumentId = $this->createInstrument('customer', 25_000);
        $entry = ($this->postCreditHandler)(new PostSettlementCreditFromInstrumentCommand($instrumentId));

        self::assertSame(-25_000, $entry->amountMinor());
        self::assertSame($instrumentId, $entry->instrumentId());

        $type = $this->entryTypeRepository->findByCode('customer_payment');
        self::assertNotNull($type);
        self::assertSame($type->id(), $entry->entryTypeId());
    }

    public function test_credit_from_fournisseur_instrument_posts_supplier_payment(): void
    {
        $instrumentId = $this->createInstrument('supplier', 8_000);
        $entry = ($this->postCreditHandler)(new PostSettlementCreditFromInstrumentCommand($instrumentId));

        self::assertSame(-8_000, $entry->amountMinor());
        $type = $this->entryTypeRepository->findByCode('supplier_payment');
        self::assertNotNull($type);
        self::assertSame($type->id(), $entry->entryTypeId());
    }

    public function test_credit_rejects_non_active_instrument(): void
    {
        $instrumentId = $this->createInstrument('customer', 5_000);
        ($this->transitionInstrumentHandler)(new TransitionSettlementInstrumentStatusCommand(
            $instrumentId,
            'cancelled',
            'test',
        ));

        try {
            ($this->postCreditHandler)(new PostSettlementCreditFromInstrumentCommand($instrumentId));
            self::fail('Expected SettlementInstrumentNotActiveException');
        } catch (SettlementInstrumentNotActiveException $exception) {
            self::assertSame('settlement_instrument.not_active', $exception->errorCode());
        }
    }

    public function test_matching_same_book_ok_and_partial_sum_equals_credit(): void
    {
        $ctx = $this->seedDebitAndCredit(debit: 100_000, credit: 100_000);

        $m1 = ($this->createMatchingHandler)(new CreateSettlementMatchingCommand(
            debitEntryId: $ctx['debitId'],
            creditEntryId: $ctx['creditId'],
            matchedAmountMinor: 60_000,
        ));
        $m2 = ($this->createMatchingHandler)(new CreateSettlementMatchingCommand(
            debitEntryId: $ctx['debitId'],
            creditEntryId: $ctx['creditId'],
            matchedAmountMinor: 40_000,
        ));

        self::assertTrue($m1->isActive());
        self::assertTrue($m2->isActive());
        self::assertSame(
            100_000,
            $this->matchingRepository->sumActiveMatchedForCreditEntry($ctx['creditId']),
        );
    }

    public function test_matching_book_mismatch_rejected(): void
    {
        $ctx = $this->seedDebitAndCredit(debit: 50_000, credit: 50_000);
        $otherCredit = $this->postStandaloneCredit('EUR', 50_000);

        try {
            ($this->createMatchingHandler)(new CreateSettlementMatchingCommand(
                debitEntryId: $ctx['debitId'],
                creditEntryId: $otherCredit,
                matchedAmountMinor: 10_000,
            ));
            self::fail('Expected SettlementMatchingBookMismatchException');
        } catch (SettlementMatchingBookMismatchException $exception) {
            self::assertSame('settlement_matching.book_mismatch', $exception->errorCode());
        }
    }

    public function test_matching_exceeds_credit_rejected(): void
    {
        $ctx = $this->seedDebitAndCredit(debit: 100_000, credit: 30_000);

        try {
            ($this->createMatchingHandler)(new CreateSettlementMatchingCommand(
                debitEntryId: $ctx['debitId'],
                creditEntryId: $ctx['creditId'],
                matchedAmountMinor: 30_001,
            ));
            self::fail('Expected SettlementMatchingExceedsCreditException');
        } catch (SettlementMatchingExceedsCreditException $exception) {
            self::assertSame('settlement_matching.exceeds_credit', $exception->errorCode());
        }
    }

    public function test_matching_exceeds_debit_rejected(): void
    {
        $ctx = $this->seedDebitAndCredit(debit: 30_000, credit: 100_000);

        try {
            ($this->createMatchingHandler)(new CreateSettlementMatchingCommand(
                debitEntryId: $ctx['debitId'],
                creditEntryId: $ctx['creditId'],
                matchedAmountMinor: 30_001,
            ));
            self::fail('Expected SettlementMatchingExceedsDebitException');
        } catch (SettlementMatchingExceedsCreditException $exception) {
            self::fail('Expected ExceedsDebit, got ExceedsCredit: '.$exception->getMessage());
        } catch (SettlementMatchingExceedsDebitException $exception) {
            self::assertSame('settlement_matching.exceeds_debit', $exception->errorCode());
            self::assertSame([
                'debit_entry_id' => $ctx['debitId'],
                'debit_capacity' => 30_000,
                'already_matched' => 0,
                'requested' => 30_001,
            ], $exception->context());
        }
    }

    public function test_matching_partial_sum_equals_debit_capacity(): void
    {
        $ctx = $this->seedDebitAndCredit(debit: 100_000, credit: 200_000);

        $m1 = ($this->createMatchingHandler)(new CreateSettlementMatchingCommand(
            debitEntryId: $ctx['debitId'],
            creditEntryId: $ctx['creditId'],
            matchedAmountMinor: 60_000,
        ));
        $m2 = ($this->createMatchingHandler)(new CreateSettlementMatchingCommand(
            debitEntryId: $ctx['debitId'],
            creditEntryId: $ctx['creditId'],
            matchedAmountMinor: 40_000,
        ));

        self::assertTrue($m1->isActive());
        self::assertTrue($m2->isActive());
        self::assertSame(
            100_000,
            $this->matchingRepository->sumActiveMatchedForDebitEntry($ctx['debitId']),
        );
    }

    public function test_unmatch_frees_capacity_for_new_matching(): void
    {
        $ctx = $this->seedDebitAndCredit(debit: 50_000, credit: 50_000);

        $first = ($this->createMatchingHandler)(new CreateSettlementMatchingCommand(
            debitEntryId: $ctx['debitId'],
            creditEntryId: $ctx['creditId'],
            matchedAmountMinor: 50_000,
        ));

        ($this->unmatchHandler)(new UnmatchSettlementMatchingCommand((int) $first->id()));

        self::assertSame(0, $this->matchingRepository->sumActiveMatchedForCreditEntry($ctx['creditId']));

        $second = ($this->createMatchingHandler)(new CreateSettlementMatchingCommand(
            debitEntryId: $ctx['debitId'],
            creditEntryId: $ctx['creditId'],
            matchedAmountMinor: 50_000,
        ));
        self::assertTrue($second->isActive());
        self::assertSame(50_000, $this->matchingRepository->sumActiveMatchedForCreditEntry($ctx['creditId']));
    }

    public function test_handlers_never_write_settlement_balance_directly(): void
    {
        foreach ([
            PostSettlementCreditFromInstrumentHandler::class,
            CreateSettlementMatchingHandler::class,
            UnmatchSettlementMatchingHandler::class,
            DoctrineSettlementMatchingRepository::class,
        ] as $class) {
            $source = (string) file_get_contents((new ReflectionClass($class))->getFileName() ?: '');
            self::assertStringNotContainsString('INSERT INTO settlement_balance', $source);
            self::assertStringNotContainsString('UPDATE settlement_balance', $source);
            self::assertStringNotContainsString('DELETE FROM settlement_balance', $source);
        }
    }

    private function createInstrument(string $partyRole, int $amountMinor): int
    {
        $partyId = $this->createOrg('CredInst');
        $method = $this->paymentMethodRepository->findByCode('CB');
        self::assertNotNull($method);

        $instrument = ($this->createInstrumentHandler)(new CreateSettlementInstrumentCommand(
            partyAccountId: $partyId,
            partyRole: $partyRole,
            currencyCode: 'TND',
            paymentMethodId: (int) $method->id(),
            amountMinor: $amountMinor,
        ));

        return (int) $instrument->id();
    }

    /**
     * @return array{debitId: int, creditId: int, partyId: int}
     */
    private function seedDebitAndCredit(int $debit, int $credit): array
    {
        $partyId = $this->createOrg('MatchBk');
        $obligationType = $this->entryTypeRepository->findByCode('customer_obligation');
        $creditType = $this->entryTypeRepository->findByCode('customer_payment');
        self::assertNotNull($obligationType);
        self::assertNotNull($creditType);

        $debitEntry = SettlementLedgerEntry::post(
            partyAccountId: $partyId,
            partyRole: InstrumentPartyRole::Customer,
            currencyCode: 'TND',
            entryTypeId: (int) $obligationType->id(),
            amountMinor: $debit,
            effectiveDate: new DateTimeImmutable('today'),
            bookingId: 1,
        );
        $this->ledgerRepository->append($debitEntry);

        $creditEntry = SettlementLedgerEntry::post(
            partyAccountId: $partyId,
            partyRole: InstrumentPartyRole::Customer,
            currencyCode: 'TND',
            entryTypeId: (int) $creditType->id(),
            amountMinor: -$credit,
            effectiveDate: new DateTimeImmutable('today'),
            invoiceId: 1,
        );
        $this->ledgerRepository->append($creditEntry);
        $this->unitOfWork->commit();

        return [
            'debitId' => (int) $debitEntry->id(),
            'creditId' => (int) $creditEntry->id(),
            'partyId' => $partyId,
        ];
    }

    private function postStandaloneCredit(string $currency, int $amount): int
    {
        $partyId = $this->createOrg('OtherBk');
        $creditType = $this->entryTypeRepository->findByCode('customer_payment');
        self::assertNotNull($creditType);

        $entry = SettlementLedgerEntry::post(
            partyAccountId: $partyId,
            partyRole: InstrumentPartyRole::Customer,
            currencyCode: $currency,
            entryTypeId: (int) $creditType->id(),
            amountMinor: -$amount,
            effectiveDate: new DateTimeImmutable('today'),
            invoiceId: 2,
        );
        $this->ledgerRepository->append($entry);
        $this->unitOfWork->commit();

        return (int) $entry->id();
    }

    private function createOrg(string $label): int
    {
        $suffix = bin2hex(random_bytes(4));
        $account = PartyAccount::createOrganization(
            $label.' '.$suffix,
            Email::fromString('cm.'.$suffix.'@example.com'),
        );
        $this->accountRepository->save($account);
        $this->unitOfWork->commit();

        return (int) $account->id();
    }
}
