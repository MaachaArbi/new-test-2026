<?php

declare(strict_types=1);

namespace App\Tests\Integration\Modules\Booking\Infrastructure;

use App\Modules\Booking\Application\AssertBookingServiceType;
use App\Modules\Booking\Application\CreateBooking\CreateBookingCommand;
use App\Modules\Booking\Application\BookingReferentialValidator;
use App\Modules\Booking\Application\CreateBooking\CreateBookingHandler;
use App\Modules\Booking\Application\SetBookingCarRentalDetail\SetBookingCarRentalDetailCommand;
use App\Modules\Booking\Application\SetBookingCarRentalDetail\SetBookingCarRentalDetailHandler;
use App\Modules\Booking\Domain\Entity\BookingFolder;
use App\Modules\Booking\Domain\Exception\BookingServiceTypeMismatchException;
use App\Modules\Booking\Domain\Exception\InvalidBookingCarRentalDetailException;
use App\Modules\Booking\Domain\Repository\BookingCarRentalDetailRepositoryInterface;
use App\Modules\Booking\Domain\Repository\BookingFolderRepositoryInterface;
use App\Modules\Booking\Domain\Repository\BookingRepositoryInterface;
use App\Modules\Party\Domain\Entity\PartyAccount;
use App\Modules\Party\Domain\Repository\PartyAccountRepositoryInterface;
use App\Shared\Domain\ValueObject\Email;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * PostgreSQL réel — extension 1-1 booking_car_rental_detail.
 */
final class BookingCarRentalDetailPersistenceTest extends KernelTestCase
{
    private UnitOfWork $unitOfWork;

    private EntityManagerInterface $em;

    private BookingRepositoryInterface $bookingRepository;

    private BookingFolderRepositoryInterface $folderRepository;

    private PartyAccountRepositoryInterface $accountRepository;

    private BookingCarRentalDetailRepositoryInterface $detailRepository;

    private CreateBookingHandler $createHandler;

    private SetBookingCarRentalDetailHandler $setDetailHandler;

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

        /** @var BookingCarRentalDetailRepositoryInterface $detailRepository */
        $detailRepository = $container->get(BookingCarRentalDetailRepositoryInterface::class);
        $this->detailRepository = $detailRepository;

        /** @var Connection $connection */
        $connection = $container->get(Connection::class);

        $this->createHandler = new CreateBookingHandler($this->bookingRepository, new BookingReferentialValidator($connection), $this->unitOfWork);
        $this->setDetailHandler = new SetBookingCarRentalDetailHandler(
            new AssertBookingServiceType($connection),
            $this->detailRepository,

            $this->unitOfWork
);
    }

    public function test_full_round_trip_preserves_hourly_precision(): void
    {
        $bookingId = $this->createBooking('car_rental', 'CarFull');
        $pickupAt = new DateTimeImmutable('2026-06-25 15:00:00+01:00');
        $dropoffAt = new DateTimeImmutable('2026-06-26 15:00:00+01:00');

        ($this->setDetailHandler)(new SetBookingCarRentalDetailCommand(
            bookingId: $bookingId,
            vehicleCategory: 'Mini I10 ou similaire',
            vehicleBrandModel: 'Hyundai',
            pickupAt: $pickupAt,
            dropoffAt: $dropoffAt,
            pickupLocation: 'Aéroport Tunis-Carthage',
            dropoffLocation: 'Sfax centre',
        ));

        $this->em->clear();

        $reloaded = $this->detailRepository->findByBookingId($bookingId);
        self::assertNotNull($reloaded);
        self::assertSame($bookingId, $reloaded->bookingId());
        self::assertSame('Mini I10 ou similaire', $reloaded->vehicleCategory());
        self::assertSame('Hyundai', $reloaded->vehicleBrandModel());
        self::assertSame('Aéroport Tunis-Carthage', $reloaded->pickupLocation());
        self::assertSame('Sfax centre', $reloaded->dropoffLocation());

        self::assertNotNull($reloaded->pickupAt());
        self::assertNotNull($reloaded->dropoffAt());
        self::assertSame($pickupAt->getTimestamp(), $reloaded->pickupAt()->getTimestamp());
        self::assertSame($dropoffAt->getTimestamp(), $reloaded->dropoffAt()->getTimestamp());
        // Précision horaire dans le fuseau d'origine : 15:00, pas minuit.
        self::assertSame(
            '15:00',
            $reloaded->pickupAt()->setTimezone($pickupAt->getTimezone())->format('H:i'),
        );
        self::assertSame(
            '15:00',
            $reloaded->dropoffAt()->setTimezone($dropoffAt->getTimezone())->format('H:i'),
        );
    }

    public function test_rejected_for_hotel_booking(): void
    {
        $bookingId = $this->createBooking('hotel', 'CarOnHotel');

        try {
            ($this->setDetailHandler)(new SetBookingCarRentalDetailCommand(
                bookingId: $bookingId,
                vehicleCategory: 'Should Fail',
            ));
            self::fail('Expected BookingServiceTypeMismatchException');
        } catch (BookingServiceTypeMismatchException $exception) {
            self::assertSame('booking.service_type_mismatch', $exception->errorCode());
            self::assertSame('car_rental', $exception->context()['extension_code']);
            self::assertSame('hotel', $exception->context()['actual_service_type']);
        }

        self::assertNull($this->detailRepository->findByBookingId($bookingId));
    }

    public function test_dropoff_before_pickup_rejected_by_domain(): void
    {
        $bookingId = $this->createBooking('car_rental', 'CarBadDates');

        try {
            ($this->setDetailHandler)(new SetBookingCarRentalDetailCommand(
                bookingId: $bookingId,
                pickupAt: new DateTimeImmutable('2026-06-25 15:00:00'),
                dropoffAt: new DateTimeImmutable('2026-06-25 10:00:00'),
            ));
            self::fail('Expected InvalidBookingCarRentalDetailException');
        } catch (InvalidBookingCarRentalDetailException $exception) {
            self::assertSame('booking_car_rental_detail.invalid_dates', $exception->errorCode());
        }

        self::assertNull($this->detailRepository->findByBookingId($bookingId));
    }

    public function test_null_dropoff_location_accepted_same_place_case(): void
    {
        $bookingId = $this->createBooking('car_rental', 'CarSamePlace');

        ($this->setDetailHandler)(new SetBookingCarRentalDetailCommand(
            bookingId: $bookingId,
            vehicleCategory: 'SUV',
            pickupAt: new DateTimeImmutable('2026-07-01 09:30:00+01:00'),
            dropoffAt: new DateTimeImmutable('2026-07-03 09:30:00+01:00'),
            pickupLocation: 'Agence Tunis',
            dropoffLocation: null,
        ));

        $this->em->clear();

        $reloaded = $this->detailRepository->findByBookingId($bookingId);
        self::assertNotNull($reloaded);
        self::assertSame('Agence Tunis', $reloaded->pickupLocation());
        self::assertNull($reloaded->dropoffLocation());
        self::assertNotNull($reloaded->pickupAt());
        self::assertSame(
            '09:30',
            $reloaded->pickupAt()->setTimezone(new \DateTimeZone('+01:00'))->format('H:i'),
        );
    }

    private function createBooking(string $serviceType, string $label): int
    {
        $ctx = $this->seedFolderContext($label);

        $booking = ($this->createHandler)(new CreateBookingCommand(
            folderId: $ctx['folderId'],
            serviceTypeCode: $serviceType,
            statusCode: 'draft',
            customerAccountId: $ctx['customerId'],
            supplierAccountId: null,
            officeAccountId: $ctx['officeId'],
            startDate: '2026-06-25',
            endDate: '2026-06-26',
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
            Email::fromString('cr.cust.'.$suffix.'@example.com'),
        );
        $office = PartyAccount::createOrganization(
            $label.' Off '.$suffix,
            Email::fromString('cr.off.'.$suffix.'@example.com'),
        );
        $this->accountRepository->save($customer);
        $this->unitOfWork->commit();
        $this->accountRepository->save($office);
        $this->unitOfWork->commit();

        $folder = BookingFolder::create(
            'CR-'.$label.'-'.$suffix,
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
