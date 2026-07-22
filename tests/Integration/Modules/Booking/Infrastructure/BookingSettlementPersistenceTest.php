<?php

declare(strict_types=1);

namespace App\Tests\Integration\Modules\Booking\Infrastructure;

use App\Modules\Booking\Application\AssignBookingSettlement\AssignBookingSettlementCommand;
use App\Modules\Booking\Application\AssignBookingSettlement\AssignBookingSettlementHandler;
use App\Modules\Booking\Application\CreateBooking\CreateBookingCommand;
use App\Modules\Booking\Application\CreateBooking\CreateBookingHandler;
use App\Modules\Booking\Application\BookingReferentialValidator;
use App\Modules\Booking\Application\RevokeBookingSettlement\RevokeBookingSettlementCommand;
use App\Modules\Booking\Application\RevokeBookingSettlement\RevokeBookingSettlementHandler;
use App\Modules\Booking\Domain\Entity\BookingFolder;
use App\Modules\Booking\Domain\Exception\BookingSettlementAlreadyActiveException;
use App\Modules\Booking\Domain\Repository\BookingFolderRepositoryInterface;
use App\Modules\Booking\Domain\Repository\BookingRepositoryInterface;
use App\Modules\Booking\Domain\Repository\BookingSettlementRepositoryInterface;
use App\Modules\Booking\Domain\ValueObject\BeneficiaryRole;
use App\Modules\Party\Domain\Entity\PartyAccount;
use App\Modules\Party\Domain\Repository\PartyAccountRepositoryInterface;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Infrastructure\Persistence\UnitOfWork;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * PostgreSQL réel — booking_settlement (pas de recalcul Booking).
 */
final class BookingSettlementPersistenceTest extends KernelTestCase
{
    private UnitOfWork $unitOfWork;

    private EntityManagerInterface $em;

    private Connection $connection;

    private BookingRepositoryInterface $bookingRepository;

    private BookingFolderRepositoryInterface $folderRepository;

    private PartyAccountRepositoryInterface $accountRepository;

    private BookingSettlementRepositoryInterface $settlementRepository;

    private AssignBookingSettlementHandler $assignHandler;

    private RevokeBookingSettlementHandler $revokeHandler;

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

        /** @var BookingRepositoryInterface $bookingRepository */
        $bookingRepository = $container->get(BookingRepositoryInterface::class);
        $this->bookingRepository = $bookingRepository;

        /** @var BookingFolderRepositoryInterface $folderRepository */
        $folderRepository = $container->get(BookingFolderRepositoryInterface::class);
        $this->folderRepository = $folderRepository;

        /** @var PartyAccountRepositoryInterface $accountRepository */
        $accountRepository = $container->get(PartyAccountRepositoryInterface::class);
        $this->accountRepository = $accountRepository;

        /** @var BookingSettlementRepositoryInterface $settlementRepository */
        $settlementRepository = $container->get(BookingSettlementRepositoryInterface::class);
        $this->settlementRepository = $settlementRepository;

        $this->assignHandler = new AssignBookingSettlementHandler(
            $this->settlementRepository,
            $this->unitOfWork,
        );
        $this->revokeHandler = new RevokeBookingSettlementHandler(
            $this->settlementRepository,
            $this->unitOfWork,
        );
    }

    public function test_assign_round_trip_persists_all_mapped_fields(): void
    {
        [$bookingId, $beneficiaryId] = $this->seedBookingAndBeneficiary('SettRT');

        $settlement = ($this->assignHandler)(new AssignBookingSettlementCommand(
            bookingId: $bookingId,
            beneficiaryAccountId: $beneficiaryId,
            beneficiaryRole: BeneficiaryRole::Distributeur,
            amountOwedMinor: 151_011,
            currencyCode: 'TND',
            amountSettledDirectMinor: 1_000,
            rate: '50.500',
            resalePriceAmountMinor: 181_213,
            createdBy: null,
        ));

        $id = $settlement->id();
        self::assertNotNull($id);
        $validFrom = $settlement->validFrom();

        $this->em->clear();

        $reloaded = $this->settlementRepository->findById($id);
        self::assertNotNull($reloaded);
        self::assertSame($id, $reloaded->id());
        self::assertSame($bookingId, $reloaded->bookingId());
        self::assertSame($beneficiaryId, $reloaded->beneficiaryAccountId());
        self::assertSame(BeneficiaryRole::Distributeur, $reloaded->beneficiaryRole());
        self::assertSame(151_011, $reloaded->amountOwed()->amount());
        self::assertSame(1_000, $reloaded->amountSettledDirect()->amount());
        self::assertSame('50.500', $reloaded->rate()?->toString());
        self::assertSame(181_213, $reloaded->resalePriceAmount()?->amount());
        self::assertSame('TND', $reloaded->currencyCode());
        self::assertTrue($reloaded->isActive());
        self::assertNull($reloaded->validTo());
        self::assertSame(
            $validFrom->format('Y-m-d H:i:s'),
            $reloaded->validFrom()->format('Y-m-d H:i:s'),
        );
    }

    public function test_duplicate_active_triplet_is_rejected_before_sql(): void
    {
        [$bookingId, $beneficiaryId] = $this->seedBookingAndBeneficiary('SettDup');

        ($this->assignHandler)(new AssignBookingSettlementCommand(
            bookingId: $bookingId,
            beneficiaryAccountId: $beneficiaryId,
            beneficiaryRole: BeneficiaryRole::Fournisseur,
            amountOwedMinor: 10_000,
            currencyCode: 'TND',
        ));

        try {
            ($this->assignHandler)(new AssignBookingSettlementCommand(
                bookingId: $bookingId,
                beneficiaryAccountId: $beneficiaryId,
                beneficiaryRole: BeneficiaryRole::Fournisseur,
                amountOwedMinor: 20_000,
                currencyCode: 'TND',
            ));
            self::fail('Expected BookingSettlementAlreadyActiveException');
        } catch (BookingSettlementAlreadyActiveException $exception) {
            self::assertSame('booking_settlement.already_active', $exception->errorCode());
        }

        self::assertCount(1, $this->settlementRepository->findByBookingId($bookingId));
    }

    public function test_different_roles_same_booking_and_account_coexist(): void
    {
        [$bookingId, $beneficiaryId] = $this->seedBookingAndBeneficiary('SettRoles');

        ($this->assignHandler)(new AssignBookingSettlementCommand(
            bookingId: $bookingId,
            beneficiaryAccountId: $beneficiaryId,
            beneficiaryRole: BeneficiaryRole::AgencePrincipale,
            amountOwedMinor: 5_000,
            currencyCode: 'TND',
        ));
        ($this->assignHandler)(new AssignBookingSettlementCommand(
            bookingId: $bookingId,
            beneficiaryAccountId: $beneficiaryId,
            beneficiaryRole: BeneficiaryRole::Distributeur,
            amountOwedMinor: 3_000,
            currencyCode: 'TND',
        ));

        $actives = $this->settlementRepository->findByBookingId($bookingId);
        self::assertCount(2, $actives);
        $roles = array_map(
            static fn ($s) => $s->beneficiaryRole()->value,
            $actives,
        );
        sort($roles);
        self::assertSame(['agence_principale', 'distributeur'], $roles);
    }

    public function test_revoke_persists_valid_to_and_allows_reassign(): void
    {
        [$bookingId, $beneficiaryId] = $this->seedBookingAndBeneficiary('SettRev');

        $first = ($this->assignHandler)(new AssignBookingSettlementCommand(
            bookingId: $bookingId,
            beneficiaryAccountId: $beneficiaryId,
            beneficiaryRole: BeneficiaryRole::Distributeur,
            amountOwedMinor: 8_000,
            currencyCode: 'TND',
        ));
        $firstId = (int) $first->id();

        $revoked = ($this->revokeHandler)(new RevokeBookingSettlementCommand($firstId));
        self::assertFalse($revoked->isActive());
        self::assertNotNull($revoked->validTo());

        $this->em->clear();

        $reloaded = $this->settlementRepository->findById($firstId);
        self::assertNotNull($reloaded);
        self::assertNotNull($reloaded->validTo());
        self::assertCount(0, $this->settlementRepository->findByBookingId($bookingId));
        self::assertCount(1, $this->settlementRepository->findByBookingId($bookingId, activeOnly: false));

        $second = ($this->assignHandler)(new AssignBookingSettlementCommand(
            bookingId: $bookingId,
            beneficiaryAccountId: $beneficiaryId,
            beneficiaryRole: BeneficiaryRole::Distributeur,
            amountOwedMinor: 9_000,
            currencyCode: 'TND',
        ));
        self::assertNotSame($firstId, $second->id());
        self::assertTrue($second->isActive());
    }

    public function test_assign_does_not_mutate_booking_totals(): void
    {
        [$bookingId, $beneficiaryId] = $this->seedBookingAndBeneficiary('SettNoRecalc', totalAchat: 100_000, totalVente: 150_000);

        $before = $this->bookingRepository->findById($bookingId);
        self::assertNotNull($before);
        $achatBefore = $before->totalAchatAmount()->amount();
        $venteBefore = $before->totalVenteAmount()->amount();
        $margeAgenceBefore = $before->margeAgenceAmount()->amount();
        $margeDistrBefore = $before->margeDistributeurAmount()->amount();
        $paidBefore = $before->paidAmount()->amount();

        ($this->assignHandler)(new AssignBookingSettlementCommand(
            bookingId: $bookingId,
            beneficiaryAccountId: $beneficiaryId,
            beneficiaryRole: BeneficiaryRole::Distributeur,
            amountOwedMinor: 25_000,
            currencyCode: 'TND',
            resalePriceAmountMinor: 40_000,
        ));

        $this->em->clear();

        $after = $this->bookingRepository->findById($bookingId);
        self::assertNotNull($after);
        self::assertSame($achatBefore, $after->totalAchatAmount()->amount());
        self::assertSame($venteBefore, $after->totalVenteAmount()->amount());
        self::assertSame($margeAgenceBefore, $after->margeAgenceAmount()->amount());
        self::assertSame($margeDistrBefore, $after->margeDistributeurAmount()->amount());
        self::assertSame($paidBefore, $after->paidAmount()->amount());
    }

    public function test_handler_has_no_booking_repository_dependency(): void
    {
        $params = (new ReflectionClass(AssignBookingSettlementHandler::class))
            ->getConstructor()
            ?->getParameters() ?? [];

        $typeNames = [];
        foreach ($params as $param) {
            $type = $param->getType();
            if ($type instanceof \ReflectionNamedType) {
                $typeNames[] = $type->getName();
            }
        }

        self::assertNotContains(BookingRepositoryInterface::class, $typeNames);
        self::assertContains(BookingSettlementRepositoryInterface::class, $typeNames);
    }

    public function test_resale_price_is_not_summed_across_settlements(): void
    {
        [$bookingId, $beneficiaryA] = $this->seedBookingAndBeneficiary('SettResaleA');
        $beneficiaryB = PartyAccount::createOrganization(
            'SettResaleB '.bin2hex(random_bytes(3)),
            Email::fromString('sett.resale.b.'.bin2hex(random_bytes(3)).'@example.com'),
        );
        $this->accountRepository->save($beneficiaryB);
        $this->unitOfWork->commit();

        ($this->assignHandler)(new AssignBookingSettlementCommand(
            bookingId: $bookingId,
            beneficiaryAccountId: $beneficiaryA,
            beneficiaryRole: BeneficiaryRole::Distributeur,
            amountOwedMinor: 1_000,
            currencyCode: 'TND',
            resalePriceAmountMinor: 10_000,
        ));
        ($this->assignHandler)(new AssignBookingSettlementCommand(
            bookingId: $bookingId,
            beneficiaryAccountId: (int) $beneficiaryB->id(),
            beneficiaryRole: BeneficiaryRole::Distributeur,
            amountOwedMinor: 2_000,
            currencyCode: 'TND',
            resalePriceAmountMinor: 20_000,
        ));

        // Structurel : aucune API Domain/Application n'agrège resale_price_amount.
        // Preuve SQL : pas de colonne booking dérivée ; totaux booking inchangés.
        $sumResaleRaw = $this->connection->fetchOne(
            'SELECT COALESCE(SUM(resale_price_amount), 0) FROM booking_settlement WHERE booking_id = :id AND valid_to IS NULL',
            ['id' => $bookingId],
        );
        self::assertIsNumeric($sumResaleRaw);
        self::assertSame(30_000, (int) $sumResaleRaw);

        $booking = $this->bookingRepository->findById($bookingId);
        self::assertNotNull($booking);
        self::assertSame(0, $booking->totalVenteAmount()->amount());
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function seedBookingAndBeneficiary(
        string $label,
        int $totalAchat = 0,
        int $totalVente = 0,
    ): array {
        $suffix = bin2hex(random_bytes(4));
        $customer = PartyAccount::createOrganization(
            $label.' Cust '.$suffix,
            Email::fromString(strtolower($label).'.cust.'.$suffix.'@example.com'),
        );
        $office = PartyAccount::createOrganization(
            $label.' Off '.$suffix,
            Email::fromString(strtolower($label).'.off.'.$suffix.'@example.com'),
        );
        $beneficiary = PartyAccount::createOrganization(
            $label.' Ben '.$suffix,
            Email::fromString(strtolower($label).'.ben.'.$suffix.'@example.com'),
        );
        $this->accountRepository->save($customer);
        $this->accountRepository->save($office);
        $this->accountRepository->save($beneficiary);
        $this->unitOfWork->commit();

        $folder = BookingFolder::create(
            strtoupper($label).'-'.$suffix,
            (int) $customer->id(),
            (int) $office->id(),
        );
        $this->folderRepository->save($folder);
        $this->unitOfWork->commit();

        $booking = (new CreateBookingHandler(
            $this->bookingRepository,
            new BookingReferentialValidator($this->connection),
            $this->unitOfWork,
        ))(new CreateBookingCommand(
            folderId: (int) $folder->id(),
            serviceTypeCode: 'flight',
            statusCode: 'draft',
            customerAccountId: (int) $customer->id(),
            supplierAccountId: null,
            officeAccountId: (int) $office->id(),
            startDate: '2026-11-01',
            endDate: '2026-11-05',
            achatCurrencyCode: 'TND',
            venteCurrencyCode: 'TND',
            achatExchangeRate: '1',
            venteExchangeRate: '1',
            totalAchatAmount: $totalAchat,
            totalVenteAmount: $totalVente,
            margeAgenceAmount: 0,
            margeDistributeurAmount: 0,
            paidAmount: 0,
        ));

        $bookingId = $booking->id();
        self::assertNotNull($bookingId);

        return [$bookingId, (int) $beneficiary->id()];
    }
}
