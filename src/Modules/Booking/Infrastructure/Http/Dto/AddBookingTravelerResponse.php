<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Http\Dto;

use App\Modules\Booking\Domain\Entity\BookingTraveler;

/**
 * Réponse minimale POST travelers — snapshot métier, sans id interne ni
 * bookingId (ADR : pas d'identifiant public sur cette sous-ressource).
 */
final readonly class AddBookingTravelerResponse
{
    /**
     * @return array{
     *     firstName: string,
     *     lastName: string,
     *     civility: string|null,
     *     phone: string|null,
     *     email: string|null,
     *     age: int|null,
     *     birthDate: string|null,
     *     birthPlace: string|null,
     *     nationalityCountryId: int|null,
     *     residenceCountryId: int|null,
     *     hotelRoomId: int|null,
     *     partyAccountId: int|null,
     *     documentType: string|null,
     *     documentNumber: string|null,
     *     drivingLicenseNumber: string|null,
     *     isPaxLeader: bool,
     *     ticketNumber: string|null,
     *     pnr: string|null,
     *     travelClass: string|null
     * }
     */
    public static function fromDomain(BookingTraveler $traveler): array
    {
        $birthDate = $traveler->birthDate();

        return [
            'firstName' => $traveler->firstName(),
            'lastName' => $traveler->lastName(),
            'civility' => $traveler->civility(),
            'phone' => $traveler->phone(),
            'email' => $traveler->email(),
            'age' => $traveler->age(),
            'birthDate' => $birthDate?->format('Y-m-d'),
            'birthPlace' => $traveler->birthPlace(),
            'nationalityCountryId' => $traveler->nationalityCountryId(),
            'residenceCountryId' => $traveler->residenceCountryId(),
            'hotelRoomId' => $traveler->hotelRoomId(),
            'partyAccountId' => $traveler->partyAccountId(),
            'documentType' => $traveler->documentType(),
            'documentNumber' => $traveler->documentNumber(),
            'drivingLicenseNumber' => $traveler->drivingLicenseNumber(),
            'isPaxLeader' => $traveler->isPaxLeader(),
            'ticketNumber' => $traveler->ticketNumber(),
            'pnr' => $traveler->pnr(),
            'travelClass' => $traveler->travelClass(),
        ];
    }
}
