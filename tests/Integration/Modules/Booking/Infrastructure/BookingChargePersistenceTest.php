<?php

declare(strict_types=1);

namespace App\Tests\Integration\Modules\Booking\Infrastructure;

use App\Modules\Booking\Application\AddBookingCharge\AddBookingChargeCommand;
use App\Modules\Booking\Application\AddBookingCharge\AddBookingChargeHandler;
use App\Modules\Booking\Application\AddBookingTransportSegment\AddBookingTransportSegmentCommand;
use App\Modules\Booking\Application\AddBookingTransportSegment\AddBookingTransportSegmentHandler;
use App\Modules\Booking\Application\AssertBookingServiceType;
use App\Modules\Booking\Application\BookingReferentialValidator;
use App\Modules\Booking\Application\CreateBooking\CreateBookingCommand;
use App\Modules\Booking\Application\CreateBooking\CreateBookingHandler;
use App\Modules\Booking\Application\CreateBookingTraveler\CreateBookingTravelerCommand;
use App\Modules\Booking\Application\CreateBookingTraveler\CreateBookingTravelerHandler;
use App\Modules\Booking\Domain\Entity\BookingFolder;
use App\Modules\Booking\Domain\Exception\BookingChargeSegmentMismatchException;
use App\Modules\Booking\Domain\Exception\BookingChargeTravelerMismatchException;
use App\Modules\Booking\Domain\Exception\BookingUnknownChargeTypeException;
use App\Modules\Booking\Domain\Repository\BookingChargeRepositoryInterface;
use App\Modules\Booking\Domain\Repository\BookingFolderRepositoryInterface;
use App\Modules\Booking\Domain\Repository\BookingRepositoryInterface;
use App\Modules\Booking\Domain\Repository\BookingTransportSegmentRepositoryInterface;
use App\Modules\Booking\Domain\Repository\BookingTravelerRepositoryInterface;
use App\Modules\Party\Domain\Entity\PartyAccount;
use App\Modules\Party\Domain\Repository\PartyAccountRepositoryInterface;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\Money;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * PostgreSQL réel — booking_charge + recalcul applicatif des totaux.
 */
final class BookingChargePersistenceTest extends KernelTestCase
{
    private UnitOfWork $unitOfWork;

    private EntityManagerInterface $em;

    private Connection $connection;

    private BookingRepositoryInterface $bookingRepository;

    private BookingFolderRepositoryInterface $folderRepository;

    private PartyAccountRepositoryInterface $accountRepository;

    private BookingChargeRepositoryInterface $chargeRepository;

    private BookingTravelerRepositoryInterface $travelerRepository;

    private BookingTransportSegmentRepositoryInterface $segmentRepository;

    private CreateBookingHandler $createBookingHandler;

    private CreateBookingTravelerHandler $createTravelerHandler;

    private AddBookingTransportSegmentHandler $addSegmentHandler;

    private AddBookingChargeHandler $addChargeHandler;

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

        /** @var BookingChargeRepositoryInterface $chargeRepository */
        $chargeRepository = $container->get(BookingChargeRepositoryInterface::class);
        $this->chargeRepository = $chargeRepository;

        /** @var BookingTravelerRepositoryInterface $travelerRepository */
        $travelerRepository = $container->get(BookingTravelerRepositoryInterface::class);
        $this->travelerRepository = $travelerRepository;

        /** @var BookingTransportSegmentRepositoryInterface $segmentRepository */
        $segmentRepository = $container->get(BookingTransportSegmentRepositoryInterface::class);
        $this->segmentRepository = $segmentRepository;

        $referential = new BookingReferentialValidator($connection);
        $this->createBookingHandler = new CreateBookingHandler($this->bookingRepository, $referential, $this->unitOfWork);
        $this->createTravelerHandler = new CreateBookingTravelerHandler($this->travelerRepository, $this->unitOfWork);
        $this->addSegmentHandler = new AddBookingTransportSegmentHandler(
            new AssertBookingServiceType($connection),
            $this->segmentRepository,

            $this->unitOfWork
);
        $this->addChargeHandler = new AddBookingChargeHandler(
            $this->bookingRepository,
            $this->chargeRepository,
            $this->travelerRepository,
            $this->segmentRepository,
            $referential,
            $connection,

            $this->unitOfWork
);
    }

    public function test_simple_charge_round_trip_updates_booking_totals(): void
    {
        $bookingId = $this->createBooking('flight', 'ChargeSimple', initialAchat: 10_000, initialVente: 12_000);

        $before = $this->bookingRepository->findById($bookingId);
        self::assertNotNull($before);
        self::assertSame(10_000, $before->totalAchatAmount()->amount());
        self::assertSame(12_000, $before->totalVenteAmount()->amount());

        $charge = ($this->addChargeHandler)(new AddBookingChargeCommand(
            bookingId: $bookingId,
            chargeTypeCode: 'fare',
            achatAmount: Money::fromMinorUnits(5_000, 'TND'),
            venteAmount: Money::fromMinorUnits(6_500, 'TND'),
            label: 'Tarif aller',
            sortOrder: 1,
        ));

        self::assertNotNull($charge->id());
        self::assertSame($bookingId, $charge->bookingId());
        self::assertSame('fare', $charge->chargeTypeCode());
        self::assertSame('Tarif aller', $charge->label());
        self::assertSame(5_000, $charge->achatAmount()->amount());
        self::assertSame(6_500, $charge->venteAmount()->amount());

        $this->em->clear();

        $reloadedCharges = $this->chargeRepository->findByBookingId($bookingId);
        self::assertCount(1, $reloadedCharges);
        $reloaded = $reloadedCharges[0];
        self::assertSame($charge->id(), $reloaded->id());
        self::assertSame('fare', $reloaded->chargeTypeCode());
        self::assertSame('Tarif aller', $reloaded->label());
        self::assertSame(5_000, $reloaded->achatAmount()->amount());
        self::assertSame('TND', $reloaded->achatAmount()->currencyCode());
        self::assertSame(6_500, $reloaded->venteAmount()->amount());
        self::assertSame('TND', $reloaded->venteAmount()->currencyCode());
        self::assertSame(1, $reloaded->sortOrder());

        $after = $this->bookingRepository->findById($bookingId);
        self::assertNotNull($after);
        self::assertSame(5_000, $after->totalAchatAmount()->amount());
        self::assertSame(6_500, $after->totalVenteAmount()->amount());
        self::assertSame('TND', $after->totalAchatAmount()->currencyCode());
        self::assertSame('TND', $after->totalVenteAmount()->currencyCode());
    }

    public function test_successive_charges_accumulate_totals(): void
    {
        $bookingId = $this->createBooking('hotel', 'ChargeCumulate', initialAchat: 99, initialVente: 99);

        ($this->addChargeHandler)(new AddBookingChargeCommand(
            bookingId: $bookingId,
            chargeTypeCode: 'room_rate',
            achatAmount: Money::fromMinorUnits(10_000, 'TND'),
            venteAmount: Money::fromMinorUnits(12_000, 'TND'),
            sortOrder: 0,
        ));

        $this->em->clear();
        $afterFirst = $this->bookingRepository->findById($bookingId);
        self::assertNotNull($afterFirst);
        self::assertSame(10_000, $afterFirst->totalAchatAmount()->amount());
        self::assertSame(12_000, $afterFirst->totalVenteAmount()->amount());

        ($this->addChargeHandler)(new AddBookingChargeCommand(
            bookingId: $bookingId,
            chargeTypeCode: 'city_tax',
            achatAmount: Money::fromMinorUnits(200, 'TND'),
            venteAmount: Money::fromMinorUnits(200, 'TND'),
            sortOrder: 1,
        ));

        $this->em->clear();
        $afterSecond = $this->bookingRepository->findById($bookingId);
        self::assertNotNull($afterSecond);
        self::assertSame(10_200, $afterSecond->totalAchatAmount()->amount());
        self::assertSame(12_200, $afterSecond->totalVenteAmount()->amount());

        ($this->addChargeHandler)(new AddBookingChargeCommand(
            bookingId: $bookingId,
            chargeTypeCode: 'file_fee',
            achatAmount: Money::fromMinorUnits(0, 'TND'),
            venteAmount: Money::fromMinorUnits(1_500, 'TND'),
            sortOrder: 2,
        ));

        $this->em->clear();
        $afterThird = $this->bookingRepository->findById($bookingId);
        self::assertNotNull($afterThird);
        self::assertSame(10_200, $afterThird->totalAchatAmount()->amount());
        self::assertSame(13_700, $afterThird->totalVenteAmount()->amount());

        $charges = $this->chargeRepository->findByBookingId($bookingId);
        self::assertCount(3, $charges);
        self::assertSame(['room_rate', 'city_tax', 'file_fee'], array_map(
            static fn ($c) => $c->chargeTypeCode(),
            $charges,
        ));
    }

    public function test_traveler_from_other_booking_rejected_before_sql(): void
    {
        $bookingA = $this->createBooking('flight', 'ChargeTvA');
        $bookingB = $this->createBooking('flight', 'ChargeTvB');

        $travelerOnB = ($this->createTravelerHandler)(new CreateBookingTravelerCommand(
            bookingId: $bookingB,
            firstName: 'Other',
            lastName: 'Booking',
        ));
        self::assertNotNull($travelerOnB->id());

        try {
            ($this->addChargeHandler)(new AddBookingChargeCommand(
                bookingId: $bookingA,
                chargeTypeCode: 'fare',
                achatAmount: Money::fromMinorUnits(100, 'TND'),
                venteAmount: Money::fromMinorUnits(100, 'TND'),
                travelerId: $travelerOnB->id(),
            ));
            self::fail('Expected BookingChargeTravelerMismatchException');
        } catch (BookingChargeTravelerMismatchException $exception) {
            self::assertSame('booking_charge.traveler_mismatch', $exception->errorCode());
            self::assertSame($bookingA, $exception->context()['booking_id']);
            self::assertSame($travelerOnB->id(), $exception->context()['traveler_id']);
        }

        self::assertSame([], $this->chargeRepository->findByBookingId($bookingA));
        $countA = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM booking_charge WHERE booking_id = :id',
            ['id' => $bookingA],
        );
        self::assertTrue(is_numeric($countA));
        self::assertSame(0, (int) $countA);
    }

    public function test_segment_from_other_booking_rejected_before_sql(): void
    {
        $bookingA = $this->createBooking('flight', 'ChargeSegA');
        $bookingB = $this->createBooking('flight', 'ChargeSegB');

        $segmentOnB = ($this->addSegmentHandler)(new AddBookingTransportSegmentCommand(
            bookingId: $bookingB,
            departureAt: new DateTimeImmutable('2026-11-01 08:00:00+01:00'),
            arrivalAt: new DateTimeImmutable('2026-11-01 10:30:00+01:00'),
            sequenceNumber: 1,
            carrierCode: 'TU',
            departureLocation: 'TUN',
            arrivalLocation: 'CDG',
        ));
        self::assertNotNull($segmentOnB->id());

        try {
            ($this->addChargeHandler)(new AddBookingChargeCommand(
                bookingId: $bookingA,
                chargeTypeCode: 'fare',
                achatAmount: Money::fromMinorUnits(100, 'TND'),
                venteAmount: Money::fromMinorUnits(100, 'TND'),
                segmentId: $segmentOnB->id(),
            ));
            self::fail('Expected BookingChargeSegmentMismatchException');
        } catch (BookingChargeSegmentMismatchException $exception) {
            self::assertSame('booking_charge.segment_mismatch', $exception->errorCode());
            self::assertSame($bookingA, $exception->context()['booking_id']);
            self::assertSame($segmentOnB->id(), $exception->context()['segment_id']);
        }

        self::assertSame([], $this->chargeRepository->findByBookingId($bookingA));
        $countA = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM booking_charge WHERE booking_id = :id',
            ['id' => $bookingA],
        );
        self::assertTrue(is_numeric($countA));
        self::assertSame(0, (int) $countA);
    }

    public function test_unknown_charge_type_rejected_as_domain_exception(): void
    {
        $bookingId = $this->createBooking('flight', 'ChargeUnknownType');

        try {
            ($this->addChargeHandler)(new AddBookingChargeCommand(
                bookingId: $bookingId,
                chargeTypeCode: 'not_a_real_charge_type',
                achatAmount: Money::fromMinorUnits(100, 'TND'),
                venteAmount: Money::fromMinorUnits(100, 'TND'),
            ));
            self::fail('Expected BookingUnknownChargeTypeException');
        } catch (BookingUnknownChargeTypeException $exception) {
            self::assertSame('booking.unknown_charge_type', $exception->errorCode());
            self::assertSame('not_a_real_charge_type', $exception->context()['code']);
        }

        $count = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM booking_charge WHERE booking_id = :id',
            ['id' => $bookingId],
        );
        self::assertTrue(is_numeric($count));
        self::assertSame(0, (int) $count);
    }

    public function test_metadata_jsonb_nested_object_round_trip(): void
    {
        $bookingId = $this->createBooking('maritime', 'ChargeMeta');
        $metadata = [
            'vehicle' => [
                'plate' => '123 TUN 456',
                'dims' => ['length_m' => 4.5, 'width_m' => 1.8],
            ],
            'flags' => ['oversized' => true, 'tags' => ['deck', 'priority']],
        ];

        ($this->addChargeHandler)(new AddBookingChargeCommand(
            bookingId: $bookingId,
            chargeTypeCode: 'vehicle_transport',
            achatAmount: Money::fromMinorUnits(8_000, 'TND'),
            venteAmount: Money::fromMinorUnits(9_500, 'TND'),
            label: 'Transport véhicule',
            metadata: $metadata,
            sortOrder: 0,
        ));

        $this->em->clear();

        $charges = $this->chargeRepository->findByBookingId($bookingId);
        self::assertCount(1, $charges);
        self::assertEquals($metadata, $charges[0]->metadata());

        $raw = $this->connection->fetchOne(
            'SELECT metadata::text FROM booking_charge WHERE booking_id = :id',
            ['id' => $bookingId],
        );
        self::assertIsString($raw);
        self::assertStringContainsString('123 TUN 456', $raw);
        self::assertStringContainsString('oversized', $raw);
    }

    private function createBooking(
        string $serviceTypeCode,
        string $label,
        int $initialAchat = 0,
        int $initialVente = 0,
    ): int {
        $ctx = $this->seedFolderContext($label);

        $booking = ($this->createBookingHandler)(new CreateBookingCommand(
            folderId: $ctx['folderId'],
            serviceTypeCode: $serviceTypeCode,
            statusCode: 'draft',
            customerAccountId: $ctx['customerId'],
            supplierAccountId: null,
            officeAccountId: $ctx['officeId'],
            startDate: '2026-10-01',
            endDate: '2026-10-05',
            achatCurrencyCode: 'TND',
            venteCurrencyCode: 'TND',
            achatExchangeRate: '1',
            venteExchangeRate: '1',
            totalAchatAmount: $initialAchat,
            totalVenteAmount: $initialVente,
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
            Email::fromString('ch.cust.'.$suffix.'@example.com'),
        );
        $office = PartyAccount::createOrganization(
            $label.' Off '.$suffix,
            Email::fromString('ch.off.'.$suffix.'@example.com'),
        );
        $this->accountRepository->save($customer);
        $this->unitOfWork->commit();
        $this->accountRepository->save($office);
        $this->unitOfWork->commit();

        $folder = BookingFolder::create(
            'CH-'.$label.'-'.$suffix,
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
