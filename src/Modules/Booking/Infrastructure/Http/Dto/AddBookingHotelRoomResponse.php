<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Http\Dto;

use App\Modules\Booking\Domain\Entity\BookingHotelRoom;

/**
 * Réponse POST hotel-rooms — métier seul, sans id interne.
 */
final readonly class AddBookingHotelRoomResponse
{
    /**
     * @return array{roomType: string|null}
     */
    public static function fromDomain(BookingHotelRoom $room): array
    {
        return [
            'roomType' => $room->roomType(),
        ];
    }
}
