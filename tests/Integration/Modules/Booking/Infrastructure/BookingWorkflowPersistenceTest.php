<?php

declare(strict_types=1);

namespace App\Tests\Integration\Modules\Booking\Infrastructure;

use App\Modules\Booking\Application\CreateBooking\CreateBookingCommand;
use App\Modules\Booking\Application\BookingReferentialValidator;
use App\Modules\Booking\Application\CreateBooking\CreateBookingHandler;
use App\Modules\Booking\Application\UpdateBookingWorkflow\UpdateBookingWorkflowCommand;
use App\Modules\Booking\Application\UpdateBookingWorkflow\UpdateBookingWorkflowHandler;
use App\Modules\Booking\Domain\Entity\BookingFolder;
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
 * PostgreSQL réel — mapping XML des colonnes workflow + persist après mutation.
 */
final class BookingWorkflowPersistenceTest extends KernelTestCase
{
    private UnitOfWork $unitOfWork;

    private EntityManagerInterface $em;

    private BookingRepositoryInterface $bookingRepository;

    private BookingFolderRepositoryInterface $folderRepository;

    private PartyAccountRepositoryInterface $accountRepository;

    private CreateBookingHandler $createHandler;

    private UpdateBookingWorkflowHandler $workflowHandler;

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
        $this->workflowHandler = new UpdateBookingWorkflowHandler($this->bookingRepository, $this->unitOfWork);
    }

    public function test_workflow_mutations_round_trip(): void
    {
        $ctx = $this->seedContext('WorkflowRt');

        $booking = ($this->createHandler)(new CreateBookingCommand(
            folderId: $ctx['folderId'],
            serviceTypeCode: 'hotel',
            statusCode: 'confirmed',
            customerAccountId: $ctx['customerId'],
            supplierAccountId: null,
            officeAccountId: $ctx['officeId'],
            startDate: '2026-08-20',
            endDate: '2026-08-22',
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

        ($this->workflowHandler)(new UpdateBookingWorkflowCommand(
            bookingId: $id,
            hasOnRequest: true,
            isOnRequest: true,
            hasAssignment: true,
            assignedAgentAccountId: $ctx['agentId'],
            hasLocked: true,
            isLocked: true,
            hasDisputed: true,
            isDisputed: true,
            hasSupplierStatusLabel: true,
            supplierStatusLabel: 'WAIT-SUPPLIER',
        ));

        $this->em->clear();

        $reloaded = $this->bookingRepository->findById($id);
        self::assertNotNull($reloaded);
        self::assertTrue($reloaded->isOnRequest());
        self::assertSame($ctx['agentId'], $reloaded->assignedAgentAccountId());
        self::assertNotNull($reloaded->assignedAt());
        self::assertTrue($reloaded->isLocked());
        self::assertTrue($reloaded->isDisputed());
        self::assertSame('WAIT-SUPPLIER', $reloaded->supplierStatusLabel());
        // status_code inchangé (hors périmètre mutations workflow)
        self::assertSame('confirmed', $reloaded->statusCode()->toString());

        ($this->workflowHandler)(new UpdateBookingWorkflowCommand(
            bookingId: $id,
            hasAssignment: true,
            assignedAgentAccountId: null,
            hasOnRequest: true,
            isOnRequest: false,
            hasLocked: true,
            isLocked: false,
            hasDisputed: true,
            isDisputed: false,
            hasSupplierStatusLabel: true,
            supplierStatusLabel: null,
        ));

        $this->em->clear();

        $cleared = $this->bookingRepository->findById($id);
        self::assertNotNull($cleared);
        self::assertFalse($cleared->isOnRequest());
        self::assertNull($cleared->assignedAgentAccountId());
        self::assertNull($cleared->assignedAt());
        self::assertFalse($cleared->isLocked());
        self::assertFalse($cleared->isDisputed());
        self::assertNull($cleared->supplierStatusLabel());
    }

    /**
     * @return array{folderId: int, customerId: int, officeId: int, agentId: int}
     */
    private function seedContext(string $label): array
    {
        $suffix = bin2hex(random_bytes(4));
        $customer = PartyAccount::createOrganization(
            $label.' Cust '.$suffix,
            Email::fromString('wf.cust.'.$suffix.'@example.com'),
        );
        $office = PartyAccount::createOrganization(
            $label.' Off '.$suffix,
            Email::fromString('wf.off.'.$suffix.'@example.com'),
        );
        $agent = PartyAccount::createPerson(
            $label.' Agent '.$suffix,
            Email::fromString('wf.agent.'.$suffix.'@example.com'),
        );
        $this->accountRepository->save($customer);
        $this->unitOfWork->commit();
        $this->accountRepository->save($office);
        $this->unitOfWork->commit();
        $this->accountRepository->save($agent);
        $this->unitOfWork->commit();

        $folder = BookingFolder::create(
            'WF-'.$label.'-'.$suffix,
            (int) $customer->id(),
            (int) $office->id(),
        );
        $this->folderRepository->save($folder);
        $this->unitOfWork->commit();

        return [
            'folderId' => (int) $folder->id(),
            'customerId' => (int) $customer->id(),
            'officeId' => (int) $office->id(),
            'agentId' => (int) $agent->id(),
        ];
    }
}
