<?php

declare(strict_types=1);

namespace App\Tests\Integration\Modules\Booking\Infrastructure;

use App\Modules\Booking\Application\CreateBookingFolder\CreateBookingFolderCommand;
use App\Modules\Booking\Application\CreateBookingFolder\CreateBookingFolderHandler;
use App\Modules\Booking\Domain\Exception\BookingFolderReferenceCodeAlreadyUsedException;
use App\Modules\Booking\Domain\Repository\BookingFolderRepositoryInterface;
use App\Modules\Party\Domain\Entity\PartyAccount;
use App\Modules\Party\Domain\Repository\PartyAccountRepositoryInterface;
use App\Shared\Domain\ValueObject\Email;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * PostgreSQL réel uniquement — jamais SQLite.
 */
final class BookingFolderPersistenceTest extends KernelTestCase
{
    private UnitOfWork $unitOfWork;

    private EntityManagerInterface $em;

    private BookingFolderRepositoryInterface $folderRepository;

    private PartyAccountRepositoryInterface $accountRepository;

    private CreateBookingFolderHandler $createHandler;

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

        /** @var BookingFolderRepositoryInterface $folderRepository */
        $folderRepository = $container->get(BookingFolderRepositoryInterface::class);
        $this->folderRepository = $folderRepository;

        /** @var PartyAccountRepositoryInterface $accountRepository */
        $accountRepository = $container->get(PartyAccountRepositoryInterface::class);
        $this->accountRepository = $accountRepository;

        $this->createHandler = new CreateBookingFolderHandler($this->folderRepository, $this->unitOfWork);
    }

    public function test_round_trip_persists_and_reloads_mapped_fields(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $party = $this->createOrganization('Folder Client '.$suffix);
        $office = $this->createOrganization('Folder Office '.$suffix);
        $referenceCode = 'RT-'.$suffix;

        $folder = ($this->createHandler)(new CreateBookingFolderCommand(
            $referenceCode,
            (int) $party->id(),
            (int) $office->id(),
        ));

        $id = $folder->id();
        $publicId = $folder->publicId()->toString();
        self::assertNotNull($id);

        $this->em->clear();

        $reloaded = $this->folderRepository->findById($id);
        self::assertNotNull($reloaded);
        self::assertSame($id, $reloaded->id());
        self::assertSame($publicId, $reloaded->publicId()->toString());
        self::assertSame($referenceCode, $reloaded->referenceCode());
        self::assertSame($party->id(), $reloaded->partyAccountId());
        self::assertSame($office->id(), $reloaded->officeAccountId());
        self::assertFalse($reloaded->isDeleted());
        self::assertNull($reloaded->deletedAt());
    }

    public function test_duplicate_reference_code_is_rejected_before_sql(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $party = $this->createOrganization('Folder Dup Client '.$suffix);
        $office = $this->createOrganization('Folder Dup Office '.$suffix);
        $referenceCode = 'DUP-'.$suffix;

        ($this->createHandler)(new CreateBookingFolderCommand(
            $referenceCode,
            (int) $party->id(),
            (int) $office->id(),
        ));

        try {
            ($this->createHandler)(new CreateBookingFolderCommand(
                $referenceCode,
                (int) $party->id(),
                (int) $office->id(),
            ));
            self::fail('Expected BookingFolderReferenceCodeAlreadyUsedException');
        } catch (BookingFolderReferenceCodeAlreadyUsedException $exception) {
            self::assertSame('booking_folder.reference_code_already_used', $exception->errorCode());
            self::assertSame(['reference_code' => $referenceCode], $exception->context());
        }
    }

    public function test_soft_delete_hides_from_find_by_public_id(): void
    {
        $suffix = bin2hex(random_bytes(4));
        $party = $this->createOrganization('Folder Del Client '.$suffix);
        $office = $this->createOrganization('Folder Del Office '.$suffix);

        $folder = ($this->createHandler)(new CreateBookingFolderCommand(
            'DEL-'.$suffix,
            (int) $party->id(),
            (int) $office->id(),
        ));
        $publicId = $folder->publicId();

        $folder->delete();
        $this->folderRepository->delete($folder);
        $this->unitOfWork->commit();

        $this->em->clear();

        self::assertNull($this->folderRepository->findByPublicId($publicId));
        self::assertNotNull($this->folderRepository->findById((int) $folder->id()));
    }

    private function createOrganization(string $label): PartyAccount
    {
        $suffix = bin2hex(random_bytes(4));
        $account = PartyAccount::createOrganization(
            $label.' '.$suffix,
            Email::fromString('booking.folder.'.$suffix.'@example.com'),
        );
        $this->accountRepository->save($account);
        $this->unitOfWork->commit();

        return $account;
    }
}
