<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Entity;

use App\Shared\Domain\ValueObject\Money;

/**
 * Ligne de tarification agrégée (booking_charge).
 * Devises = celles du booking (colonnes absentes du schéma charge).
 * Pas d'update dans cette vague.
 */
final class BookingCharge
{
    /**
     * @param array<string, mixed> $metadata
     */
    private function __construct(
        private ?int $id,
        private int $bookingId,
        private ?int $travelerId,
        private ?int $segmentId,
        private string $chargeTypeCode,
        private ?string $label,
        private array $metadata,
        private int $achatAmount,
        private string $achatCurrencyCode,
        private int $venteAmount,
        private string $venteCurrencyCode,
        private int $sortOrder,
    ) {
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public static function create(
        int $bookingId,
        string $chargeTypeCode,
        Money $achatAmount,
        Money $venteAmount,
        ?int $travelerId = null,
        ?int $segmentId = null,
        ?string $label = null,
        array $metadata = [],
        int $sortOrder = 0,
    ): self {
        return new self(
            id: null,
            bookingId: $bookingId,
            travelerId: $travelerId,
            segmentId: $segmentId,
            chargeTypeCode: $chargeTypeCode,
            label: $label,
            metadata: $metadata,
            achatAmount: $achatAmount->amount(),
            achatCurrencyCode: $achatAmount->currencyCode(),
            venteAmount: $venteAmount->amount(),
            venteCurrencyCode: $venteAmount->currencyCode(),
            sortOrder: $sortOrder,
        );
    }

    /**
     * Post-load Infrastructure : devises lues depuis le booking parent
     * (absentes de booking_charge).
     */
    public function hydrateCurrencies(string $achatCurrencyCode, string $venteCurrencyCode): void
    {
        $this->achatCurrencyCode = strtoupper(trim($achatCurrencyCode));
        $this->venteCurrencyCode = strtoupper(trim($venteCurrencyCode));
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function bookingId(): int
    {
        return $this->bookingId;
    }

    public function travelerId(): ?int
    {
        return $this->travelerId;
    }

    public function segmentId(): ?int
    {
        return $this->segmentId;
    }

    public function chargeTypeCode(): string
    {
        return $this->chargeTypeCode;
    }

    public function label(): ?string
    {
        return $this->label;
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        return $this->metadata;
    }

    public function achatAmount(): Money
    {
        return Money::fromMinorUnits($this->achatAmount, $this->achatCurrencyCode);
    }

    public function venteAmount(): Money
    {
        return Money::fromMinorUnits($this->venteAmount, $this->venteCurrencyCode);
    }

    public function sortOrder(): int
    {
        return $this->sortOrder;
    }
}
