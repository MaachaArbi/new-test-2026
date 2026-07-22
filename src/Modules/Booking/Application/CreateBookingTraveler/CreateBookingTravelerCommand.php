<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\CreateBookingTraveler;

use DateTimeImmutable;

final readonly class CreateBookingTravelerCommand
{
    public function __construct(
        public int $bookingId,
        public string $firstName,
        public string $lastName,
        public ?int $hotelRoomId = null,
        public ?int $partyAccountId = null,
        public ?string $civility = null,
        public ?string $phone = null,
        public ?string $email = null,
        public ?int $age = null,
        public ?DateTimeImmutable $birthDate = null,
        public ?string $birthPlace = null,
        public ?int $nationalityCountryId = null,
        public ?int $residenceCountryId = null,
        public ?string $documentType = null,
        public ?string $documentNumber = null,
        public ?string $drivingLicenseNumber = null,
        public bool $isPaxLeader = false,
        public ?string $ticketNumber = null,
        public ?string $pnr = null,
        public ?string $travelClass = null,
    ) {
    }
}
