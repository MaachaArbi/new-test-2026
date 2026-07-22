<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Http\Dto;

use App\Modules\Booking\Domain\Entity\BookingCarRentalDetail;
use DateTimeInterface;

/**
 * Réponse PUT car-rental-detail — métier seul, sans bookingId interne.
 */
final readonly class SetBookingCarRentalDetailResponse
{
    /**
     * @return array{
     *     vehicleCategory: string|null,
     *     vehicleBrandModel: string|null,
     *     pickupAt: string|null,
     *     dropoffAt: string|null,
     *     pickupLocation: string|null,
     *     dropoffLocation: string|null
     * }
     */
    public static function fromDomain(BookingCarRentalDetail $detail): array
    {
        return [
            'vehicleCategory' => $detail->vehicleCategory(),
            'vehicleBrandModel' => $detail->vehicleBrandModel(),
            'pickupAt' => $detail->pickupAt()?->format(DateTimeInterface::ATOM),
            'dropoffAt' => $detail->dropoffAt()?->format(DateTimeInterface::ATOM),
            'pickupLocation' => $detail->pickupLocation(),
            'dropoffLocation' => $detail->dropoffLocation(),
        ];
    }
}
