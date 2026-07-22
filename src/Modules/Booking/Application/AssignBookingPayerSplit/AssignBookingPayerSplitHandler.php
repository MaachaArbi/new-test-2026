<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\AssignBookingPayerSplit;

use App\Modules\Booking\Domain\Entity\BookingPayerSplit;
use App\Modules\Booking\Domain\Exception\BookingNotFoundException;
use App\Modules\Booking\Domain\Exception\BookingPayerSplitAlreadyActiveException;
use App\Modules\Booking\Domain\Exception\BookingPayerSplitCurrencyMismatchException;
use App\Modules\Booking\Domain\Exception\BookingPayerSplitExceedsTotalException;
use App\Modules\Booking\Domain\Repository\BookingPayerSplitRepositoryInterface;
use App\Modules\Booking\Domain\Repository\BookingRepositoryInterface;
use App\Shared\Domain\ValueObject\Money;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * Assigne un split payeur — lit totalVenteAmount sans jamais muter le Booking.
 * Plafond : SUM(actifs) + nouveau <= total_vente (égalité OK, dépassement non).
 */
final class AssignBookingPayerSplitHandler
{
    public function __construct(
        private readonly BookingRepositoryInterface $bookingRepository,
        private readonly BookingPayerSplitRepositoryInterface $payerSplitRepository,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function __invoke(AssignBookingPayerSplitCommand $command): BookingPayerSplit
    {
        $booking = $this->bookingRepository->findById($command->bookingId);
        if ($booking === null) {
            throw BookingNotFoundException::forId($command->bookingId);
        }

        $venteCurrency = $booking->venteCurrencyCode();
        if (strtoupper(trim($command->currencyCode)) !== $venteCurrency) {
            throw BookingPayerSplitCurrencyMismatchException::forBooking(
                $command->bookingId,
                $venteCurrency,
                strtoupper(trim($command->currencyCode)),
            );
        }

        if ($this->payerSplitRepository->hasActivePayerSplit(
            $command->bookingId,
            $command->payerAccountId,
        )) {
            throw BookingPayerSplitAlreadyActiveException::forBookingAndPayer(
                $command->bookingId,
                $command->payerAccountId,
            );
        }

        $alreadyAllocated = $this->payerSplitRepository->sumActiveAmountForBooking($command->bookingId);
        $allowedTotal = $booking->totalVenteAmount()->amount();
        $projected = $alreadyAllocated + $command->amountMinor;

        if ($projected > $allowedTotal) {
            throw BookingPayerSplitExceedsTotalException::forBooking(
                $command->bookingId,
                $alreadyAllocated,
                $command->amountMinor,
                $allowedTotal,
            );
        }

        $split = BookingPayerSplit::assign(
            bookingId: $command->bookingId,
            payerAccountId: $command->payerAccountId,
            amount: Money::fromMinorUnits($command->amountMinor, $venteCurrency),
            createdBy: $command->createdBy,
        );

        $this->payerSplitRepository->assign($split);
        $this->unitOfWork->commit();

        return $split;
    }
}
