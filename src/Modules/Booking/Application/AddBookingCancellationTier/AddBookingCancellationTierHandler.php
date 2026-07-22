<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\AddBookingCancellationTier;

use App\Modules\Booking\Domain\Entity\BookingCancellationTier;
use App\Modules\Booking\Domain\Exception\BookingCancellationPolicyNotFoundException;
use App\Modules\Booking\Domain\Repository\BookingCancellationPolicyRepositoryInterface;
use App\Modules\Booking\Domain\Repository\BookingCancellationTierRepositoryInterface;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * Use case : ajouter un palier à un barème d'annulation.
 */
final class AddBookingCancellationTierHandler
{
    public function __construct(
        private readonly BookingCancellationPolicyRepositoryInterface $policyRepository,
        private readonly BookingCancellationTierRepositoryInterface $tierRepository,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function __invoke(AddBookingCancellationTierCommand $command): BookingCancellationTier
    {
        if (!$this->policyRepository->existsById($command->policyId)) {
            throw BookingCancellationPolicyNotFoundException::forId($command->policyId);
        }

        $tier = BookingCancellationTier::create(
            $command->policyId,
            $command->daysBeforeStart,
            $command->penaltyType,
            $command->penaltyValue,
            $command->thresholdTime,
            $command->minStayNights,
            $command->maxStayNights,
            $command->sortOrder,
        );

        $this->tierRepository->save($tier);
        $this->unitOfWork->commit();

        return $tier;
    }
}
