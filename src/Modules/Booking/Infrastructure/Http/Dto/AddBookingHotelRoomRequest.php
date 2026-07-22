<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Http\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body POST /api/v1/bookings/{publicId}/hotel-rooms.
 */
final class AddBookingHotelRoomRequest
{
    #[Assert\Type('string')]
    public mixed $roomType = null;

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $request = new self();
        $request->roomType = $data['roomType'] ?? null;

        return $request;
    }
}
