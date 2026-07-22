<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Repository;

use App\Modules\Booking\Domain\Entity\BookingCancellationPolicy;

interface BookingCancellationPolicyRepositoryInterface
{
    public function findById(int $id): ?BookingCancellationPolicy;

    /** ADR-003 : existence DBAL (pas de find() pour un simple null-check). */
    public function existsById(int $id): bool;

    /**
     * Politique "toute la réservation" (room_id IS NULL) pour ce booking, si elle existe.
     */
    public function findByBookingId(int $bookingId): ?BookingCancellationPolicy;

    public function findByRoomId(int $roomId): ?BookingCancellationPolicy;

    public function existsForBooking(int $bookingId): bool;

    public function existsForRoom(int $roomId): bool;

    public function save(BookingCancellationPolicy $policy): void;
}
