<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Entity;

use DateTimeImmutable;

/**
 * Voyageur — snapshot figé (booking_traveler).
 * Pas de mutation : correction éventuelle = vague séparée.
 * email : string déclaratif (pas Shared\Email — saisie libre figée).
 */
final class BookingTraveler
{
    private function __construct(
        private ?int $id,
        private int $bookingId,
        private ?int $hotelRoomId,
        private ?int $partyAccountId,
        private string $firstName,
        private string $lastName,
        private ?string $civility,
        private ?string $phone,
        private ?string $email,
        private ?int $age,
        private ?DateTimeImmutable $birthDate,
        private ?string $birthPlace,
        private ?int $nationalityCountryId,
        private ?int $residenceCountryId,
        private ?string $documentType,
        private ?string $documentNumber,
        private ?string $drivingLicenseNumber,
        private bool $isPaxLeader,
        private ?string $ticketNumber,
        private ?string $pnr,
        private ?string $travelClass,
    ) {
    }

    public static function create(
        int $bookingId,
        string $firstName,
        string $lastName,
        ?int $hotelRoomId = null,
        ?int $partyAccountId = null,
        ?string $civility = null,
        ?string $phone = null,
        ?string $email = null,
        ?int $age = null,
        ?DateTimeImmutable $birthDate = null,
        ?string $birthPlace = null,
        ?int $nationalityCountryId = null,
        ?int $residenceCountryId = null,
        ?string $documentType = null,
        ?string $documentNumber = null,
        ?string $drivingLicenseNumber = null,
        bool $isPaxLeader = false,
        ?string $ticketNumber = null,
        ?string $pnr = null,
        ?string $travelClass = null,
    ): self {
        return new self(
            id: null,
            bookingId: $bookingId,
            hotelRoomId: $hotelRoomId,
            partyAccountId: $partyAccountId,
            firstName: $firstName,
            lastName: $lastName,
            civility: $civility,
            phone: $phone,
            email: $email,
            age: $age,
            birthDate: $birthDate,
            birthPlace: $birthPlace,
            nationalityCountryId: $nationalityCountryId,
            residenceCountryId: $residenceCountryId,
            documentType: $documentType,
            documentNumber: $documentNumber,
            drivingLicenseNumber: $drivingLicenseNumber,
            isPaxLeader: $isPaxLeader,
            ticketNumber: $ticketNumber,
            pnr: $pnr,
            travelClass: $travelClass,
        );
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function bookingId(): int
    {
        return $this->bookingId;
    }

    public function hotelRoomId(): ?int
    {
        return $this->hotelRoomId;
    }

    public function partyAccountId(): ?int
    {
        return $this->partyAccountId;
    }

    public function firstName(): string
    {
        return $this->firstName;
    }

    public function lastName(): string
    {
        return $this->lastName;
    }

    public function civility(): ?string
    {
        return $this->civility;
    }

    public function phone(): ?string
    {
        return $this->phone;
    }

    public function email(): ?string
    {
        return $this->email;
    }

    public function age(): ?int
    {
        return $this->age;
    }

    public function birthDate(): ?DateTimeImmutable
    {
        return $this->birthDate;
    }

    public function birthPlace(): ?string
    {
        return $this->birthPlace;
    }

    public function nationalityCountryId(): ?int
    {
        return $this->nationalityCountryId;
    }

    public function residenceCountryId(): ?int
    {
        return $this->residenceCountryId;
    }

    public function documentType(): ?string
    {
        return $this->documentType;
    }

    public function documentNumber(): ?string
    {
        return $this->documentNumber;
    }

    public function drivingLicenseNumber(): ?string
    {
        return $this->drivingLicenseNumber;
    }

    public function isPaxLeader(): bool
    {
        return $this->isPaxLeader;
    }

    public function ticketNumber(): ?string
    {
        return $this->ticketNumber;
    }

    public function pnr(): ?string
    {
        return $this->pnr;
    }

    public function travelClass(): ?string
    {
        return $this->travelClass;
    }
}
