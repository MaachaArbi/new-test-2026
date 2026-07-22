<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Http\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body PUT /api/v1/bookings/{publicId}/car-rental-detail.
 */
final class SetBookingCarRentalDetailRequest
{
    #[Assert\Type('string')]
    public mixed $vehicleCategory = null;

    #[Assert\Type('string')]
    public mixed $vehicleBrandModel = null;

    #[Assert\DateTime(format: \DateTimeInterface::ATOM)]
    public mixed $pickupAt = null;

    #[Assert\DateTime(format: \DateTimeInterface::ATOM)]
    public mixed $dropoffAt = null;

    #[Assert\Type('string')]
    public mixed $pickupLocation = null;

    #[Assert\Type('string')]
    public mixed $dropoffLocation = null;

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $request = new self();
        $request->vehicleCategory = $data['vehicleCategory'] ?? null;
        $request->vehicleBrandModel = $data['vehicleBrandModel'] ?? null;
        $pickupAt = $data['pickupAt'] ?? null;
        $request->pickupAt = $pickupAt === '' ? null : $pickupAt;
        $dropoffAt = $data['dropoffAt'] ?? null;
        $request->dropoffAt = $dropoffAt === '' ? null : $dropoffAt;
        $request->pickupLocation = $data['pickupLocation'] ?? null;
        $request->dropoffLocation = $data['dropoffLocation'] ?? null;

        return $request;
    }
}
