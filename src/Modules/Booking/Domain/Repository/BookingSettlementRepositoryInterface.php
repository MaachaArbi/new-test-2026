<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Repository;

use App\Modules\Booking\Domain\Entity\BookingSettlement;
use App\Modules\Booking\Domain\ValueObject\BeneficiaryRole;

interface BookingSettlementRepositoryInterface
{
    public function findById(int $id): ?BookingSettlement;

    /**
     * @return list<BookingSettlement>
     */
    public function findByBookingId(int $bookingId, bool $activeOnly = true): array;

    /**
     * Existence active — miroir uq_booking_settlement_active (ADR-003 DBAL).
     */
    public function hasActiveSettlement(
        int $bookingId,
        BeneficiaryRole $beneficiaryRole,
        int $beneficiaryAccountId,
    ): bool;

    public function assign(BookingSettlement $settlement): void;

    /**
     * Mutation Domain (validTo) déjà appliquée par l'appelant.
     * Commit (flush) = responsabilité de l'appelant (UnitOfWork).
     */
    public function revoke(BookingSettlement $settlement): void;
}
