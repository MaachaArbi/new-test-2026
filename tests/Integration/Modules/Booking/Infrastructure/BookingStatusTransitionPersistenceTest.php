<?php

declare(strict_types=1);

namespace App\Tests\Integration\Modules\Booking\Infrastructure;

use App\Modules\Booking\Application\CreateBooking\CreateBookingCommand;
use App\Modules\Booking\Application\BookingReferentialValidator;
use App\Modules\Booking\Application\CreateBooking\CreateBookingHandler;
use App\Modules\Booking\Application\TransitionBookingStatus\TransitionBookingStatusCommand;
use App\Modules\Booking\Application\TransitionBookingStatus\TransitionBookingStatusHandler;
use App\Modules\Booking\Domain\Entity\BookingFolder;
use App\Modules\Booking\Domain\Exception\BookingNotFoundException;
use App\Modules\Booking\Domain\Repository\BookingFolderRepositoryInterface;
use App\Modules\Booking\Domain\Repository\BookingRepositoryInterface;
use App\Modules\Party\Domain\Entity\PartyAccount;
use App\Modules\Party\Domain\Repository\PartyAccountRepositoryInterface;
use App\Shared\Domain\ValueObject\Email;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * PostgreSQL réel — transition status_code persistée.
 */
final class BookingStatusTransitionPersistenceTest extends KernelTestCase
{
    private UnitOfWork $unitOfWork;

    private EntityManagerInterface $em;

    private BookingRepositoryInterface $bookingRepository;

    private BookingFolderRepositoryInterface $folderRepository;

    private PartyAccountRepositoryInterface $accountRepository;

    private CreateBookingHandler $createHandler;

    private TransitionBookingStatusHandler $transitionHandler;

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
        $this->transitionHandler = new TransitionBookingStatusHandler($this->bookingRepository, $this->unitOfWork);
    }

    public function test_transition_round_trip(): void
    {
        $ctx = $this->seedContext('StatusRt');

        $booking = ($this->createHandler)(new CreateBookingCommand(
            folderId: $ctx['folderId'],
            serviceTypeCode: 'hotel',
            statusCode: 'draft',
            customerAccountId: $ctx['customerId'],
            supplierAccountId: null,
            officeAccountId: $ctx['officeId'],
            startDate: '2026-08-25',
            endDate: '2026-08-27',
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

        $id = (int) $booking->id();

        ($this->transitionHandler)(new TransitionBookingStatusCommand($id, 'cancelled'));
        $this->em->clear();

        $reloaded = $this->bookingRepository->findById($id);
        self::assertNotNull($reloaded);
        self::assertSame('cancelled', $reloaded->statusCode()->toString());

        // Reouverture depuis un statut final (pas de matrice)
        ($this->transitionHandler)(new TransitionBookingStatusCommand($id, 'confirmed'));
        $this->em->clear();

        $reopened = $this->bookingRepository->findById($id);
        self::assertNotNull($reopened);
        self::assertSame('confirmed', $reopened->statusCode()->toString());
    }

    public function test_transition_unknown_booking_raises_not_found(): void
    {
        try {
            ($this->transitionHandler)(new TransitionBookingStatusCommand(999_999_990, 'confirmed'));
            self::fail('Expected BookingNotFoundException');
        } catch (BookingNotFoundException $exception) {
            self::assertSame('booking.not_found', $exception->errorCode());
            self::assertSame(999_999_990, $exception->context()['id']);
        }
    }

    /**
     * @return array{folderId: int, customerId: int, officeId: int}
     */
    private function seedContext(string $label): array
    {
        $suffix = bin2hex(random_bytes(4));
        $customer = PartyAccount::createOrganization(
            $label.' Cust '.$suffix,
            Email::fromString('st.cust.'.$suffix.'@example.com'),
        );
        $office = PartyAccount::createOrganization(
            $label.' Off '.$suffix,
            Email::fromString('st.off.'.$suffix.'@example.com'),
        );
        $this->accountRepository->save($customer);
        $this->unitOfWork->commit();
        $this->accountRepository->save($office);
        $this->unitOfWork->commit();

        $folder = BookingFolder::create(
            'ST-'.$label.'-'.$suffix,
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
