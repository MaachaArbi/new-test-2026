<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\TransitionBookingStatus;

use App\Modules\Booking\Domain\Entity\Booking;
use App\Modules\Booking\Domain\Exception\BookingNotFoundException;
use App\Modules\Booking\Domain\Repository\BookingRepositoryInterface;
use App\Modules\Booking\Domain\ValueObject\BookingStatusCode;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * Use case : transitionner status_code.
 * Point d'extension futur pour effets de bord (notifications, etc.).
 */
final class TransitionBookingStatusHandler
{
    public function __construct(
        private readonly BookingRepositoryInterface $bookingRepository,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function __invoke(TransitionBookingStatusCommand $command): Booking
    {
        $booking = $this->bookingRepository->findById($command->bookingId);
        if ($booking === null) {
            throw BookingNotFoundException::forId($command->bookingId);
        }

        $booking->transitionTo(BookingStatusCode::fromString($command->statusCode));
        $this->bookingRepository->save($booking);
        $this->unitOfWork->commit();

        return $booking;
    }
}
