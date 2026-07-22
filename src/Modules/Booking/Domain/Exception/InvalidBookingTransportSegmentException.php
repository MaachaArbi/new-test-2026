<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;
use DateTimeImmutable;

/**
 * Invariants sur un tronçon transport (ex. arrival_at < departure_at).
 */
final class InvalidBookingTransportSegmentException extends DomainException
{
    public function errorCode(): string
    {
        return 'booking_transport_segment.invalid_dates';
    }

    public static function arrivalBeforeDeparture(
        DateTimeImmutable $departureAt,
        DateTimeImmutable $arrivalAt,
    ): self {
        return new self(
            'Transport segment arrivalAt must be greater than or equal to departureAt.',
            [
                'departure_at' => $departureAt->format(\DateTimeInterface::ATOM),
                'arrival_at' => $arrivalAt->format(\DateTimeInterface::ATOM),
            ],
        );
    }
}
