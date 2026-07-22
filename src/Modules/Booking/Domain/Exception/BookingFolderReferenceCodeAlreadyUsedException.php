<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

/**
 * reference_code déjà utilisé (unicité globale uq_booking_folder_reference_code).
 */
final class BookingFolderReferenceCodeAlreadyUsedException extends DomainException
{
    public function errorCode(): string
    {
        return 'booking_folder.reference_code_already_used';
    }

    public static function forCode(string $referenceCode): self
    {
        return new self(
            sprintf('Booking folder reference code "%s" is already used.', $referenceCode),
            ['reference_code' => $referenceCode],
        );
    }
}
