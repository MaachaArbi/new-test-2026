<?php

declare(strict_types=1);

namespace App\Tests\Integration\Modules\Booking\Infrastructure;

use App\Modules\Booking\Application\AddBookingCancellationTier\AddBookingCancellationTierCommand;
use App\Modules\Booking\Application\AddBookingCancellationTier\AddBookingCancellationTierHandler;
use App\Modules\Booking\Application\AddBookingHotelRoom\AddBookingHotelRoomCommand;
use App\Modules\Booking\Application\AddBookingHotelRoom\AddBookingHotelRoomHandler;
use App\Modules\Booking\Application\AssertBookingServiceType;
use App\Modules\Booking\Application\CreateBooking\CreateBookingCommand;
use App\Modules\Booking\Application\BookingReferentialValidator;
use App\Modules\Booking\Application\CreateBooking\CreateBookingHandler;
use App\Modules\Booking\Application\CreateBookingCancellationPolicy\CreateBookingCancellationPolicyCommand;
use App\Modules\Booking\Application\CreateBookingCancellationPolicy\CreateBookingCancellationPolicyHandler;
use App\Modules\Booking\Domain\Entity\BookingFolder;
use App\Modules\Booking\Domain\Exception\BookingCancellationPolicyAlreadyExistsException;
use App\Modules\Booking\Domain\Exception\BookingCancellationRoomMismatchException;
use App\Modules\Booking\Domain\Exception\InvalidBookingCancellationTierException;
use App\Modules\Booking\Domain\Repository\BookingCancellationPolicyRepositoryInterface;
use App\Modules\Booking\Domain\Repository\BookingCancellationTierRepositoryInterface;
use App\Modules\Booking\Domain\Repository\BookingFolderRepositoryInterface;
use App\Modules\Booking\Domain\Repository\BookingHotelRoomRepositoryInterface;
use App\Modules\Booking\Domain\Repository\BookingRepositoryInterface;
use App\Modules\Booking\Domain\ValueObject\PenaltyType;
use App\Modules\Party\Domain\Entity\PartyAccount;
use App\Modules\Party\Domain\Repository\PartyAccountRepositoryInterface;
use App\Shared\Domain\ValueObject\Email;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * PostgreSQL réel — booking_cancellation_policy + booking_cancellation_tier.
 */
final class BookingCancellationPolicyPersistenceTest extends KernelTestCase
{
    private UnitOfWork $unitOfWork;

    private EntityManagerInterface $em;

    private BookingRepositoryInterface $bookingRepository;

    private BookingFolderRepositoryInterface $folderRepository;

    private PartyAccountRepositoryInterface $accountRepository;

    private BookingHotelRoomRepositoryInterface $roomRepository;

    private BookingCancellationPolicyRepositoryInterface $policyRepository;

    private BookingCancellationTierRepositoryInterface $tierRepository;

    private CreateBookingHandler $createBookingHandler;

    private AddBookingHotelRoomHandler $addRoomHandler;

    private CreateBookingCancellationPolicyHandler $createPolicyHandler;

    private AddBookingCancellationTierHandler $addTierHandler;

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

        /** @var BookingHotelRoomRepositoryInterface $roomRepository */
        $roomRepository = $container->get(BookingHotelRoomRepositoryInterface::class);
        $this->roomRepository = $roomRepository;

        /** @var BookingCancellationPolicyRepositoryInterface $policyRepository */
        $policyRepository = $container->get(BookingCancellationPolicyRepositoryInterface::class);
        $this->policyRepository = $policyRepository;

        /** @var BookingCancellationTierRepositoryInterface $tierRepository */
        $tierRepository = $container->get(BookingCancellationTierRepositoryInterface::class);
        $this->tierRepository = $tierRepository;

        /** @var Connection $connection */
        $connection = $container->get(Connection::class);

        $this->createBookingHandler = new CreateBookingHandler($this->bookingRepository, new BookingReferentialValidator($connection), $this->unitOfWork);
        $assert = new AssertBookingServiceType($connection);
        $this->addRoomHandler = new AddBookingHotelRoomHandler($assert, $this->roomRepository, $this->unitOfWork);
        $this->createPolicyHandler = new CreateBookingCancellationPolicyHandler(
            $this->policyRepository,
            $this->roomRepository,

            $this->unitOfWork
);
        $this->addTierHandler = new AddBookingCancellationTierHandler(
            $this->policyRepository,
            $this->tierRepository,

            $this->unitOfWork
);
    }

    public function test_whole_booking_policy_round_trip_and_uniqueness(): void
    {
        $bookingId = $this->createBooking('flight', 'CancelWhole');

        $policy = ($this->createPolicyHandler)(new CreateBookingCancellationPolicyCommand($bookingId));
        self::assertNotNull($policy->id());
        self::assertNull($policy->roomId());

        $this->em->clear();

        $reloaded = $this->policyRepository->findByBookingId($bookingId);
        self::assertNotNull($reloaded);
        self::assertSame($policy->id(), $reloaded->id());
        self::assertNull($reloaded->roomId());

        try {
            ($this->createPolicyHandler)(new CreateBookingCancellationPolicyCommand($bookingId));
            self::fail('Expected BookingCancellationPolicyAlreadyExistsException');
        } catch (BookingCancellationPolicyAlreadyExistsException $exception) {
            self::assertSame('booking_cancellation_policy.already_exists', $exception->errorCode());
            self::assertSame($bookingId, $exception->context()['booking_id']);
        }
    }

    public function test_room_policy_rejects_room_from_other_booking(): void
    {
        $bookingA = $this->createBooking('hotel', 'CancelRoomA');
        $bookingB = $this->createBooking('hotel', 'CancelRoomB');

        $roomOnB = ($this->addRoomHandler)(new AddBookingHotelRoomCommand($bookingB, 'Double'));
        self::assertNotNull($roomOnB->id());

        try {
            ($this->createPolicyHandler)(new CreateBookingCancellationPolicyCommand(
                bookingId: $bookingA,
                roomId: $roomOnB->id(),
            ));
            self::fail('Expected BookingCancellationRoomMismatchException');
        } catch (BookingCancellationRoomMismatchException $exception) {
            self::assertSame('booking_cancellation_policy.room_mismatch', $exception->errorCode());
            self::assertSame($bookingA, $exception->context()['booking_id']);
            self::assertSame($roomOnB->id(), $exception->context()['room_id']);
        }
    }

    public function test_room_policy_round_trip_and_coexists_with_whole_booking(): void
    {
        $bookingId = $this->createBooking('hotel', 'CancelBoth');
        $room = ($this->addRoomHandler)(new AddBookingHotelRoomCommand($bookingId, 'Suite'));
        self::assertNotNull($room->id());

        $whole = ($this->createPolicyHandler)(new CreateBookingCancellationPolicyCommand($bookingId));
        $perRoom = ($this->createPolicyHandler)(new CreateBookingCancellationPolicyCommand(
            bookingId: $bookingId,
            roomId: $room->id(),
        ));

        self::assertNotNull($whole->id());
        self::assertNotNull($perRoom->id());
        self::assertNotSame($whole->id(), $perRoom->id());

        $this->em->clear();

        self::assertNotNull($this->policyRepository->findByBookingId($bookingId));
        $byRoom = $this->policyRepository->findByRoomId((int) $room->id());
        self::assertNotNull($byRoom);
        self::assertSame($room->id(), $byRoom->roomId());
    }

    public function test_multiple_tiers_ordered_by_sort_order(): void
    {
        $bookingId = $this->createBooking('transfer', 'CancelTiers');
        $policy = ($this->createPolicyHandler)(new CreateBookingCancellationPolicyCommand($bookingId));
        self::assertNotNull($policy->id());

        ($this->addTierHandler)(new AddBookingCancellationTierCommand(
            policyId: (int) $policy->id(),
            daysBeforeStart: 7,
            penaltyType: PenaltyType::Percentage,
            penaltyValue: '100',
            sortOrder: 2,
        ));
        ($this->addTierHandler)(new AddBookingCancellationTierCommand(
            policyId: (int) $policy->id(),
            daysBeforeStart: 30,
            penaltyType: PenaltyType::Free,
            thresholdTime: '15:00:00',
            sortOrder: 0,
        ));
        ($this->addTierHandler)(new AddBookingCancellationTierCommand(
            policyId: (int) $policy->id(),
            daysBeforeStart: 15,
            penaltyType: PenaltyType::Percentage,
            penaltyValue: '30',
            sortOrder: 1,
        ));

        $this->em->clear();

        $tiers = $this->tierRepository->findByPolicyId((int) $policy->id());
        self::assertCount(3, $tiers);
        self::assertSame(0, $tiers[0]->sortOrder());
        self::assertSame(PenaltyType::Free, $tiers[0]->penaltyType());
        self::assertNull($tiers[0]->penaltyValue());
        self::assertSame('15:00:00', $tiers[0]->thresholdTime());
        self::assertSame(1, $tiers[1]->sortOrder());
        self::assertSame('30.000', $tiers[1]->penaltyValue());
        self::assertSame(2, $tiers[2]->sortOrder());
        self::assertSame('100.000', $tiers[2]->penaltyValue());
    }

    public function test_free_with_value_rejected_by_domain_via_handler(): void
    {
        $bookingId = $this->createBooking('train', 'CancelFreeBad');
        $policy = ($this->createPolicyHandler)(new CreateBookingCancellationPolicyCommand($bookingId));
        self::assertNotNull($policy->id());

        try {
            ($this->addTierHandler)(new AddBookingCancellationTierCommand(
                policyId: (int) $policy->id(),
                daysBeforeStart: 30,
                penaltyType: PenaltyType::Free,
                penaltyValue: '10',
            ));
            self::fail('Expected InvalidBookingCancellationTierException');
        } catch (InvalidBookingCancellationTierException $exception) {
            self::assertSame('booking_cancellation_tier.invalid_penalty', $exception->errorCode());
        }

        self::assertSame([], $this->tierRepository->findByPolicyId((int) $policy->id()));
    }

    public function test_percentage_above_100_rejected_by_domain_via_handler(): void
    {
        $bookingId = $this->createBooking('maritime', 'CancelPctBad');
        $policy = ($this->createPolicyHandler)(new CreateBookingCancellationPolicyCommand($bookingId));
        self::assertNotNull($policy->id());

        try {
            ($this->addTierHandler)(new AddBookingCancellationTierCommand(
                policyId: (int) $policy->id(),
                daysBeforeStart: 3,
                penaltyType: PenaltyType::Percentage,
                penaltyValue: '150',
            ));
            self::fail('Expected InvalidBookingCancellationTierException');
        } catch (InvalidBookingCancellationTierException $exception) {
            self::assertSame('booking_cancellation_tier.invalid_penalty', $exception->errorCode());
        }
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
            startDate: '2026-08-01',
            endDate: '2026-08-05',
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
            Email::fromString('cp.cust.'.$suffix.'@example.com'),
        );
        $office = PartyAccount::createOrganization(
            $label.' Off '.$suffix,
            Email::fromString('cp.off.'.$suffix.'@example.com'),
        );
        $this->accountRepository->save($customer);
        $this->unitOfWork->commit();
        $this->accountRepository->save($office);
        $this->unitOfWork->commit();

        $folder = BookingFolder::create(
            'CP-'.$label.'-'.$suffix,
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
