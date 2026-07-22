<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Entity;

/**
 * Barème d'annulation (booking_cancellation_policy) — agrégat.
 * room_id NULL = toute la réservation ; renseigné = par chambre (cas hôtel).
 * Pas d'update dans cette vague.
 */
final class BookingCancellationPolicy
{
    private function __construct(
        private ?int $id,
        private int $bookingId,
        private ?int $roomId,
    ) {
    }

    public static function create(int $bookingId, ?int $roomId = null): self
    {
        return new self(
            id: null,
            bookingId: $bookingId,
            roomId: $roomId,
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

    public function roomId(): ?int
    {
        return $this->roomId;
    }
}
