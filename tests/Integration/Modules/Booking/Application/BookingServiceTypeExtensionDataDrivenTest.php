<?php

declare(strict_types=1);

namespace App\Tests\Integration\Modules\Booking\Application;

use App\Modules\Booking\Application\AddBookingTransportSegment\AddBookingTransportSegmentCommand;
use App\Modules\Booking\Application\AddBookingTransportSegment\AddBookingTransportSegmentHandler;
use App\Modules\Booking\Application\AssertBookingServiceType;
use App\Modules\Booking\Application\CreateBooking\CreateBookingCommand;
use App\Modules\Booking\Application\BookingReferentialValidator;
use App\Modules\Booking\Application\CreateBooking\CreateBookingHandler;
use App\Modules\Booking\Domain\Entity\BookingFolder;
use App\Modules\Booking\Domain\Exception\BookingServiceTypeMismatchException;
use App\Modules\Booking\Domain\Repository\BookingFolderRepositoryInterface;
use App\Modules\Booking\Domain\Repository\BookingRepositoryInterface;
use App\Modules\Booking\Domain\Repository\BookingTransportSegmentRepositoryInterface;
use App\Modules\Party\Domain\Entity\PartyAccount;
use App\Modules\Party\Domain\Repository\PartyAccountRepositoryInterface;
use App\Shared\Domain\ValueObject\Email;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * Prouve le caractère data-driven de booking_service_type_extension.
 *
 * Service_type utilisé : `bus`.
 * Pourquoi : seedé dans booking_service_type (sort_order 3) mais ABSENT du
 * seed initial de booking_service_type_extension (seulement hotel /
 * flight / train / maritime / transfer / car_rental). Aucune modification
 * PHP n'est nécessaire pour l'activer — uniquement un INSERT SQL.
 */
final class BookingServiceTypeExtensionDataDrivenTest extends KernelTestCase
{
    private UnitOfWork $unitOfWork;

    private Connection $connection;

    private BookingRepositoryInterface $bookingRepository;

    private BookingFolderRepositoryInterface $folderRepository;

    private PartyAccountRepositoryInterface $accountRepository;

    private BookingTransportSegmentRepositoryInterface $segmentRepository;

    private CreateBookingHandler $createBookingHandler;

    private AddBookingTransportSegmentHandler $addSegmentHandler;

    private AssertBookingServiceType $assertBookingServiceType;

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

        /** @var BookingRepositoryInterface $bookingRepository */
        $bookingRepository = $container->get(BookingRepositoryInterface::class);
        $this->bookingRepository = $bookingRepository;

        /** @var BookingFolderRepositoryInterface $folderRepository */
        $folderRepository = $container->get(BookingFolderRepositoryInterface::class);
        $this->folderRepository = $folderRepository;

        /** @var PartyAccountRepositoryInterface $accountRepository */
        $accountRepository = $container->get(PartyAccountRepositoryInterface::class);
        $this->accountRepository = $accountRepository;

        /** @var BookingTransportSegmentRepositoryInterface $segmentRepository */
        $segmentRepository = $container->get(BookingTransportSegmentRepositoryInterface::class);
        $this->segmentRepository = $segmentRepository;

        $this->createBookingHandler = new CreateBookingHandler($this->bookingRepository, new BookingReferentialValidator($connection), $this->unitOfWork);
        $this->assertBookingServiceType = new AssertBookingServiceType(
            $this->connection,
        );
        $this->addSegmentHandler = new AddBookingTransportSegmentHandler(
            $this->assertBookingServiceType,
            $this->segmentRepository,

            $this->unitOfWork
);
    }

    protected function tearDown(): void
    {
        $this->connection->executeStatement(
            "DELETE FROM booking_service_type_extension
             WHERE service_type_code = 'bus' AND extension_code = 'transport_segment'",
        );

        parent::tearDown();
    }

    public function test_bus_rejected_until_mapping_row_inserted(): void
    {
        $bookingId = $this->createBusBooking();

        try {
            ($this->assertBookingServiceType)($bookingId, 'transport_segment');
            self::fail('Expected BookingServiceTypeMismatchException before mapping insert');
        } catch (BookingServiceTypeMismatchException $exception) {
            self::assertSame('booking.service_type_mismatch', $exception->errorCode());
            self::assertSame('transport_segment', $exception->context()['extension_code']);
            self::assertSame('bus', $exception->context()['actual_service_type']);
        }

        $this->connection->executeStatement(
            "INSERT INTO booking_service_type_extension (service_type_code, extension_code)
             VALUES ('bus', 'transport_segment')",
        );

        // Même Assert / même Handler PHP — seul le référentiel a changé.
        ($this->assertBookingServiceType)($bookingId, 'transport_segment');

        $segment = ($this->addSegmentHandler)(new AddBookingTransportSegmentCommand(
            bookingId: $bookingId,
            departureAt: new DateTimeImmutable('2026-12-01 08:00:00'),
            arrivalAt: new DateTimeImmutable('2026-12-01 12:00:00'),
            sequenceNumber: 1,
            carrierCode: 'SNT',
            departureLocation: 'TUN',
            arrivalLocation: 'SFA',
        ));

        self::assertNotNull($segment->id());
        self::assertSame($bookingId, $segment->bookingId());
    }

    private function createBusBooking(): int
    {
        $suffix = bin2hex(random_bytes(4));
        $customer = PartyAccount::createOrganization(
            'BusDD Cust '.$suffix,
            Email::fromString('bus.dd.cust.'.$suffix.'@example.com'),
        );
        $office = PartyAccount::createOrganization(
            'BusDD Off '.$suffix,
            Email::fromString('bus.dd.off.'.$suffix.'@example.com'),
        );
        $this->accountRepository->save($customer);
        $this->unitOfWork->commit();
        $this->accountRepository->save($office);
        $this->unitOfWork->commit();

        $folder = BookingFolder::create(
            'BUS-DD-'.$suffix,
            (int) $customer->id(),
            (int) $office->id(),
        );
        $this->folderRepository->save($folder);
        $this->unitOfWork->commit();

        $booking = ($this->createBookingHandler)(new CreateBookingCommand(
            folderId: (int) $folder->id(),
            serviceTypeCode: 'bus',
            statusCode: 'draft',
            customerAccountId: (int) $customer->id(),
            supplierAccountId: null,
            officeAccountId: (int) $office->id(),
            startDate: '2026-12-01',
            endDate: '2026-12-01',
            achatCurrencyCode: 'TND',
            venteCurrencyCode: 'TND',
            achatExchangeRate: '1',
            venteExchangeRate: '1',
            totalAchatAmount: 0,
            totalVenteAmount: 0,
            margeAgenceAmount: 0,
            margeDistributeurAmount: 0,
            paidAmount: 0,
        ));

        $id = $booking->id();
        self::assertNotNull($id);

        return $id;
    }
}
