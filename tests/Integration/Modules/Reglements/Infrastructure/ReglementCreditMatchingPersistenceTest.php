<?php

declare(strict_types=1);

namespace App\Tests\Integration\Modules\Reglements\Infrastructure;

use App\Modules\Party\Domain\Entity\PartyAccount;
use App\Modules\Party\Domain\Repository\PartyAccountRepositoryInterface;
use App\Modules\Reglements\Application\CreateReglementInstrument\CreateReglementInstrumentCommand;
use App\Modules\Reglements\Application\CreateReglementInstrument\CreateReglementInstrumentHandler;
use App\Modules\Reglements\Application\CreateReglementMatching\CreateReglementMatchingCommand;
use App\Modules\Reglements\Application\CreateReglementMatching\CreateReglementMatchingHandler;
use App\Modules\Reglements\Application\PostReglementCreditFromInstrument\PostReglementCreditFromInstrumentCommand;
use App\Modules\Reglements\Application\PostReglementCreditFromInstrument\PostReglementCreditFromInstrumentHandler;
use App\Modules\Reglements\Application\ReglementReferentialValidator;
use App\Modules\Reglements\Application\TransitionReglementInstrumentStatus\TransitionReglementInstrumentStatusCommand;
use App\Modules\Reglements\Application\TransitionReglementInstrumentStatus\TransitionReglementInstrumentStatusHandler;
use App\Modules\Reglements\Application\UnmatchReglementMatching\UnmatchReglementMatchingCommand;
use App\Modules\Reglements\Application\UnmatchReglementMatching\UnmatchReglementMatchingHandler;
use App\Modules\Reglements\Domain\Entity\ReglementLedgerEntry;
use App\Modules\Reglements\Domain\Exception\ReglementInstrumentNotActiveException;
use App\Modules\Reglements\Domain\Exception\ReglementMatchingBookMismatchException;
use App\Modules\Reglements\Domain\Exception\ReglementMatchingExceedsCreditException;
use App\Modules\Reglements\Domain\Exception\ReglementMatchingExceedsDebitException;
use App\Modules\Reglements\Domain\Repository\ReglementEntryTypeRepositoryInterface;
use App\Modules\Reglements\Domain\Repository\ReglementInstrumentRepositoryInterface;
use App\Modules\Reglements\Domain\Repository\ReglementLedgerEntryRepositoryInterface;
use App\Modules\Reglements\Domain\Repository\ReglementMatchingRepositoryInterface;
use App\Modules\Reglements\Domain\Repository\ReglementPaymentMethodRepositoryInterface;
use App\Modules\Reglements\Domain\ValueObject\InstrumentPartyRole;
use App\Modules\Reglements\Infrastructure\Persistence\DoctrineReglementMatchingRepository;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Infrastructure\Persistence\UnitOfWork;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * PostgreSQL réel — crédit instrument + lettrage.
 */
final class ReglementCreditMatchingPersistenceTest extends KernelTestCase
{
    private UnitOfWork $unitOfWork;

    private Connection $connection;

    private PartyAccountRepositoryInterface $accountRepository;

    private ReglementPaymentMethodRepositoryInterface $paymentMethodRepository;

    private ReglementEntryTypeRepositoryInterface $entryTypeRepository;

    private ReglementInstrumentRepositoryInterface $instrumentRepository;

    private ReglementLedgerEntryRepositoryInterface $ledgerRepository;

    private ReglementMatchingRepositoryInterface $matchingRepository;

    private CreateReglementInstrumentHandler $createInstrumentHandler;

    private TransitionReglementInstrumentStatusHandler $transitionInstrumentHandler;

    private PostReglementCreditFromInstrumentHandler $postCreditHandler;

    private CreateReglementMatchingHandler $createMatchingHandler;

    private UnmatchReglementMatchingHandler $unmatchHandler;

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

        /** @var ReglementPaymentMethodRepositoryInterface $paymentMethodRepository */
        $paymentMethodRepository = $container->get(ReglementPaymentMethodRepositoryInterface::class);
        $this->paymentMethodRepository = $paymentMethodRepository;

        /** @var ReglementEntryTypeRepositoryInterface $entryTypeRepository */
        $entryTypeRepository = $container->get(ReglementEntryTypeRepositoryInterface::class);
        $this->entryTypeRepository = $entryTypeRepository;

        /** @var ReglementInstrumentRepositoryInterface $instrumentRepository */
        $instrumentRepository = $container->get(ReglementInstrumentRepositoryInterface::class);
        $this->instrumentRepository = $instrumentRepository;

        /** @var ReglementLedgerEntryRepositoryInterface $ledgerRepository */
        $ledgerRepository = $container->get(ReglementLedgerEntryRepositoryInterface::class);
        $this->ledgerRepository = $ledgerRepository;

        /** @var ReglementMatchingRepositoryInterface $matchingRepository */
        $matchingRepository = $container->get(ReglementMatchingRepositoryInterface::class);
        $this->matchingRepository = $matchingRepository;

        $this->createInstrumentHandler = new CreateReglementInstrumentHandler(
            $this->instrumentRepository,
            new ReglementReferentialValidator($this->connection),
            $this->unitOfWork,
        );
        $this->transitionInstrumentHandler = new TransitionReglementInstrumentStatusHandler(
            $this->instrumentRepository,
            $this->unitOfWork,
        );
        $this->postCreditHandler = new PostReglementCreditFromInstrumentHandler(
            $this->instrumentRepository,
            $this->entryTypeRepository,
            $this->ledgerRepository,
            $this->unitOfWork,
        );
        $this->createMatchingHandler = new CreateReglementMatchingHandler(
            $this->ledgerRepository,
            $this->matchingRepository,
            $this->unitOfWork,
        );
        $this->unmatchHandler = new UnmatchReglementMatchingHandler(
            $this->matchingRepository,
            $this->unitOfWork,
        );
    }

    public function test_credit_from_client_instrument_posts_negative_reglement_client(): void
    {
        $instrumentId = $this->createInstrument('client', 25_000);
        $entry = ($this->postCreditHandler)(new PostReglementCreditFromInstrumentCommand($instrumentId));

        self::assertSame(-25_000, $entry->amountMinor());
        self::assertSame($instrumentId, $entry->instrumentId());

        $type = $this->entryTypeRepository->findByCode('reglement_client');
        self::assertNotNull($type);
        self::assertSame($type->id(), $entry->entryTypeId());
    }

    public function test_credit_from_fournisseur_instrument_posts_reglement_fournisseur(): void
    {
        $instrumentId = $this->createInstrument('fournisseur', 8_000);
        $entry = ($this->postCreditHandler)(new PostReglementCreditFromInstrumentCommand($instrumentId));

        self::assertSame(-8_000, $entry->amountMinor());
        $type = $this->entryTypeRepository->findByCode('reglement_fournisseur');
        self::assertNotNull($type);
        self::assertSame($type->id(), $entry->entryTypeId());
    }

    public function test_credit_rejects_non_active_instrument(): void
    {
        $instrumentId = $this->createInstrument('client', 5_000);
        ($this->transitionInstrumentHandler)(new TransitionReglementInstrumentStatusCommand(
            $instrumentId,
            'cancelled',
            'test',
        ));

        try {
            ($this->postCreditHandler)(new PostReglementCreditFromInstrumentCommand($instrumentId));
            self::fail('Expected ReglementInstrumentNotActiveException');
        } catch (ReglementInstrumentNotActiveException $exception) {
            self::assertSame('reglement_instrument.not_active', $exception->errorCode());
        }
    }

    public function test_matching_same_book_ok_and_partial_sum_equals_credit(): void
    {
        $ctx = $this->seedDebitAndCredit(debit: 100_000, credit: 100_000);

        $m1 = ($this->createMatchingHandler)(new CreateReglementMatchingCommand(
            debitEntryId: $ctx['debitId'],
            creditEntryId: $ctx['creditId'],
            matchedAmountMinor: 60_000,
        ));
        $m2 = ($this->createMatchingHandler)(new CreateReglementMatchingCommand(
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
            ($this->createMatchingHandler)(new CreateReglementMatchingCommand(
                debitEntryId: $ctx['debitId'],
                creditEntryId: $otherCredit,
                matchedAmountMinor: 10_000,
            ));
            self::fail('Expected ReglementMatchingBookMismatchException');
        } catch (ReglementMatchingBookMismatchException $exception) {
            self::assertSame('reglement_matching.book_mismatch', $exception->errorCode());
        }
    }

    public function test_matching_exceeds_credit_rejected(): void
    {
        $ctx = $this->seedDebitAndCredit(debit: 100_000, credit: 30_000);

        try {
            ($this->createMatchingHandler)(new CreateReglementMatchingCommand(
                debitEntryId: $ctx['debitId'],
                creditEntryId: $ctx['creditId'],
                matchedAmountMinor: 30_001,
            ));
            self::fail('Expected ReglementMatchingExceedsCreditException');
        } catch (ReglementMatchingExceedsCreditException $exception) {
            self::assertSame('reglement_matching.exceeds_credit', $exception->errorCode());
        }
    }

    public function test_matching_exceeds_debit_rejected(): void
    {
        $ctx = $this->seedDebitAndCredit(debit: 30_000, credit: 100_000);

        try {
            ($this->createMatchingHandler)(new CreateReglementMatchingCommand(
                debitEntryId: $ctx['debitId'],
                creditEntryId: $ctx['creditId'],
                matchedAmountMinor: 30_001,
            ));
            self::fail('Expected ReglementMatchingExceedsDebitException');
        } catch (ReglementMatchingExceedsCreditException $exception) {
            self::fail('Expected ExceedsDebit, got ExceedsCredit: '.$exception->getMessage());
        } catch (ReglementMatchingExceedsDebitException $exception) {
            self::assertSame('reglement_matching.exceeds_debit', $exception->errorCode());
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

        $m1 = ($this->createMatchingHandler)(new CreateReglementMatchingCommand(
            debitEntryId: $ctx['debitId'],
            creditEntryId: $ctx['creditId'],
            matchedAmountMinor: 60_000,
        ));
        $m2 = ($this->createMatchingHandler)(new CreateReglementMatchingCommand(
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

        $first = ($this->createMatchingHandler)(new CreateReglementMatchingCommand(
            debitEntryId: $ctx['debitId'],
            creditEntryId: $ctx['creditId'],
            matchedAmountMinor: 50_000,
        ));

        ($this->unmatchHandler)(new UnmatchReglementMatchingCommand((int) $first->id()));

        self::assertSame(0, $this->matchingRepository->sumActiveMatchedForCreditEntry($ctx['creditId']));

        $second = ($this->createMatchingHandler)(new CreateReglementMatchingCommand(
            debitEntryId: $ctx['debitId'],
            creditEntryId: $ctx['creditId'],
            matchedAmountMinor: 50_000,
        ));
        self::assertTrue($second->isActive());
        self::assertSame(50_000, $this->matchingRepository->sumActiveMatchedForCreditEntry($ctx['creditId']));
    }

    public function test_handlers_never_write_reglement_balance_directly(): void
    {
        foreach ([
            PostReglementCreditFromInstrumentHandler::class,
            CreateReglementMatchingHandler::class,
            UnmatchReglementMatchingHandler::class,
            DoctrineReglementMatchingRepository::class,
        ] as $class) {
            $source = (string) file_get_contents((new ReflectionClass($class))->getFileName() ?: '');
            self::assertStringNotContainsString('INSERT INTO reglement_balance', $source);
            self::assertStringNotContainsString('UPDATE reglement_balance', $source);
            self::assertStringNotContainsString('DELETE FROM reglement_balance', $source);
        }
    }

    private function createInstrument(string $partyRole, int $amountMinor): int
    {
        $partyId = $this->createOrg('CredInst');
        $method = $this->paymentMethodRepository->findByCode('CB');
        self::assertNotNull($method);

        $instrument = ($this->createInstrumentHandler)(new CreateReglementInstrumentCommand(
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
        $obligationType = $this->entryTypeRepository->findByCode('obligation_vente');
        $creditType = $this->entryTypeRepository->findByCode('reglement_client');
        self::assertNotNull($obligationType);
        self::assertNotNull($creditType);

        $debitEntry = ReglementLedgerEntry::post(
            partyAccountId: $partyId,
            partyRole: InstrumentPartyRole::Client,
            currencyCode: 'TND',
            entryTypeId: (int) $obligationType->id(),
            amountMinor: $debit,
            effectiveDate: new DateTimeImmutable('today'),
            bookingId: 1,
        );
        $this->ledgerRepository->append($debitEntry);

        $creditEntry = ReglementLedgerEntry::post(
            partyAccountId: $partyId,
            partyRole: InstrumentPartyRole::Client,
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
        $creditType = $this->entryTypeRepository->findByCode('reglement_client');
        self::assertNotNull($creditType);

        $entry = ReglementLedgerEntry::post(
            partyAccountId: $partyId,
            partyRole: InstrumentPartyRole::Client,
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
