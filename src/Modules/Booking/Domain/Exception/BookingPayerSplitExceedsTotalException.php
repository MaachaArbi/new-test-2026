<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

/**
 * Somme des splits actifs + nouveau montant dépasse total_vente_amount.
 */
final class BookingPayerSplitExceedsTotalException extends DomainException
{
    public function errorCode(): string
    {
        return 'booking_payer_split.exceeds_total';
    }

    public static function forBooking(
        int $bookingId,
        int $alreadyAllocatedMinor,
        int $requestedMinor,
        int $allowedTotalMinor,
    ): self {
        return new self(
            sprintf(
                'Booking %d payer split would exceed total_vente_amount: already=%d + requested=%d > allowed=%d.',
                $bookingId,
                $alreadyAllocatedMinor,
                $requestedMinor,
                $allowedTotalMinor,
            ),
            [
                'booking_id' => $bookingId,
                'already_allocated_minor' => $alreadyAllocatedMinor,
                'requested_minor' => $requestedMinor,
                'allowed_total_minor' => $allowedTotalMinor,
            ],
        );
    }
}
