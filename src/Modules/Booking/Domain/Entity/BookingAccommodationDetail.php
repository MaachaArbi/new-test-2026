<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Entity;

/**
 * Extension 1-1 booking_accommodation_detail (PK = booking_id).
 * Spécifique service_type=hotel. Pas de setters — corrections hors vague.
 * board_type_snapshot = libellé commercial fournisseur figé à la vente (texte libre).
 */
final class BookingAccommodationDetail
{
    private function __construct(
        private int $bookingId,
        private ?int $accommodationId,
        private ?string $accommodationNameSnapshot,
        private ?string $boardTypeSnapshot,
    ) {
    }

    public static function create(
        int $bookingId,
        ?int $accommodationId,
        ?string $accommodationNameSnapshot,
        ?string $boardTypeSnapshot,
    ): self {
        return new self(
            $bookingId,
            $accommodationId,
            $accommodationNameSnapshot,
            $boardTypeSnapshot,
        );
    }

    public function bookingId(): int
    {
        return $this->bookingId;
    }

    public function accommodationId(): ?int
    {
        return $this->accommodationId;
    }

    public function accommodationNameSnapshot(): ?string
    {
        return $this->accommodationNameSnapshot;
    }

    public function boardTypeSnapshot(): ?string
    {
        return $this->boardTypeSnapshot;
    }
}
