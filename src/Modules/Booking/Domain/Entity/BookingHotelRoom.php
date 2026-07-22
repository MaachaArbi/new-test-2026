<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Entity;

/**
 * Chambre hôtel (booking_hotel_room) — collection 1-N, pas d'historisation.
 * Pas de valid_from/valid_to ni deleted_at dans le schéma : une chambre
 * ajoutée reste ; suppression = vague séparée si besoin réel.
 */
final class BookingHotelRoom
{
    private function __construct(
        private ?int $id,
        private int $bookingId,
        private ?string $roomType,
    ) {
    }

    public static function create(int $bookingId, ?string $roomType): self
    {
        return new self(
            id: null,
            bookingId: $bookingId,
            roomType: $roomType,
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

    public function roomType(): ?string
    {
        return $this->roomType;
    }
}
