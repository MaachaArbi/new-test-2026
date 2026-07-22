<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\CreateBookingFolder;

/**
 * Commande de création d'un booking_folder.
 */
final readonly class CreateBookingFolderCommand
{
    public function __construct(
        public string $referenceCode,
        public int $partyAccountId,
        public int $officeAccountId,
    ) {
    }
}
