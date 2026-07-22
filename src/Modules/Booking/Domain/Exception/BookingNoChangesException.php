<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

/**
 * Update workflow sans aucun champ applicable.
 */
final class BookingNoChangesException extends DomainException
{
    public function errorCode(): string
    {
        return 'booking.no_changes_provided';
    }

    public static function create(): self
    {
        return new self('No changes provided for booking workflow update.');
    }
}
