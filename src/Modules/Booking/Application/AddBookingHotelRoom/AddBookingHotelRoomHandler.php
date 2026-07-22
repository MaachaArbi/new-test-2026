<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\AddBookingHotelRoom;

use App\Modules\Booking\Application\AssertBookingServiceType;
use App\Modules\Booking\Domain\Entity\BookingHotelRoom;
use App\Modules\Booking\Domain\Repository\BookingHotelRoomRepositoryInterface;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * Use case : ajouter une chambre (extension accommodation).
 */
final class AddBookingHotelRoomHandler
{
    private const REQUIRED_EXTENSION = 'accommodation';

    public function __construct(
        private readonly AssertBookingServiceType $assertBookingServiceType,
        private readonly BookingHotelRoomRepositoryInterface $roomRepository,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function __invoke(AddBookingHotelRoomCommand $command): BookingHotelRoom
    {
        ($this->assertBookingServiceType)($command->bookingId, self::REQUIRED_EXTENSION);

        $room = BookingHotelRoom::create($command->bookingId, $command->roomType);
        $this->roomRepository->save($room);
        $this->unitOfWork->commit();

        return $room;
    }
}
