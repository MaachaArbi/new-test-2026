<?php

declare(strict_types=1);

namespace App\Tests\Integration\Modules\Settlement\Infrastructure;

use App\Modules\Booking\Application\AssignBookingPayerSplit\AssignBookingPayerSplitCommand;
use App\Modules\Booking\Application\AssignBookingPayerSplit\AssignBookingPayerSplitHandler;
use App\Modules\Booking\Application\BookingReferentialValidator;
use App\Modules\Booking\Application\CreateBooking\CreateBookingCommand;
use App\Modules\Booking\Application\CreateBooking\CreateBookingHandler;
use App\Modules\Booking\Domain\Entity\BookingFolder;
use App\Modules\Booking\Domain\Repository\BookingFolderRepositoryInterface;
use App\Modules\Booking\Domain\Repository\BookingPayerSplitRepositoryInterface;
use App\Modules\Booking\Domain\Repository\BookingRepositoryInterface;
use App\Modules\Party\Domain\Entity\PartyAccount;
use App\Modules\Party\Domain\Repository\PartyAccountRepositoryInterface;
use App\Modules\Settlement\Application\PostSettlementObligationFromBooking\PostSettlementObligationFromBookingCommand;
use App\Modules\Settlement\Application\PostSettlementObligationFromBooking\PostSettlementObligationFromBookingHandler;
use App\Modules\Settlement\Application\PostSettlementTransfer\PostSettlementTransferCommand;
use App\Modules\Settlement\Application\PostSettlementTransfer\PostSettlementTransferHandler;
use App\Modules\Settlement\Application\SettlementReferentialValidator;
use App\Modules\Settlement\Domain\Repository\SettlementEntryTypeRepositoryInterface;
use App\Modules\Settlement\Domain\Repository\SettlementLedgerEntryRepositoryInterface;
use App\Modules\Settlement\Domain\ValueObject\InstrumentPartyRole;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Infrastructure\Persistence\UnitOfWork;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * PostgreSQL réel — grand livre (obligation Domain + transfert SQL + trigger).
 */
final class SettlementLedgerEntryPersistenceTest extends KernelTestCase
{
    private UnitOfWork $unitOfWork;

    private EntityManagerInterface $em;

    private Connection $connection;

    private PartyAccountRepositoryInterface $accountRepository;

    private BookingRepositoryInterface $bookingRepository;

    private BookingFolderRepositoryInterface $folderRepository;

    private BookingPayerSplitRepositoryInterface $payerSplitRepository;

    private SettlementLedgerEntryRepositoryInterface $ledgerRepository;

    private SettlementEntryTypeRepositoryInterface $entryTypeRepository;

    private CreateBookingHandler $createBookingHandler;

    private AssignBookingPayerSplitHandler $assignSplitHandler;

    private PostSettlementObligationFromBookingHandler $postObligationHandler;

    private PostSettlementTransferHandler $postTransferHandler;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var UnitOfWork $unitOfWork */
        $unitOfWork = $container->get(UnitOfWork::class);
        $this->unitOfWork = $unitOfWork;

        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $this->em = $em;

        /** @var Connection $connection */
        $connection = $container->get(Connection::class);
        $this->connection = $connection;

        /** @var PartyAccountRepositoryInterface $accountRepository */
        $accountRepository = $container->get(PartyAccountRepositoryInterface::class);
        $this->accountRepository = $accountRepository;

        /** @var BookingRepositoryInterface $bookingRepository */
        $bookingRepository = $container->get(BookingRepositoryInterface::class);
        $this->bookingRepository = $bookingRepository;

        /** @var BookingFolderRepositoryInterface $folderRepository */
        $folderRepository = $container->get(BookingFolderRepositoryInterface::class);
        $this->folderRepository = $folderRepository;

        /** @var BookingPayerSplitRepositoryInterface $payerSplitRepository */
        $payerSplitRepository = $container->get(BookingPayerSplitRepositoryInterface::class);
        $this->payerSplitRepository = $payerSplitRepository;

        /** @var SettlementLedgerEntryRepositoryInterface $ledgerRepository */
        $ledgerRepository = $container->get(SettlementLedgerEntryRepositoryInterface::class);
        $this->ledgerRepository = $ledgerRepository;

        /** @var SettlementEntryTypeRepositoryInterface $entryTypeRepository */
        $entryTypeRepository = $container->get(SettlementEntryTypeRepositoryInterface::class);
        $this->entryTypeRepository = $entryTypeRepository;

        $this->createBookingHandler = new CreateBookingHandler(
            $this->bookingRepository,
            new BookingReferentialValidator($this->connection),
            $this->unitOfWork,
        );
        $this->assignSplitHandler = new AssignBookingPayerSplitHandler(
            $this->bookingRepository,
            $this->payerSplitRepository,
            $this->unitOfWork,
        );
        $this->postObligationHandler = new PostSettlementObligationFromBookingHandler(
            $this->bookingRepository,
            $this->payerSplitRepository,
            $this->entryTypeRepository,
            $this->ledgerRepository,
            $this->unitOfWork,
        );
        $this->postTransferHandler = new PostSettlementTransferHandler(
            $this->connection,
            new SettlementReferentialValidator($this->connection),
        );
    }

    public function test_obligation_from_two_active_payer_splits(): void
    {
        $ctx = $this->seedBookingWithTwoPayers('Obl2', totalVente: 100_000, amountA: 60_000, amountB: 40_000);

        $posted = ($this->postObligationHandler)(new PostSettlementObligationFromBookingCommand(
            bookingId: $ctx['bookingId'],
        ));

        self::assertCount(2, $posted);
        $this->em->clear();

        $entries = $this->ledgerRepository->findByBookingId($ctx['bookingId']);
        self::assertCount(2, $entries);

        $amountsByPayer = [];
        foreach ($entries as $entry) {
            self::assertSame($ctx['bookingId'], $entry->bookingId());
            self::assertSame('TND', $entry->currencyCode());
            self::assertSame(InstrumentPartyRole::Customer, $entry->partyRole());
            self::assertGreaterThan(0, $entry->amountMinor());
            $amountsByPayer[$entry->partyAccountId()] = $entry->amountMinor();
        }

        self::assertSame(60_000, $amountsByPayer[$ctx['payerAId']]);
        self::assertSame(40_000, $amountsByPayer[$ctx['payerBId']]);

        $sumA = $this->ledgerRepository->sumActiveByBook(
            $ctx['payerAId'],
            InstrumentPartyRole::Customer,
            'TND',
        );
        $sumB = $this->ledgerRepository->sumActiveByBook(
            $ctx['payerBId'],
            InstrumentPartyRole::Customer,
            'TND',
        );
        self::assertSame(60_000, $sumA);
        self::assertSame(40_000, $sumB);
    }

    public function test_transfer_creates_transfer_row_and_two_legs_atomically(): void
    {
        $source = $this->createOrg('XferSrc');
        $target = $this->createOrg('XferTgt');

        $transferId = ($this->postTransferHandler)(new PostSettlementTransferCommand(
            sourceAccountId: $source,
            sourceRole: 'customer',
            targetAccountId: $target,
            targetRole: 'customer',
            currencyCode: 'TND',
            amountMinor: 15_000,
            effectiveDate: '2026-07-22',
            reason: 'report solde employé',
        ));

        self::assertGreaterThan(0, $transferId);

        $transfer = $this->connection->fetchAssociative(
            'SELECT source_account_id, target_account_id, currency_code, amount_minor, reason
             FROM settlement_transfer WHERE id = :id',
            ['id' => $transferId],
        );
        self::assertIsArray($transfer);
        self::assertTrue(is_numeric($transfer['source_account_id']));
        self::assertTrue(is_numeric($transfer['target_account_id']));
        self::assertTrue(is_numeric($transfer['amount_minor']));
        self::assertSame($source, (int) $transfer['source_account_id']);
        self::assertSame($target, (int) $transfer['target_account_id']);
        self::assertSame('TND', $transfer['currency_code']);
        self::assertSame(15_000, (int) $transfer['amount_minor']);
        self::assertSame('report solde employé', $transfer['reason']);

        $legs = $this->connection->fetchAllAssociative(
            'SELECT party_account_id, amount_minor, transfer_id
             FROM settlement_ledger_entry
             WHERE transfer_id = :id
             ORDER BY amount_minor ASC',
            ['id' => $transferId],
        );
        self::assertCount(2, $legs);
        self::assertTrue(is_numeric($legs[0]['party_account_id']));
        self::assertTrue(is_numeric($legs[0]['amount_minor']));
        self::assertTrue(is_numeric($legs[0]['transfer_id']));
        self::assertTrue(is_numeric($legs[1]['party_account_id']));
        self::assertTrue(is_numeric($legs[1]['amount_minor']));
        self::assertTrue(is_numeric($legs[1]['transfer_id']));
        self::assertSame($source, (int) $legs[0]['party_account_id']);
        self::assertSame(-15_000, (int) $legs[0]['amount_minor']);
        self::assertSame($target, (int) $legs[1]['party_account_id']);
        self::assertSame(15_000, (int) $legs[1]['amount_minor']);
        self::assertSame($transferId, (int) $legs[0]['transfer_id']);
        self::assertSame($transferId, (int) $legs[1]['transfer_id']);
    }

    public function test_ledger_append_only_trigger_rejects_update(): void
    {
        $partyId = $this->createOrg('TrigUpd');
        $entryType = $this->entryTypeRepository->findByCode('customer_obligation');
        self::assertNotNull($entryType);

        $this->connection->executeStatement(
            'INSERT INTO settlement_ledger_entry
                (party_account_id, party_role, currency_code, entry_type_id,
                 amount_minor, effective_date, booking_id, memo)
             VALUES
                (:party, \'customer\', \'TND\', :type_id, 1000, CURRENT_DATE, 1, \'trigger probe\')',
            [
                'party' => $partyId,
                'type_id' => (int) $entryType->id(),
            ],
        );

        $idRaw = $this->connection->fetchOne(
            'SELECT id FROM settlement_ledger_entry
             WHERE party_account_id = :party AND memo = \'trigger probe\'
             ORDER BY id DESC LIMIT 1',
            ['party' => $partyId],
        );
        self::assertTrue(is_numeric($idRaw));
        $id = (int) $idRaw;
        self::assertGreaterThan(0, $id);

        try {
            $this->connection->executeStatement(
                'UPDATE settlement_ledger_entry SET memo = :memo WHERE id = :id',
                ['memo' => 'mutated', 'id' => $id],
            );
            self::fail('Expected DBAL exception from append-only trigger');
        } catch (DbalException $exception) {
            self::assertStringContainsString('append-only', $exception->getMessage());
            self::assertStringContainsString((string) $id, $exception->getMessage());
        }

        $memo = $this->connection->fetchOne(
            'SELECT memo FROM settlement_ledger_entry WHERE id = :id',
            ['id' => $id],
        );
        self::assertSame('trigger probe', $memo);
    }

    /**
     * @return array{bookingId: int, payerAId: int, payerBId: int}
     */
    private function seedBookingWithTwoPayers(
        string $label,
        int $totalVente,
        int $amountA,
        int $amountB,
    ): array {
        $suffix = bin2hex(random_bytes(4));
        $customer = PartyAccount::createOrganization(
            $label.' Cust '.$suffix,
            Email::fromString('led.cust.'.$suffix.'@example.com'),
        );
        $office = PartyAccount::createOrganization(
            $label.' Off '.$suffix,
            Email::fromString('led.off.'.$suffix.'@example.com'),
        );
        $payerA = PartyAccount::createOrganization(
            $label.' PayA '.$suffix,
            Email::fromString('led.paya.'.$suffix.'@example.com'),
        );
        $payerB = PartyAccount::createOrganization(
            $label.' PayB '.$suffix,
            Email::fromString('led.payb.'.$suffix.'@example.com'),
        );

        foreach ([$customer, $office, $payerA, $payerB] as $account) {
            $this->accountRepository->save($account);
            $this->unitOfWork->commit();
        }

        $folder = BookingFolder::create(
            'LED-'.$suffix,
            (int) $customer->id(),
            (int) $office->id(),
        );
        $this->folderRepository->save($folder);
        $this->unitOfWork->commit();

        $booking = ($this->createBookingHandler)(new CreateBookingCommand(
            folderId: (int) $folder->id(),
            serviceTypeCode: 'hotel',
            statusCode: 'confirmed',
            customerAccountId: (int) $customer->id(),
            supplierAccountId: null,
            officeAccountId: (int) $office->id(),
            startDate: '2026-09-01',
            endDate: '2026-09-03',
            achatCurrencyCode: 'TND',
            venteCurrencyCode: 'TND',
            achatExchangeRate: '1',
            venteExchangeRate: '1',
            totalAchatAmount: 0,
            totalVenteAmount: $totalVente,
            margeAgenceAmount: 0,
            margeDistributeurAmount: 0,
            paidAmount: 0,
        ));

        $bookingId = (int) $booking->id();

        ($this->assignSplitHandler)(new AssignBookingPayerSplitCommand(
            bookingId: $bookingId,
            payerAccountId: (int) $payerA->id(),
            amountMinor: $amountA,
            currencyCode: 'TND',
        ));
        ($this->assignSplitHandler)(new AssignBookingPayerSplitCommand(
            bookingId: $bookingId,
            payerAccountId: (int) $payerB->id(),
            amountMinor: $amountB,
            currencyCode: 'TND',
        ));

        return [
            'bookingId' => $bookingId,
            'payerAId' => (int) $payerA->id(),
            'payerBId' => (int) $payerB->id(),
        ];
    }

    private function createOrg(string $label): int
    {
        $suffix = bin2hex(random_bytes(4));
        $account = PartyAccount::createOrganization(
            $label.' '.$suffix,
            Email::fromString('led.'.$suffix.'@example.com'),
        );
        $this->accountRepository->save($account);
        $this->unitOfWork->commit();

        return (int) $account->id();
    }
}
