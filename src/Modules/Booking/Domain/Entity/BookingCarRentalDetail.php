<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Entity;

use App\Modules\Booking\Domain\Exception\InvalidBookingCarRentalDetailException;
use DateTimeImmutable;

/**
 * Extension 1-1 booking_car_rental_detail (PK = booking_id).
 * Spécifique extension car_rental. Pas de setters — corrections hors vague.
 */
final class BookingCarRentalDetail
{
    private function __construct(
        private int $bookingId,
        private ?string $vehicleCategory,
        private ?string $vehicleBrandModel,
        private ?DateTimeImmutable $pickupAt,
        private ?DateTimeImmutable $dropoffAt,
        private ?string $pickupLocation,
        private ?string $dropoffLocation,
    ) {
    }

    public static function create(
        int $bookingId,
        ?string $vehicleCategory = null,
        ?string $vehicleBrandModel = null,
        ?DateTimeImmutable $pickupAt = null,
        ?DateTimeImmutable $dropoffAt = null,
        ?string $pickupLocation = null,
        ?string $dropoffLocation = null,
    ): self {
        if ($pickupAt !== null && $dropoffAt !== null && $dropoffAt < $pickupAt) {
            throw InvalidBookingCarRentalDetailException::dropoffBeforePickup(
                $pickupAt,
                $dropoffAt,
            );
        }

        return new self(
            $bookingId,
            $vehicleCategory,
            $vehicleBrandModel,
            $pickupAt,
            $dropoffAt,
            $pickupLocation,
            $dropoffLocation,
        );
    }

    public function bookingId(): int
    {
        return $this->bookingId;
    }

    public function vehicleCategory(): ?string
    {
        return $this->vehicleCategory;
    }

    public function vehicleBrandModel(): ?string
    {
        return $this->vehicleBrandModel;
    }

    public function pickupAt(): ?DateTimeImmutable
    {
        return $this->pickupAt;
    }

    public function dropoffAt(): ?DateTimeImmutable
    {
        return $this->dropoffAt;
    }

    public function pickupLocation(): ?string
    {
        return $this->pickupLocation;
    }

    public function dropoffLocation(): ?string
    {
        return $this->dropoffLocation;
    }
}
