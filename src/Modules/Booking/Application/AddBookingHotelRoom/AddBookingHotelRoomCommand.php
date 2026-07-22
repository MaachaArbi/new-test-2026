<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\AddBookingHotelRoom;

final readonly class AddBookingHotelRoomCommand
{
    public function __construct(
        public int $bookingId,
        public ?string $roomType = null,
    ) {
    }
}
