<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\CreateBookingTraveler;

use App\Modules\Booking\Domain\Entity\BookingTraveler;
use App\Modules\Booking\Domain\Exception\BookingTravelerPaxLeaderAlreadySetException;
use App\Modules\Booking\Domain\Repository\BookingTravelerRepositoryInterface;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * Use case : créer un voyageur snapshot. Un seul pax leader par booking.
 */
final class CreateBookingTravelerHandler
{
    public function __construct(
        private readonly BookingTravelerRepositoryInterface $travelerRepository,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function __invoke(CreateBookingTravelerCommand $command): BookingTraveler
    {
        if ($command->isPaxLeader && $this->travelerRepository->hasActivePaxLeader($command->bookingId)) {
            throw BookingTravelerPaxLeaderAlreadySetException::forBooking($command->bookingId);
        }

        $traveler = BookingTraveler::create(
            $command->bookingId,
            $command->firstName,
            $command->lastName,
            $command->hotelRoomId,
            $command->partyAccountId,
            $command->civility,
            $command->phone,
            $command->email,
            $command->age,
            $command->birthDate,
            $command->birthPlace,
            $command->nationalityCountryId,
            $command->residenceCountryId,
            $command->documentType,
            $command->documentNumber,
            $command->drivingLicenseNumber,
            $command->isPaxLeader,
            $command->ticketNumber,
            $command->pnr,
            $command->travelClass,
        );

        $this->travelerRepository->save($traveler);
        $this->unitOfWork->commit();

        return $traveler;
    }
}
