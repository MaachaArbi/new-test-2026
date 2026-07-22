<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Http\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body POST /api/v1/bookings/{publicId}/travelers — validation d'input
 * (pas de règle métier Domain).
 */
final class AddBookingTravelerRequest
{
    #[Assert\NotBlank]
    #[Assert\Type('string')]
    public mixed $firstName = null;

    #[Assert\NotBlank]
    #[Assert\Type('string')]
    public mixed $lastName = null;

    #[Assert\Type('string')]
    public mixed $civility = null;

    #[Assert\Type('string')]
    public mixed $phone = null;

    #[Assert\Type('string')]
    public mixed $email = null;

    #[Assert\Type('integer')]
    public mixed $age = null;

    #[Assert\Date]
    public mixed $birthDate = null;

    #[Assert\Type('string')]
    public mixed $birthPlace = null;

    #[Assert\Type('string')]
    public mixed $documentType = null;

    #[Assert\Type('string')]
    public mixed $documentNumber = null;

    #[Assert\Type('string')]
    public mixed $drivingLicenseNumber = null;

    #[Assert\Type('string')]
    public mixed $ticketNumber = null;

    #[Assert\Type('string')]
    public mixed $pnr = null;

    #[Assert\Type('string')]
    public mixed $travelClass = null;

    #[Assert\Type('integer')]
    public mixed $nationalityCountryId = null;

    #[Assert\Type('integer')]
    public mixed $residenceCountryId = null;

    #[Assert\Type('integer')]
    public mixed $hotelRoomId = null;

    #[Assert\Type('integer')]
    public mixed $partyAccountId = null;

    #[Assert\Type('boolean')]
    public mixed $isPaxLeader = false;

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $request = new self();
        $request->firstName = $data['firstName'] ?? null;
        $request->lastName = $data['lastName'] ?? null;
        $request->civility = $data['civility'] ?? null;
        $request->phone = $data['phone'] ?? null;
        $request->email = $data['email'] ?? null;
        $request->age = $data['age'] ?? null;
        $birthDate = $data['birthDate'] ?? null;
        $request->birthDate = $birthDate === '' ? null : $birthDate;
        $request->birthPlace = $data['birthPlace'] ?? null;
        $request->documentType = $data['documentType'] ?? null;
        $request->documentNumber = $data['documentNumber'] ?? null;
        $request->drivingLicenseNumber = $data['drivingLicenseNumber'] ?? null;
        $request->ticketNumber = $data['ticketNumber'] ?? null;
        $request->pnr = $data['pnr'] ?? null;
        $request->travelClass = $data['travelClass'] ?? null;
        $request->nationalityCountryId = $data['nationalityCountryId'] ?? null;
        $request->residenceCountryId = $data['residenceCountryId'] ?? null;
        $request->hotelRoomId = $data['hotelRoomId'] ?? null;
        $request->partyAccountId = $data['partyAccountId'] ?? null;
        $request->isPaxLeader = array_key_exists('isPaxLeader', $data)
            ? $data['isPaxLeader']
            : false;

        return $request;
    }
}
