<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;
use DateTimeImmutable;

/**
 * Violations d'invariants d'état sur l'agrégat Booking (ex. ck_booking_dates).
 */
final class InvalidBookingStateException extends DomainException
{
    public function errorCode(): string
    {
        return 'booking.invalid_dates';
    }

    public static function endDateBeforeStartDate(
        DateTimeImmutable $startDate,
        DateTimeImmutable $endDate,
    ): self {
        return new self(
            'Booking endDate must be greater than or equal to startDate.',
            [
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d'),
            ],
        );
    }
}
