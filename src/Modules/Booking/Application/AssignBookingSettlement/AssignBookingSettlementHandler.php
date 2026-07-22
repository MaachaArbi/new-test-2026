<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\AssignBookingSettlement;

use App\Modules\Booking\Domain\Entity\BookingSettlement;
use App\Modules\Booking\Domain\Exception\BookingSettlementAlreadyActiveException;
use App\Modules\Booking\Domain\Repository\BookingSettlementRepositoryInterface;
use App\Modules\Booking\Domain\ValueObject\SettlementRate;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * Assigne un fait de settlement — sans toucher aux totaux Booking.
 */
final class AssignBookingSettlementHandler
{
    public function __construct(
        private readonly BookingSettlementRepositoryInterface $settlementRepository,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function __invoke(AssignBookingSettlementCommand $command): BookingSettlement
    {
        if ($this->settlementRepository->hasActiveSettlement(
            $command->bookingId,
            $command->beneficiaryRole,
            $command->beneficiaryAccountId,
        )) {
            throw BookingSettlementAlreadyActiveException::forTriplet(
                $command->bookingId,
                $command->beneficiaryRole->value,
                $command->beneficiaryAccountId,
            );
        }

        $amountOwed = Money::fromMinorUnits($command->amountOwedMinor, $command->currencyCode);
        $settledDirect = Money::fromMinorUnits($command->amountSettledDirectMinor, $command->currencyCode);
        $resale = $command->resalePriceAmountMinor === null
            ? null
            : Money::fromMinorUnits($command->resalePriceAmountMinor, $command->currencyCode);
        $rate = $command->rate === null ? null : SettlementRate::fromString($command->rate);

        $settlement = BookingSettlement::assign(
            bookingId: $command->bookingId,
            beneficiaryAccountId: $command->beneficiaryAccountId,
            beneficiaryRole: $command->beneficiaryRole,
            amountOwed: $amountOwed,
            amountSettledDirect: $settledDirect,
            rate: $rate,
            resalePriceAmount: $resale,
            createdBy: $command->createdBy,
        );

        $this->settlementRepository->assign($settlement);
        $this->unitOfWork->commit();

        return $settlement;
    }
}
