<?php

declare(strict_types=1);

namespace App\Tests\Integration\Modules\Booking\Infrastructure;

use App\Modules\Booking\Application\CreateBooking\CreateBookingCommand;
use App\Modules\Booking\Application\BookingReferentialValidator;
use App\Modules\Booking\Application\CreateBooking\CreateBookingHandler;
use App\Modules\Booking\Domain\Entity\BookingFolder;
use App\Modules\Booking\Domain\Exception\InvalidBookingStateException;
use App\Modules\Booking\Domain\Repository\BookingFolderRepositoryInterface;
use App\Modules\Booking\Domain\Repository\BookingRepositoryInterface;
use App\Modules\Booking\Domain\ValueObject\PaymentStatus;
use App\Modules\Party\Domain\Entity\PartyAccount;
use App\Modules\Party\Domain\Repository\PartyAccountRepositoryInterface;
use App\Shared\Domain\ValueObject\Email;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * PostgreSQL réel — pivot booking montants/devises.
 */
final class BookingPersistenceTest extends KernelTestCase
{
    private UnitOfWork $unitOfWork;

    private EntityManagerInterface $em;

    private BookingRepositoryInterface $bookingRepository;

    private BookingFolderRepositoryInterface $folderRepository;

    private PartyAccountRepositoryInterface $accountRepository;

    private CreateBookingHandler $createHandler;

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

        /** @var Connection $connection */


        $connection = $container->get(Connection::class);


        $this->createHandler = new CreateBookingHandler($this->bookingRepository, new BookingReferentialValidator($connection), $this->unitOfWork);
    }

    public function test_create_with_null_supplier_round_trip(): void
    {
        $ctx = $this->seedFolderContext('NullSupplier');

        $booking = ($this->createHandler)($this->moneyCommand(
            folderId: $ctx['folderId'],
            customerAccountId: $ctx['customerId'],
            supplierAccountId: null,
            officeAccountId: $ctx['officeId'],
            serviceTypeCode: 'hotel',
            statusCode: 'draft',
            startDate: '2026-08-01',
            endDate: '2026-08-05',
        ));

        $id = $booking->id();
        self::assertNotNull($id);
        self::assertNull($booking->supplierAccountId());
        self::assertSame('backoffice', $booking->channelCode()->toString());
        self::assertSame(date('Y-m-d'), $booking->bookingDate()->format('Y-m-d'));

        $this->em->clear();

        $reloaded = $this->bookingRepository->findById($id);
        self::assertNotNull($reloaded);
        self::assertSame($id, $reloaded->id());
        self::assertSame($ctx['folderId'], $reloaded->folderId());
        self::assertSame('hotel', $reloaded->serviceTypeCode()->toString());
        self::assertSame('draft', $reloaded->statusCode()->toString());
        self::assertSame($ctx['customerId'], $reloaded->customerAccountId());
        self::assertNull($reloaded->supplierAccountId());
        self::assertSame($ctx['officeId'], $reloaded->officeAccountId());
        self::assertSame('2026-08-01', $reloaded->startDate()->format('Y-m-d'));
        self::assertSame('2026-08-05', $reloaded->endDate()?->format('Y-m-d'));
        self::assertSame('backoffice', $reloaded->channelCode()->toString());
        self::assertSame(date('Y-m-d'), $reloaded->bookingDate()->format('Y-m-d'));
        self::assertSame($booking->publicId()->toString(), $reloaded->publicId()->toString());
        self::assertSame('TND', $reloaded->achatCurrencyCode());
        self::assertSame('EUR', $reloaded->venteCurrencyCode());
        self::assertSame(PaymentStatus::Unpaid, $reloaded->paymentStatus());
    }

    public function test_money_and_exchange_rate_precision_round_trip(): void
    {
        $ctx = $this->seedFolderContext('MoneyPrec');

        $booking = ($this->createHandler)(new CreateBookingCommand(
            folderId: $ctx['folderId'],
            serviceTypeCode: 'hotel',
            statusCode: 'confirmed',
            customerAccountId: $ctx['customerId'],
            supplierAccountId: null,
            officeAccountId: $ctx['officeId'],
            startDate: '2026-08-15',
            endDate: '2026-08-18',
            achatCurrencyCode: 'USD',
            venteCurrencyCode: 'TND',
            achatExchangeRate: '3.141592',
            venteExchangeRate: '1.000001',
            totalAchatAmount: 999999999999,
            totalVenteAmount: 123456789012,
            margeAgenceAmount: 111222333,
            margeDistributeurAmount: 444555,
            paidAmount: 100,
            channelCode: 'web',
            paymentStatus: 'partial',
        ));

        $id = (int) $booking->id();
        $this->em->clear();

        $reloaded = $this->bookingRepository->findById($id);
        self::assertNotNull($reloaded);

        self::assertSame('USD', $reloaded->achatCurrencyCode());
        self::assertSame('TND', $reloaded->venteCurrencyCode());
        self::assertSame('3.141592', $reloaded->achatExchangeRate()->toString());
        self::assertSame('1.000001', $reloaded->venteExchangeRate()->toString());
        self::assertSame(999999999999, $reloaded->totalAchatAmount()->amount());
        self::assertSame('USD', $reloaded->totalAchatAmount()->currencyCode());
        self::assertSame(123456789012, $reloaded->totalVenteAmount()->amount());
        self::assertSame('TND', $reloaded->totalVenteAmount()->currencyCode());
        self::assertSame(111222333, $reloaded->margeAgenceAmount()->amount());
        self::assertSame(444555, $reloaded->margeDistributeurAmount()->amount());
        self::assertSame(100, $reloaded->paidAmount()->amount());
        self::assertSame(PaymentStatus::Partial, $reloaded->paymentStatus());
        self::assertSame('web', $reloaded->channelCode()->toString());

        $row = $this->em->getConnection()->fetchAssociative(
            'SELECT achat_exchange_rate::text AS aer, vente_exchange_rate::text AS ver,
                    total_achat_amount, total_vente_amount, paid_amount, payment_status
             FROM booking WHERE id = :id',
            ['id' => $id],
        );
        self::assertIsArray($row);
        self::assertIsString($row['aer']);
        self::assertIsString($row['ver']);
        self::assertTrue(is_numeric($row['total_achat_amount']));
        self::assertTrue(is_numeric($row['total_vente_amount']));
        self::assertTrue(is_numeric($row['paid_amount']));
        self::assertIsString($row['payment_status']);
        self::assertStringContainsString('3.141592', $row['aer']);
        self::assertStringContainsString('1.000001', $row['ver']);
        self::assertSame('999999999999', (string) $row['total_achat_amount']);
        self::assertSame('123456789012', (string) $row['total_vente_amount']);
        self::assertSame('100', (string) $row['paid_amount']);
        self::assertSame('partial', $row['payment_status']);
    }

    public function test_end_date_before_start_rejected_by_domain_before_sql(): void
    {
        $ctx = $this->seedFolderContext('BadDates');

        try {
            ($this->createHandler)($this->moneyCommand(
                folderId: $ctx['folderId'],
                customerAccountId: $ctx['customerId'],
                supplierAccountId: $ctx['customerId'],
                officeAccountId: $ctx['officeId'],
                serviceTypeCode: 'transfer',
                statusCode: 'confirmed',
                startDate: '2026-08-10',
                endDate: '2026-08-01',
            ));
            self::fail('Expected InvalidBookingStateException');
        } catch (InvalidBookingStateException $exception) {
            self::assertSame('booking.invalid_dates', $exception->errorCode());
            self::assertSame('2026-08-10', $exception->context()['start_date']);
            self::assertSame('2026-08-01', $exception->context()['end_date']);
        }
    }

    public function test_two_bookings_same_booking_date_coexist(): void
    {
        $ctx = $this->seedFolderContext('SameDay');

        $first = ($this->createHandler)($this->moneyCommand(
            folderId: $ctx['folderId'],
            customerAccountId: $ctx['customerId'],
            supplierAccountId: null,
            officeAccountId: $ctx['officeId'],
            serviceTypeCode: 'flight',
            statusCode: 'draft',
            startDate: '2026-09-01',
            endDate: null,
        ));
        $second = ($this->createHandler)($this->moneyCommand(
            folderId: $ctx['folderId'],
            customerAccountId: $ctx['customerId'],
            supplierAccountId: null,
            officeAccountId: $ctx['officeId'],
            serviceTypeCode: 'visa',
            statusCode: 'draft',
            startDate: '2026-09-02',
            endDate: null,
        ));

        self::assertNotNull($first->id());
        self::assertNotNull($second->id());
        self::assertNotSame($first->id(), $second->id());
        self::assertSame(
            $first->bookingDate()->format('Y-m-d'),
            $second->bookingDate()->format('Y-m-d'),
        );

        $this->em->clear();

        self::assertNotNull($this->bookingRepository->findById((int) $first->id()));
        self::assertNotNull($this->bookingRepository->findById((int) $second->id()));
    }

    private function moneyCommand(
        int $folderId,
        int $customerAccountId,
        ?int $supplierAccountId,
        int $officeAccountId,
        string $serviceTypeCode,
        string $statusCode,
        string $startDate,
        ?string $endDate,
    ): CreateBookingCommand {
        return new CreateBookingCommand(
            folderId: $folderId,
            serviceTypeCode: $serviceTypeCode,
            statusCode: $statusCode,
            customerAccountId: $customerAccountId,
            supplierAccountId: $supplierAccountId,
            officeAccountId: $officeAccountId,
            startDate: $startDate,
            endDate: $endDate,
            achatCurrencyCode: 'TND',
            venteCurrencyCode: 'EUR',
            achatExchangeRate: '1',
            venteExchangeRate: '1',
            totalAchatAmount: 0,
            totalVenteAmount: 0,
            margeAgenceAmount: 0,
            margeDistributeurAmount: 0,
            paidAmount: 0,
        );
    }

    /**
     * @return array{folderId: int, customerId: int, officeId: int}
     */
    private function seedFolderContext(string $label): array
    {
        $suffix = bin2hex(random_bytes(4));
        $customer = PartyAccount::createOrganization(
            $label.' Cust '.$suffix,
            Email::fromString('bk.cust.'.$suffix.'@example.com'),
        );
        $office = PartyAccount::createOrganization(
            $label.' Off '.$suffix,
            Email::fromString('bk.off.'.$suffix.'@example.com'),
        );
        $this->accountRepository->save($customer);
        $this->unitOfWork->commit();
        $this->accountRepository->save($office);
        $this->unitOfWork->commit();

        $folder = BookingFolder::create(
            'B-'.$label.'-'.$suffix,
            (int) $customer->id(),
            (int) $office->id(),
        );
        $this->folderRepository->save($folder);
        $this->unitOfWork->commit();

        return [
            'folderId' => (int) $folder->id(),
            'customerId' => (int) $customer->id(),
            'officeId' => (int) $office->id(),
        ];
    }
}
