<?php

declare(strict_types=1);

namespace App\Tests\Integration\Modules\Booking\Infrastructure;

use App\Modules\Booking\Application\AssignBookingPayerSplit\AssignBookingPayerSplitCommand;
use App\Modules\Booking\Application\AssignBookingPayerSplit\AssignBookingPayerSplitHandler;
use App\Modules\Booking\Application\BookingReferentialValidator;
use App\Modules\Booking\Application\CreateBooking\CreateBookingCommand;
use App\Modules\Booking\Application\CreateBooking\CreateBookingHandler;
use App\Modules\Booking\Application\RevokeBookingPayerSplit\RevokeBookingPayerSplitCommand;
use App\Modules\Booking\Application\RevokeBookingPayerSplit\RevokeBookingPayerSplitHandler;
use App\Modules\Booking\Domain\Entity\BookingFolder;
use App\Modules\Booking\Domain\Exception\BookingPayerSplitAlreadyActiveException;
use App\Modules\Booking\Domain\Exception\BookingPayerSplitCurrencyMismatchException;
use App\Modules\Booking\Domain\Exception\BookingPayerSplitExceedsTotalException;
use App\Modules\Booking\Domain\Repository\BookingFolderRepositoryInterface;
use App\Modules\Booking\Domain\Repository\BookingPayerSplitRepositoryInterface;
use App\Modules\Booking\Domain\Repository\BookingRepositoryInterface;
use App\Modules\Party\Domain\Entity\PartyAccount;
use App\Modules\Party\Domain\Repository\PartyAccountRepositoryInterface;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Infrastructure\Persistence\UnitOfWork;
use Doctrine\ORM\EntityManagerInterface;
use ReflectionClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * PostgreSQL réel — booking_payer_split (plafond Application, pas de mutation Booking).
 */
final class BookingPayerSplitPersistenceTest extends KernelTestCase
{
    private UnitOfWork $unitOfWork;

    private EntityManagerInterface $em;

    private BookingRepositoryInterface $bookingRepository;

    private BookingFolderRepositoryInterface $folderRepository;

    private PartyAccountRepositoryInterface $accountRepository;

    private BookingPayerSplitRepositoryInterface $payerSplitRepository;

    private AssignBookingPayerSplitHandler $assignHandler;

    private RevokeBookingPayerSplitHandler $revokeHandler;

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

        /** @var BookingRepositoryInterface $bookingRepository */
        $bookingRepository = $container->get(BookingRepositoryInterface::class);
        $this->bookingRepository = $bookingRepository;

        /** @var BookingFolderRepositoryInterface $folderRepository */
        $folderRepository = $container->get(BookingFolderRepositoryInterface::class);
        $this->folderRepository = $folderRepository;

        /** @var PartyAccountRepositoryInterface $accountRepository */
        $accountRepository = $container->get(PartyAccountRepositoryInterface::class);
        $this->accountRepository = $accountRepository;

        /** @var BookingPayerSplitRepositoryInterface $payerSplitRepository */
        $payerSplitRepository = $container->get(BookingPayerSplitRepositoryInterface::class);
        $this->payerSplitRepository = $payerSplitRepository;

        $this->assignHandler = new AssignBookingPayerSplitHandler(
            $this->bookingRepository,
            $this->payerSplitRepository,
            $this->unitOfWork,
        );
        $this->revokeHandler = new RevokeBookingPayerSplitHandler(
            $this->payerSplitRepository,
            $this->unitOfWork,
        );
    }

    public function test_assign_round_trip_persists_mapped_fields(): void
    {
        [$bookingId, $payerId] = $this->seedBookingWithTotal('PayRT', totalVente: 100_000);

        $split = ($this->assignHandler)(new AssignBookingPayerSplitCommand(
            bookingId: $bookingId,
            payerAccountId: $payerId,
            amountMinor: 40_000,
            currencyCode: 'TND',
        ));

        $id = $split->id();
        self::assertNotNull($id);
        $validFrom = $split->validFrom();

        $this->em->clear();

        $reloaded = $this->payerSplitRepository->findById($id);
        self::assertNotNull($reloaded);
        self::assertSame($id, $reloaded->id());
        self::assertSame($bookingId, $reloaded->bookingId());
        self::assertSame($payerId, $reloaded->payerAccountId());
        self::assertSame(40_000, $reloaded->amount()->amount());
        self::assertSame('TND', $reloaded->currencyCode());
        self::assertTrue($reloaded->isActive());
        self::assertNull($reloaded->validTo());
        self::assertSame(
            $validFrom->format('Y-m-d H:i:s'),
            $reloaded->validFrom()->format('Y-m-d H:i:s'),
        );
    }

    public function test_two_splits_summing_exactly_to_total_are_accepted(): void
    {
        [$bookingId, $payerA] = $this->seedBookingWithTotal('PayExact', totalVente: 100_000);
        $payerB = $this->createPayer('PayExactB');

        ($this->assignHandler)(new AssignBookingPayerSplitCommand(
            bookingId: $bookingId,
            payerAccountId: $payerA,
            amountMinor: 30_000,
            currencyCode: 'TND',
        ));
        ($this->assignHandler)(new AssignBookingPayerSplitCommand(
            bookingId: $bookingId,
            payerAccountId: $payerB,
            amountMinor: 70_000,
            currencyCode: 'TND',
        ));

        self::assertSame(100_000, $this->payerSplitRepository->sumActiveAmountForBooking($bookingId));
        self::assertCount(2, $this->payerSplitRepository->findByBookingId($bookingId));
    }

    public function test_split_exceeding_total_by_one_centime_is_rejected_before_sql(): void
    {
        [$bookingId, $payerA] = $this->seedBookingWithTotal('PayOver', totalVente: 100_000);
        $payerB = $this->createPayer('PayOverB');

        ($this->assignHandler)(new AssignBookingPayerSplitCommand(
            bookingId: $bookingId,
            payerAccountId: $payerA,
            amountMinor: 100_000,
            currencyCode: 'TND',
        ));

        try {
            ($this->assignHandler)(new AssignBookingPayerSplitCommand(
                bookingId: $bookingId,
                payerAccountId: $payerB,
                amountMinor: 1,
                currencyCode: 'TND',
            ));
            self::fail('Expected BookingPayerSplitExceedsTotalException');
        } catch (BookingPayerSplitExceedsTotalException $exception) {
            self::assertSame('booking_payer_split.exceeds_total', $exception->errorCode());
            self::assertSame(100_000, $exception->context()['already_allocated_minor']);
            self::assertSame(1, $exception->context()['requested_minor']);
            self::assertSame(100_000, $exception->context()['allowed_total_minor']);
        }

        self::assertCount(1, $this->payerSplitRepository->findByBookingId($bookingId));
        self::assertSame(100_000, $this->payerSplitRepository->sumActiveAmountForBooking($bookingId));
    }

    public function test_second_active_split_same_payer_is_rejected_before_sql(): void
    {
        [$bookingId, $payerId] = $this->seedBookingWithTotal('PayDup', totalVente: 100_000);

        ($this->assignHandler)(new AssignBookingPayerSplitCommand(
            bookingId: $bookingId,
            payerAccountId: $payerId,
            amountMinor: 10_000,
            currencyCode: 'TND',
        ));

        // Plafond OK (10k+10k=20k <= 100k) — seul le doublon actif (même payeur) bloque.
        try {
            ($this->assignHandler)(new AssignBookingPayerSplitCommand(
                bookingId: $bookingId,
                payerAccountId: $payerId,
                amountMinor: 10_000,
                currencyCode: 'TND',
            ));
            self::fail('Expected BookingPayerSplitAlreadyActiveException');
        } catch (BookingPayerSplitAlreadyActiveException $exception) {
            self::assertSame('booking_payer_split.already_active', $exception->errorCode());
            self::assertSame($bookingId, $exception->context()['booking_id']);
            self::assertSame($payerId, $exception->context()['payer_account_id']);
        }

        self::assertTrue($this->payerSplitRepository->hasActivePayerSplit($bookingId, $payerId));
        self::assertCount(1, $this->payerSplitRepository->findByBookingId($bookingId));
        self::assertSame(10_000, $this->payerSplitRepository->sumActiveAmountForBooking($bookingId));
    }

    public function test_after_revoke_freed_amount_can_be_reassigned(): void
    {
        [$bookingId, $payerA] = $this->seedBookingWithTotal('PayRev', totalVente: 100_000);
        $payerB = $this->createPayer('PayRevB');

        $first = ($this->assignHandler)(new AssignBookingPayerSplitCommand(
            bookingId: $bookingId,
            payerAccountId: $payerA,
            amountMinor: 100_000,
            currencyCode: 'TND',
        ));

        ($this->revokeHandler)(new RevokeBookingPayerSplitCommand((int) $first->id()));

        self::assertSame(0, $this->payerSplitRepository->sumActiveAmountForBooking($bookingId));

        $second = ($this->assignHandler)(new AssignBookingPayerSplitCommand(
            bookingId: $bookingId,
            payerAccountId: $payerB,
            amountMinor: 100_000,
            currencyCode: 'TND',
        ));

        self::assertTrue($second->isActive());
        self::assertSame(100_000, $this->payerSplitRepository->sumActiveAmountForBooking($bookingId));
        self::assertCount(1, $this->payerSplitRepository->findByBookingId($bookingId));
        self::assertCount(2, $this->payerSplitRepository->findByBookingId($bookingId, activeOnly: false));
    }

    public function test_currency_mismatch_is_rejected(): void
    {
        [$bookingId, $payerId] = $this->seedBookingWithTotal('PayCur', totalVente: 50_000);

        try {
            ($this->assignHandler)(new AssignBookingPayerSplitCommand(
                bookingId: $bookingId,
                payerAccountId: $payerId,
                amountMinor: 10_000,
                currencyCode: 'EUR',
            ));
            self::fail('Expected BookingPayerSplitCurrencyMismatchException');
        } catch (BookingPayerSplitCurrencyMismatchException $exception) {
            self::assertSame('booking_payer_split.currency_mismatch', $exception->errorCode());
            self::assertSame('TND', $exception->context()['expected_currency']);
            self::assertSame('EUR', $exception->context()['actual_currency']);
        }

        self::assertCount(0, $this->payerSplitRepository->findByBookingId($bookingId));
    }

    public function test_assign_does_not_mutate_booking_totals(): void
    {
        [$bookingId, $payerId] = $this->seedBookingWithTotal('PayNoMut', totalVente: 80_000, totalAchat: 50_000);

        $before = $this->bookingRepository->findById($bookingId);
        self::assertNotNull($before);
        $achatBefore = $before->totalAchatAmount()->amount();
        $venteBefore = $before->totalVenteAmount()->amount();
        $margeAgenceBefore = $before->margeAgenceAmount()->amount();
        $margeDistrBefore = $before->margeDistributeurAmount()->amount();
        $paidBefore = $before->paidAmount()->amount();

        ($this->assignHandler)(new AssignBookingPayerSplitCommand(
            bookingId: $bookingId,
            payerAccountId: $payerId,
            amountMinor: 80_000,
            currencyCode: 'TND',
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

    public function test_handler_reads_booking_but_never_mutates_it(): void
    {
        $ctor = (new ReflectionClass(AssignBookingPayerSplitHandler::class))->getConstructor();
        self::assertNotNull($ctor);

        $typeNames = [];
        foreach ($ctor->getParameters() as $param) {
            $type = $param->getType();
            if ($type instanceof \ReflectionNamedType) {
                $typeNames[] = $type->getName();
            }
        }

        self::assertContains(BookingRepositoryInterface::class, $typeNames);
        self::assertContains(BookingPayerSplitRepositoryInterface::class, $typeNames);

        $source = (string) file_get_contents(
            (new ReflectionClass(AssignBookingPayerSplitHandler::class))->getFileName() ?: '',
        );

        self::assertStringContainsString('totalVenteAmount()', $source);
        self::assertStringContainsString('findById', $source);
        self::assertStringNotContainsString('recalculateTotals', $source);
        self::assertStringNotContainsString('bookingRepository->save', $source);
        self::assertStringNotContainsString('->transitionTo(', $source);
        self::assertStringNotContainsString('->markAs', $source);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function seedBookingWithTotal(string $label, int $totalVente, int $totalAchat = 0): array
    {
        $suffix = bin2hex(random_bytes(4));
        $customer = PartyAccount::createOrganization(
            $label.' Cust '.$suffix,
            Email::fromString(strtolower($label).'.cust.'.$suffix.'@example.com'),
        );
        $office = PartyAccount::createOrganization(
            $label.' Off '.$suffix,
            Email::fromString(strtolower($label).'.off.'.$suffix.'@example.com'),
        );
        $payer = PartyAccount::createOrganization(
            $label.' Pay '.$suffix,
            Email::fromString(strtolower($label).'.pay.'.$suffix.'@example.com'),
        );
        $this->accountRepository->save($customer);
        $this->accountRepository->save($office);
        $this->accountRepository->save($payer);
        $this->unitOfWork->commit();

        $folder = BookingFolder::create(
            strtoupper($label).'-'.$suffix,
            (int) $customer->id(),
            (int) $office->id(),
        );
        $this->folderRepository->save($folder);
        $this->unitOfWork->commit();

        $connection = self::getContainer()->get(\Doctrine\DBAL\Connection::class);
        self::assertInstanceOf(\Doctrine\DBAL\Connection::class, $connection);

        $booking = (new CreateBookingHandler(
            $this->bookingRepository,
            new BookingReferentialValidator($connection),
            $this->unitOfWork,
        ))(new CreateBookingCommand(
            folderId: (int) $folder->id(),
            serviceTypeCode: 'flight',
            statusCode: 'draft',
            customerAccountId: (int) $customer->id(),
            supplierAccountId: null,
            officeAccountId: (int) $office->id(),
            startDate: '2026-12-01',
            endDate: '2026-12-05',
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

        return [$bookingId, (int) $payer->id()];
    }

    private function createPayer(string $label): int
    {
        $suffix = bin2hex(random_bytes(3));
        $payer = PartyAccount::createOrganization(
            $label.' '.$suffix,
            Email::fromString(strtolower($label).'.'.$suffix.'@example.com'),
        );
        $this->accountRepository->save($payer);
        $this->unitOfWork->commit();

        return (int) $payer->id();
    }
}
