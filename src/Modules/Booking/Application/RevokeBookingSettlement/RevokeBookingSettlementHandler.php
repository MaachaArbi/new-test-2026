<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\RevokeBookingSettlement;

use App\Modules\Booking\Domain\Entity\BookingSettlement;
use App\Modules\Booking\Domain\Exception\BookingSettlementNotFoundException;
use App\Modules\Booking\Domain\Repository\BookingSettlementRepositoryInterface;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * Révoque un settlement (valid_to) — sans mutation Booking.
 */
final class RevokeBookingSettlementHandler
{
    public function __construct(
        private readonly BookingSettlementRepositoryInterface $settlementRepository,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function __invoke(RevokeBookingSettlementCommand $command): BookingSettlement
    {
        $settlement = $this->settlementRepository->findById($command->settlementId);
        if ($settlement === null) {
            throw BookingSettlementNotFoundException::forId($command->settlementId);
        }

        $settlement->revoke();
        $this->settlementRepository->revoke($settlement);
        $this->unitOfWork->commit();

        return $settlement;
    }
}
