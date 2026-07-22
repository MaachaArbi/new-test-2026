<?php

declare(strict_types=1);

namespace App\Tests\Integration\Modules\Booking\Infrastructure;

use App\Modules\Booking\Application\AddBookingHotelRoom\AddBookingHotelRoomCommand;
use App\Modules\Booking\Application\AddBookingHotelRoom\AddBookingHotelRoomHandler;
use App\Modules\Booking\Application\AssertBookingServiceType;
use App\Modules\Booking\Application\CreateBooking\CreateBookingCommand;
use App\Modules\Booking\Application\BookingReferentialValidator;
use App\Modules\Booking\Application\CreateBooking\CreateBookingHandler;
use App\Modules\Booking\Application\SetBookingAccommodationDetail\SetBookingAccommodationDetailCommand;
use App\Modules\Booking\Application\SetBookingAccommodationDetail\SetBookingAccommodationDetailHandler;
use App\Modules\Booking\Domain\Entity\BookingFolder;
use App\Modules\Booking\Domain\Exception\BookingServiceTypeMismatchException;
use App\Modules\Booking\Domain\Repository\BookingAccommodationDetailRepositoryInterface;
use App\Modules\Booking\Domain\Repository\BookingFolderRepositoryInterface;
use App\Modules\Booking\Domain\Repository\BookingHotelRoomRepositoryInterface;
use App\Modules\Booking\Domain\Repository\BookingRepositoryInterface;
use App\Modules\Party\Domain\Entity\PartyAccount;
use App\Modules\Party\Domain\Repository\PartyAccountRepositoryInterface;
use App\Shared\Domain\ValueObject\Email;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * PostgreSQL réel — extension hôtel (accommodation_detail + hotel_room).
 */
final class BookingHotelExtensionPersistenceTest extends KernelTestCase
{
    private UnitOfWork $unitOfWork;

    private EntityManagerInterface $em;

    private BookingRepositoryInterface $bookingRepository;

    private BookingFolderRepositoryInterface $folderRepository;

    private PartyAccountRepositoryInterface $accountRepository;

    private BookingAccommodationDetailRepositoryInterface $detailRepository;

    private BookingHotelRoomRepositoryInterface $roomRepository;

    private CreateBookingHandler $createHandler;

    private SetBookingAccommodationDetailHandler $setDetailHandler;

    private AddBookingHotelRoomHandler $addRoomHandler;

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

        /** @var BookingAccommodationDetailRepositoryInterface $detailRepository */
        $detailRepository = $container->get(BookingAccommodationDetailRepositoryInterface::class);
        $this->detailRepository = $detailRepository;

        /** @var BookingHotelRoomRepositoryInterface $roomRepository */
        $roomRepository = $container->get(BookingHotelRoomRepositoryInterface::class);
        $this->roomRepository = $roomRepository;

        /** @var Connection $connection */
        $connection = $container->get(Connection::class);

        $this->createHandler = new CreateBookingHandler($this->bookingRepository, new BookingReferentialValidator($connection), $this->unitOfWork);
        $assert = new AssertBookingServiceType($connection);
        $this->setDetailHandler = new SetBookingAccommodationDetailHandler($assert, $this->detailRepository, $this->unitOfWork);
        $this->addRoomHandler = new AddBookingHotelRoomHandler($assert, $this->roomRepository, $this->unitOfWork);
    }

    public function test_accommodation_detail_hotel_round_trip_nullable_accommodation_id(): void
    {
        $bookingId = $this->createBooking('hotel', 'HotelAcc');

        ($this->setDetailHandler)(new SetBookingAccommodationDetailCommand(
            bookingId: $bookingId,
            accommodationId: null,
            accommodationNameSnapshot: 'Hotel Non Réconcilié',
            boardType: 'half_board',
        ));

        $this->em->clear();

        $reloaded = $this->detailRepository->findByBookingId($bookingId);
        self::assertNotNull($reloaded);
        self::assertSame($bookingId, $reloaded->bookingId());
        self::assertNull($reloaded->accommodationId());
        self::assertSame('Hotel Non Réconcilié', $reloaded->accommodationNameSnapshot());
        self::assertSame('half_board', $reloaded->boardType());
    }

    public function test_accommodation_detail_rejected_for_non_hotel(): void
    {
        $bookingId = $this->createBooking('flight', 'FlightAcc');

        try {
            ($this->setDetailHandler)(new SetBookingAccommodationDetailCommand(
                bookingId: $bookingId,
                accommodationNameSnapshot: 'Should Fail',
            ));
            self::fail('Expected BookingServiceTypeMismatchException');
        } catch (BookingServiceTypeMismatchException $exception) {
            self::assertSame('booking.service_type_mismatch', $exception->errorCode());
            self::assertSame($bookingId, $exception->context()['booking_id']);
            self::assertSame('accommodation', $exception->context()['extension_code']);
            self::assertSame('flight', $exception->context()['actual_service_type']);
        }

        self::assertNull($this->detailRepository->findByBookingId($bookingId));
    }

    public function test_multiple_hotel_rooms_coexist(): void
    {
        $bookingId = $this->createBooking('hotel', 'MultiRoom');

        ($this->addRoomHandler)(new AddBookingHotelRoomCommand($bookingId, 'Chambre Double'));
        ($this->addRoomHandler)(new AddBookingHotelRoomCommand($bookingId, 'Chambre Familiale'));

        $this->em->clear();

        $rooms = $this->roomRepository->findByBookingId($bookingId);
        self::assertCount(2, $rooms);
        self::assertSame('Chambre Double', $rooms[0]->roomType());
        self::assertSame('Chambre Familiale', $rooms[1]->roomType());
        self::assertNotNull($rooms[0]->id());
        self::assertNotNull($rooms[1]->id());
        self::assertNotSame($rooms[0]->id(), $rooms[1]->id());
    }

    public function test_add_room_rejected_for_non_hotel(): void
    {
        $bookingId = $this->createBooking('transfer', 'TransferRoom');

        try {
            ($this->addRoomHandler)(new AddBookingHotelRoomCommand($bookingId, 'X'));
            self::fail('Expected BookingServiceTypeMismatchException');
        } catch (BookingServiceTypeMismatchException $exception) {
            self::assertSame('booking.service_type_mismatch', $exception->errorCode());
            self::assertSame('accommodation', $exception->context()['extension_code']);
            self::assertSame('transfer', $exception->context()['actual_service_type']);
        }

        self::assertSame([], $this->roomRepository->findByBookingId($bookingId));
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
            startDate: '2026-09-01',
            endDate: '2026-09-03',
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
            Email::fromString('ht.cust.'.$suffix.'@example.com'),
        );
        $office = PartyAccount::createOrganization(
            $label.' Off '.$suffix,
            Email::fromString('ht.off.'.$suffix.'@example.com'),
        );
        $this->accountRepository->save($customer);
        $this->unitOfWork->commit();
        $this->accountRepository->save($office);
        $this->unitOfWork->commit();

        $folder = BookingFolder::create(
            'HT-'.$label.'-'.$suffix,
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
