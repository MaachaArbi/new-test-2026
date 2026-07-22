<?php

declare(strict_types=1);

namespace App\Tests\Integration\Modules\Booking\Infrastructure;

use App\Modules\Booking\Application\CreateBooking\CreateBookingCommand;
use App\Modules\Booking\Application\BookingReferentialValidator;
use App\Modules\Booking\Application\CreateBooking\CreateBookingHandler;
use App\Modules\Booking\Application\CreateBookingTraveler\CreateBookingTravelerCommand;
use App\Modules\Booking\Application\CreateBookingTraveler\CreateBookingTravelerHandler;
use App\Modules\Booking\Domain\Entity\BookingFolder;
use App\Modules\Booking\Domain\Exception\BookingTravelerPaxLeaderAlreadySetException;
use App\Modules\Booking\Domain\Repository\BookingFolderRepositoryInterface;
use App\Modules\Booking\Domain\Repository\BookingRepositoryInterface;
use App\Modules\Booking\Domain\Repository\BookingTravelerRepositoryInterface;
use App\Modules\Party\Domain\Entity\PartyAccount;
use App\Modules\Party\Domain\Repository\PartyAccountRepositoryInterface;
use App\Shared\Domain\ValueObject\Email;
use Doctrine\DBAL\Connection;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * PostgreSQL réel — booking_traveler snapshot + contrainte pax leader.
 */
final class BookingTravelerPersistenceTest extends KernelTestCase
{
    private UnitOfWork $unitOfWork;

    private EntityManagerInterface $em;

    private BookingRepositoryInterface $bookingRepository;

    private BookingFolderRepositoryInterface $folderRepository;

    private PartyAccountRepositoryInterface $accountRepository;

    private BookingTravelerRepositoryInterface $travelerRepository;

    private CreateBookingHandler $createBookingHandler;

    private CreateBookingTravelerHandler $createTravelerHandler;

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

        /** @var BookingTravelerRepositoryInterface $travelerRepository */
        $travelerRepository = $container->get(BookingTravelerRepositoryInterface::class);
        $this->travelerRepository = $travelerRepository;

        /** @var Connection $connection */


        $connection = $container->get(Connection::class);


        $this->createBookingHandler = new CreateBookingHandler($this->bookingRepository, new BookingReferentialValidator($connection), $this->unitOfWork);
        $this->createTravelerHandler = new CreateBookingTravelerHandler($this->travelerRepository, $this->unitOfWork);
    }

    public function test_simple_traveler_full_round_trip(): void
    {
        $bookingId = $this->createBooking('TravelerFull');

        ($this->createTravelerHandler)(new CreateBookingTravelerCommand(
            bookingId: $bookingId,
            firstName: 'Amine',
            lastName: 'Ben Ali',
            partyAccountId: null,
            civility: 'Mr',
            phone: '+21620000000',
            email: 'amine.not-strict@example.com',
            age: 34,
            birthDate: new DateTimeImmutable('1990-05-12'),
            birthPlace: 'KASSERINE',
            nationalityCountryId: 788,
            residenceCountryId: 788,
            documentType: 'passport',
            documentNumber: 'P1234567',
            drivingLicenseNumber: null,
            isPaxLeader: false,
            ticketNumber: '124-2445435844',
            pnr: 'ABC123',
            travelClass: 'B',
        ));

        $this->em->clear();

        $travelers = $this->travelerRepository->findByBookingId($bookingId);
        self::assertCount(1, $travelers);
        $t = $travelers[0];
        self::assertNotNull($t->id());
        self::assertSame($bookingId, $t->bookingId());
        self::assertNull($t->hotelRoomId());
        self::assertNull($t->partyAccountId());
        self::assertSame('Amine', $t->firstName());
        self::assertSame('Ben Ali', $t->lastName());
        self::assertSame('Mr', $t->civility());
        self::assertSame('+21620000000', $t->phone());
        self::assertSame('amine.not-strict@example.com', $t->email());
        self::assertSame(34, $t->age());
        self::assertSame('1990-05-12', $t->birthDate()?->format('Y-m-d'));
        self::assertSame('KASSERINE', $t->birthPlace());
        self::assertSame(788, $t->nationalityCountryId());
        self::assertSame(788, $t->residenceCountryId());
        self::assertSame('passport', $t->documentType());
        self::assertSame('P1234567', $t->documentNumber());
        self::assertNull($t->drivingLicenseNumber());
        self::assertFalse($t->isPaxLeader());
        self::assertSame('124-2445435844', $t->ticketNumber());
        self::assertSame('ABC123', $t->pnr());
        self::assertSame('B', $t->travelClass());
    }

    public function test_two_travelers_without_pax_leader_coexist(): void
    {
        $bookingId = $this->createBooking('TwoNoLeader');

        ($this->createTravelerHandler)(new CreateBookingTravelerCommand(
            bookingId: $bookingId,
            firstName: 'A',
            lastName: 'One',
        ));
        ($this->createTravelerHandler)(new CreateBookingTravelerCommand(
            bookingId: $bookingId,
            firstName: 'B',
            lastName: 'Two',
        ));

        $this->em->clear();

        self::assertCount(2, $this->travelerRepository->findByBookingId($bookingId));
        self::assertFalse($this->travelerRepository->hasActivePaxLeader($bookingId));
    }

    public function test_first_pax_leader_ok_second_rejected_before_sql(): void
    {
        $bookingId = $this->createBooking('PaxLeaderDup');

        ($this->createTravelerHandler)(new CreateBookingTravelerCommand(
            bookingId: $bookingId,
            firstName: 'Leader',
            lastName: 'One',
            isPaxLeader: true,
        ));

        try {
            ($this->createTravelerHandler)(new CreateBookingTravelerCommand(
                bookingId: $bookingId,
                firstName: 'Leader',
                lastName: 'Two',
                isPaxLeader: true,
            ));
            self::fail('Expected BookingTravelerPaxLeaderAlreadySetException');
        } catch (BookingTravelerPaxLeaderAlreadySetException $exception) {
            self::assertSame('booking_traveler.pax_leader_already_set', $exception->errorCode());
            self::assertSame($bookingId, $exception->context()['booking_id']);
        }

        $this->em->clear();
        self::assertCount(1, $this->travelerRepository->findByBookingId($bookingId));
    }

    public function test_pax_leader_constraint_is_per_booking_not_global(): void
    {
        $bookingA = $this->createBooking('PaxA');
        $bookingB = $this->createBooking('PaxB');

        ($this->createTravelerHandler)(new CreateBookingTravelerCommand(
            bookingId: $bookingA,
            firstName: 'Leader',
            lastName: 'A',
            isPaxLeader: true,
        ));
        ($this->createTravelerHandler)(new CreateBookingTravelerCommand(
            bookingId: $bookingB,
            firstName: 'Leader',
            lastName: 'B',
            isPaxLeader: true,
        ));

        $this->em->clear();

        self::assertTrue($this->travelerRepository->hasActivePaxLeader($bookingA));
        self::assertTrue($this->travelerRepository->hasActivePaxLeader($bookingB));
        self::assertCount(1, $this->travelerRepository->findByBookingId($bookingA));
        self::assertCount(1, $this->travelerRepository->findByBookingId($bookingB));
    }

    public function test_age_and_birth_date_both_allowed(): void
    {
        $bookingId = $this->createBooking('AgeAndBirth');

        ($this->createTravelerHandler)(new CreateBookingTravelerCommand(
            bookingId: $bookingId,
            firstName: 'Child',
            lastName: 'Both',
            age: 6,
            birthDate: new DateTimeImmutable('2018-03-01'),
        ));

        $this->em->clear();

        $t = $this->travelerRepository->findByBookingId($bookingId)[0];
        self::assertSame(6, $t->age());
        self::assertSame('2018-03-01', $t->birthDate()?->format('Y-m-d'));
    }

    private function createBooking(string $label): int
    {
        $ctx = $this->seedFolderContext($label);

        $booking = ($this->createBookingHandler)(new CreateBookingCommand(
            folderId: $ctx['folderId'],
            serviceTypeCode: 'flight',
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
            Email::fromString('tv.cust.'.$suffix.'@example.com'),
        );
        $office = PartyAccount::createOrganization(
            $label.' Off '.$suffix,
            Email::fromString('tv.off.'.$suffix.'@example.com'),
        );
        $this->accountRepository->save($customer);
        $this->unitOfWork->commit();
        $this->accountRepository->save($office);
        $this->unitOfWork->commit();

        $folder = BookingFolder::create(
            'TV-'.$label.'-'.$suffix,
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
