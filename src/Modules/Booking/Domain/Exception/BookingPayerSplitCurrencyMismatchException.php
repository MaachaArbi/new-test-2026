<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

/**
 * Devise du split ≠ devise vente du booking.
 */
final class BookingPayerSplitCurrencyMismatchException extends DomainException
{
    public function errorCode(): string
    {
        return 'booking_payer_split.currency_mismatch';
    }

    public static function forBooking(int $bookingId, string $expected, string $actual): self
    {
        return new self(
            sprintf(
                'Booking %d payer split currency "%s" does not match vente currency "%s".',
                $bookingId,
                $actual,
                $expected,
            ),
            [
                'booking_id' => $bookingId,
                'expected_currency' => $expected,
                'actual_currency' => $actual,
            ],
        );
    }
}
