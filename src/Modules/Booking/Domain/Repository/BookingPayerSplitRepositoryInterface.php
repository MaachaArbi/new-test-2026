<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Repository;

use App\Modules\Booking\Domain\Entity\BookingPayerSplit;

interface BookingPayerSplitRepositoryInterface
{
    public function findById(int $id): ?BookingPayerSplit;

    /**
     * @return list<BookingPayerSplit>
     */
    public function findByBookingId(int $bookingId, bool $activeOnly = true): array;

    /**
     * SUM(amount) des lignes actives — ADR-003 DBAL, jamais collection PHP.
     */
    public function sumActiveAmountForBooking(int $bookingId): int;

    /**
     * Existence active — miroir uq_booking_payer_split_active (ADR-003 DBAL).
     */
    public function hasActivePayerSplit(int $bookingId, int $payerAccountId): bool;

    public function assign(BookingPayerSplit $split): void;

    /**
     * Mutation Domain (validTo) déjà appliquée par l'appelant.
     * Commit (flush) = responsabilité de l'appelant (UnitOfWork).
     */
    public function revoke(BookingPayerSplit $split): void;
}
