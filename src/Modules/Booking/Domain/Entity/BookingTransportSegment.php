<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Entity;

use App\Modules\Booking\Domain\Exception\InvalidBookingTransportSegmentException;
use DateTimeImmutable;

/**
 * Tronçon transport (booking_transport_segment) — collection 1-N.
 * Services : flight / train / maritime / transfer.
 * Pas d'update dans cette vague (comme booking_hotel_room).
 */
final class BookingTransportSegment
{
    private function __construct(
        private ?int $id,
        private int $bookingId,
        private int $sequenceNumber,
        private ?string $carrierCode,
        private DateTimeImmutable $departureAt,
        private DateTimeImmutable $arrivalAt,
        private ?string $departureLocation,
        private ?string $arrivalLocation,
    ) {
    }

    public static function create(
        int $bookingId,
        DateTimeImmutable $departureAt,
        DateTimeImmutable $arrivalAt,
        int $sequenceNumber = 1,
        ?string $carrierCode = null,
        ?string $departureLocation = null,
        ?string $arrivalLocation = null,
    ): self {
        if ($arrivalAt < $departureAt) {
            throw InvalidBookingTransportSegmentException::arrivalBeforeDeparture(
                $departureAt,
                $arrivalAt,
            );
        }

        return new self(
            id: null,
            bookingId: $bookingId,
            sequenceNumber: $sequenceNumber,
            carrierCode: $carrierCode,
            departureAt: $departureAt,
            arrivalAt: $arrivalAt,
            departureLocation: $departureLocation,
            arrivalLocation: $arrivalLocation,
        );
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function bookingId(): int
    {
        return $this->bookingId;
    }

    public function sequenceNumber(): int
    {
        return $this->sequenceNumber;
    }

    public function carrierCode(): ?string
    {
        return $this->carrierCode;
    }

    public function departureAt(): DateTimeImmutable
    {
        return $this->departureAt;
    }

    public function arrivalAt(): DateTimeImmutable
    {
        return $this->arrivalAt;
    }

    public function departureLocation(): ?string
    {
        return $this->departureLocation;
    }

    public function arrivalLocation(): ?string
    {
        return $this->arrivalLocation;
    }
}
