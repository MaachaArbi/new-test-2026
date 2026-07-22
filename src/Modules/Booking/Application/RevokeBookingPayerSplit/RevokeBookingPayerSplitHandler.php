<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\RevokeBookingPayerSplit;

use App\Modules\Booking\Domain\Entity\BookingPayerSplit;
use App\Modules\Booking\Domain\Exception\BookingPayerSplitNotFoundException;
use App\Modules\Booking\Domain\Repository\BookingPayerSplitRepositoryInterface;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * Révoque un payer split (valid_to) — sans mutation Booking.
 */
final class RevokeBookingPayerSplitHandler
{
    public function __construct(
        private readonly BookingPayerSplitRepositoryInterface $payerSplitRepository,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function __invoke(RevokeBookingPayerSplitCommand $command): BookingPayerSplit
    {
        $split = $this->payerSplitRepository->findById($command->splitId);
        if ($split === null) {
            throw BookingPayerSplitNotFoundException::forId($command->splitId);
        }

        $split->revoke();
        $this->payerSplitRepository->revoke($split);
        $this->unitOfWork->commit();

        return $split;
    }
}
