<?php

declare(strict_types=1);

namespace App\Tests\Integration\Modules\Booking\Infrastructure;

use App\Modules\Booking\Application\AddBookingTransportSegment\AddBookingTransportSegmentCommand;
use App\Modules\Booking\Application\AddBookingTransportSegment\AddBookingTransportSegmentHandler;
use App\Modules\Booking\Application\AssertBookingServiceType;
use App\Modules\Booking\Application\CreateBooking\CreateBookingCommand;
use App\Modules\Booking\Application\BookingReferentialValidator;
use App\Modules\Booking\Application\CreateBooking\CreateBookingHandler;
use App\Modules\Booking\Domain\Entity\BookingFolder;
use App\Modules\Booking\Domain\Exception\BookingServiceTypeMismatchException;
use App\Modules\Booking\Domain\Exception\InvalidBookingTransportSegmentException;
use App\Modules\Booking\Domain\Repository\BookingFolderRepositoryInterface;
use App\Modules\Booking\Domain\Repository\BookingRepositoryInterface;
use App\Modules\Booking\Domain\Repository\BookingTransportSegmentRepositoryInterface;
use App\Modules\Party\Domain\Entity\PartyAccount;
use App\Modules\Party\Domain\Repository\PartyAccountRepositoryInterface;
use App\Shared\Domain\ValueObject\Email;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * PostgreSQL réel — booking_transport_segment (multi-service).
 */
final class BookingTransportSegmentPersistenceTest extends KernelTestCase
{
    private UnitOfWork $unitOfWork;

    private EntityManagerInterface $em;

    private BookingRepositoryInterface $bookingRepository;

    private BookingFolderRepositoryInterface $folderRepository;

    private PartyAccountRepositoryInterface $accountRepository;

    private BookingTransportSegmentRepositoryInterface $segmentRepository;

    private CreateBookingHandler $createBookingHandler;

    private AddBookingTransportSegmentHandler $addSegmentHandler;

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

        /** @var BookingTransportSegmentRepositoryInterface $segmentRepository */
        $segmentRepository = $container->get(BookingTransportSegmentRepositoryInterface::class);
        $this->segmentRepository = $segmentRepository;

        /** @var Connection $connection */
        $connection = $container->get(Connection::class);

        $this->createBookingHandler = new CreateBookingHandler($this->bookingRepository, new BookingReferentialValidator($connection), $this->unitOfWork);
        $this->addSegmentHandler = new AddBookingTransportSegmentHandler(
            new AssertBookingServiceType($connection),
            $this->segmentRepository,

            $this->unitOfWork
);
    }

    public function test_flight_segment_round_trip(): void
    {
        $bookingId = $this->createBooking('flight', 'SegFlight');

        ($this->addSegmentHandler)(new AddBookingTransportSegmentCommand(
            bookingId: $bookingId,
            departureAt: new DateTimeImmutable('2026-11-01 08:00:00+01:00'),
            arrivalAt: new DateTimeImmutable('2026-11-01 10:30:00+01:00'),
            sequenceNumber: 1,
            carrierCode: 'TU',
            departureLocation: 'TUN',
            arrivalLocation: 'CDG',
        ));

        $this->em->clear();

        $segments = $this->segmentRepository->findByBookingId($bookingId);
        self::assertCount(1, $segments);
        $s = $segments[0];
        self::assertNotNull($s->id());
        self::assertSame(1, $s->sequenceNumber());
        self::assertSame('TU', $s->carrierCode());
        self::assertSame('TUN', $s->departureLocation());
        self::assertSame('CDG', $s->arrivalLocation());
        self::assertSame(
            (new DateTimeImmutable('2026-11-01 08:00:00+01:00'))->getTimestamp(),
            $s->departureAt()->getTimestamp(),
        );
        self::assertSame(
            (new DateTimeImmutable('2026-11-01 10:30:00+01:00'))->getTimestamp(),
            $s->arrivalAt()->getTimestamp(),
        );
    }

    public function test_rejected_for_hotel_booking(): void
    {
        $bookingId = $this->createBooking('hotel', 'SegHotel');

        try {
            ($this->addSegmentHandler)(new AddBookingTransportSegmentCommand(
                bookingId: $bookingId,
                departureAt: new DateTimeImmutable('2026-11-01 08:00:00'),
                arrivalAt: new DateTimeImmutable('2026-11-01 10:00:00'),
            ));
            self::fail('Expected BookingServiceTypeMismatchException');
        } catch (BookingServiceTypeMismatchException $exception) {
            self::assertSame('booking.service_type_mismatch', $exception->errorCode());
            self::assertSame('hotel', $exception->context()['actual_service_type']);
            self::assertSame('transport_segment', $exception->context()['extension_code']);
            self::assertArrayNotHasKey('expected_service_types', $exception->context());
        }

        self::assertSame([], $this->segmentRepository->findByBookingId($bookingId));
    }

    public function test_multiple_segments_ordered_by_sequence_number(): void
    {
        $bookingId = $this->createBooking('maritime', 'SegRoundTrip');

        ($this->addSegmentHandler)(new AddBookingTransportSegmentCommand(
            bookingId: $bookingId,
            departureAt: new DateTimeImmutable('2026-11-10 20:00:00'),
            arrivalAt: new DateTimeImmutable('2026-11-11 08:00:00'),
            sequenceNumber: 2,
            carrierCode: 'GNV',
            departureLocation: 'GEN',
            arrivalLocation: 'TUN',
        ));
        ($this->addSegmentHandler)(new AddBookingTransportSegmentCommand(
            bookingId: $bookingId,
            departureAt: new DateTimeImmutable('2026-11-01 18:00:00'),
            arrivalAt: new DateTimeImmutable('2026-11-02 06:00:00'),
            sequenceNumber: 1,
            carrierCode: 'GNV',
            departureLocation: 'TUN',
            arrivalLocation: 'GEN',
        ));

        $this->em->clear();

        $segments = $this->segmentRepository->findByBookingId($bookingId);
        self::assertCount(2, $segments);
        self::assertSame(1, $segments[0]->sequenceNumber());
        self::assertSame('TUN', $segments[0]->departureLocation());
        self::assertSame(2, $segments[1]->sequenceNumber());
        self::assertSame('GEN', $segments[1]->departureLocation());
    }

    public function test_arrival_before_departure_rejected_by_domain(): void
    {
        $bookingId = $this->createBooking('train', 'SegBadDates');

        try {
            ($this->addSegmentHandler)(new AddBookingTransportSegmentCommand(
                bookingId: $bookingId,
                departureAt: new DateTimeImmutable('2026-11-01 18:00:00'),
                arrivalAt: new DateTimeImmutable('2026-11-01 10:00:00'),
            ));
            self::fail('Expected InvalidBookingTransportSegmentException');
        } catch (InvalidBookingTransportSegmentException $exception) {
            self::assertSame('booking_transport_segment.invalid_dates', $exception->errorCode());
        }

        self::assertSame([], $this->segmentRepository->findByBookingId($bookingId));
    }

    private function createBooking(string $serviceType, string $label): int
    {
        $ctx = $this->seedFolderContext($label);

        $booking = ($this->createBookingHandler)(new CreateBookingCommand(
            folderId: $ctx['folderId'],
            serviceTypeCode: $serviceType,
            statusCode: 'draft',
            customerAccountId: $ctx['customerId'],
            supplierAccountId: null,
            officeAccountId: $ctx['officeId'],
            startDate: '2026-11-01',
            endDate: '2026-11-05',
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

    /**
     * @return array{folderId: int, customerId: int, officeId: int}
     */
    private function seedFolderContext(string $label): array
    {
        $suffix = bin2hex(random_bytes(4));
        $customer = PartyAccount::createOrganization(
            $label.' Cust '.$suffix,
            Email::fromString('ts.cust.'.$suffix.'@example.com'),
        );
        $office = PartyAccount::createOrganization(
            $label.' Off '.$suffix,
            Email::fromString('ts.off.'.$suffix.'@example.com'),
        );
        $this->accountRepository->save($customer);
        $this->unitOfWork->commit();
        $this->accountRepository->save($office);
        $this->unitOfWork->commit();

        $folder = BookingFolder::create(
            'TS-'.$label.'-'.$suffix,
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
