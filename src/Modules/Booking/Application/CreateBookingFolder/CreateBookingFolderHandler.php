<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\CreateBookingFolder;

use App\Modules\Booking\Domain\Entity\BookingFolder;
use App\Modules\Booking\Domain\Exception\BookingFolderReferenceCodeAlreadyUsedException;
use App\Modules\Booking\Domain\Repository\BookingFolderRepositoryInterface;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * Use case : créer un dossier — vérification explicite de l'unicité
 * reference_code AVANT écriture (uq_booking_folder_reference_code n'est
 * qu'un filet DB).
 *
 * Service invocable simple (__invoke) — PAS un #[AsMessageHandler] Messenger.
 */
final class CreateBookingFolderHandler
{
    public function __construct(
        private readonly BookingFolderRepositoryInterface $folderRepository,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function __invoke(CreateBookingFolderCommand $command): BookingFolder
    {
        if ($this->folderRepository->existsByReferenceCode($command->referenceCode)) {
            throw BookingFolderReferenceCodeAlreadyUsedException::forCode($command->referenceCode);
        }

        $folder = BookingFolder::create(
            $command->referenceCode,
            $command->partyAccountId,
            $command->officeAccountId,
        );
        $this->folderRepository->save($folder);
        $this->unitOfWork->commit();

        return $folder;
    }
}
