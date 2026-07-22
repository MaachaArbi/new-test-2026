<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\UpdateBookingWorkflow;

use App\Modules\Booking\Domain\Entity\Booking;
use App\Modules\Booking\Domain\Exception\BookingNoChangesException;
use App\Modules\Booking\Domain\Exception\BookingNotFoundException;
use App\Modules\Booking\Domain\Repository\BookingRepositoryInterface;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * Use case : mutations workflow du pivot (on_request, assignation, lock,
 * dispute, supplier_status_label). Pas de transition status_code.
 */
final class UpdateBookingWorkflowHandler
{
    public function __construct(
        private readonly BookingRepositoryInterface $bookingRepository,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function __invoke(UpdateBookingWorkflowCommand $command): Booking
    {
        $hasOnRequest = $command->hasOnRequest && $command->isOnRequest !== null;
        $hasAssignment = $command->hasAssignment;
        $hasLocked = $command->hasLocked && $command->isLocked !== null;
        $hasDisputed = $command->hasDisputed && $command->isDisputed !== null;
        $hasSupplierStatusLabel = $command->hasSupplierStatusLabel;

        if (!$hasOnRequest && !$hasAssignment && !$hasLocked && !$hasDisputed && !$hasSupplierStatusLabel) {
            throw BookingNoChangesException::create();
        }

        $booking = $this->bookingRepository->findById($command->bookingId);
        if ($booking === null) {
            throw BookingNotFoundException::forId($command->bookingId);
        }

        if ($hasOnRequest) {
            if ($command->isOnRequest === true) {
                $booking->markAsOnRequest();
            } else {
                $booking->clearOnRequest();
            }
        }

        if ($hasAssignment) {
            if ($command->assignedAgentAccountId === null) {
                $booking->unassign();
            } else {
                $booking->assignToAgent($command->assignedAgentAccountId);
            }
        }

        if ($hasLocked) {
            if ($command->isLocked === true) {
                $booking->lock();
            } else {
                $booking->unlock();
            }
        }

        if ($hasDisputed) {
            if ($command->isDisputed === true) {
                $booking->markAsDisputed();
            } else {
                $booking->clearDispute();
            }
        }

        if ($hasSupplierStatusLabel) {
            $booking->updateSupplierStatusLabel($command->supplierStatusLabel);
        }

        $this->bookingRepository->save($booking);
        $this->unitOfWork->commit();

        return $booking;
    }
}
