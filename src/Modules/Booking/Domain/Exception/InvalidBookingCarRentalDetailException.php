<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * Invariants sur booking_car_rental_detail (ex. dropoff_at < pickup_at).
 */
final class InvalidBookingCarRentalDetailException extends DomainException
{
    public function errorCode(): string
    {
        return 'booking_car_rental_detail.invalid_dates';
    }

    public static function dropoffBeforePickup(
        DateTimeImmutable $pickupAt,
        DateTimeImmutable $dropoffAt,
    ): self {
        return new self(
            'Car rental dropoffAt must be greater than or equal to pickupAt.',
            [
                'pickup_at' => $pickupAt->format(DateTimeInterface::ATOM),
                'dropoff_at' => $dropoffAt->format(DateTimeInterface::ATOM),
            ],
        );
    }
}
