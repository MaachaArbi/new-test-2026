<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

/**
 * Doublon actif (booking_id, payer_account_id) — miroir uq_booking_payer_split_active.
 */
final class BookingPayerSplitAlreadyActiveException extends DomainException
{
    public function errorCode(): string
    {
        return 'booking_payer_split.already_active';
    }

    public static function forBookingAndPayer(int $bookingId, int $payerAccountId): self
    {
        return new self(
            sprintf(
                'Booking %d already has an active payer split for account %d.',
                $bookingId,
                $payerAccountId,
            ),
            [
                'booking_id' => $bookingId,
                'payer_account_id' => $payerAccountId,
            ],
        );
    }
}
