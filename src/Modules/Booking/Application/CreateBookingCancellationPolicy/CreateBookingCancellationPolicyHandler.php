<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\CreateBookingCancellationPolicy;

use App\Modules\Booking\Domain\Entity\BookingCancellationPolicy;
use App\Modules\Booking\Domain\Exception\BookingCancellationPolicyAlreadyExistsException;
use App\Modules\Booking\Domain\Exception\BookingCancellationRoomMismatchException;
use App\Modules\Booking\Domain\Repository\BookingCancellationPolicyRepositoryInterface;
use App\Modules\Booking\Domain\Repository\BookingHotelRoomRepositoryInterface;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * Use case : créer un barème d'annulation (toute réservation ou par chambre).
 */
final class CreateBookingCancellationPolicyHandler
{
    public function __construct(
        private readonly BookingCancellationPolicyRepositoryInterface $policyRepository,
        private readonly BookingHotelRoomRepositoryInterface $roomRepository,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function __invoke(CreateBookingCancellationPolicyCommand $command): BookingCancellationPolicy
    {
        if ($command->roomId !== null) {
            $this->assertRoomBelongsToBooking($command->bookingId, $command->roomId);

            if ($this->policyRepository->existsForRoom($command->roomId)) {
                throw BookingCancellationPolicyAlreadyExistsException::forRoom($command->roomId);
            }
        } elseif ($this->policyRepository->existsForBooking($command->bookingId)) {
            throw BookingCancellationPolicyAlreadyExistsException::forBooking($command->bookingId);
        }

        $policy = BookingCancellationPolicy::create($command->bookingId, $command->roomId);
        $this->policyRepository->save($policy);
        $this->unitOfWork->commit();

        return $policy;
    }

    private function assertRoomBelongsToBooking(int $bookingId, int $roomId): void
    {
        if ($this->roomRepository->belongsToBooking($roomId, $bookingId)) {
            return;
        }

        throw BookingCancellationRoomMismatchException::forBookingAndRoom($bookingId, $roomId);
    }
}
