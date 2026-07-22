<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Entity;

use App\Modules\Booking\Domain\Exception\InvalidBookingPayerSplitException;
use App\Shared\Domain\ValueObject\Money;
use DateTimeImmutable;

/**
 * Répartition historisée du montant à payer entre payeurs (booking_payer_split).
 *
 * Append-only : seule mutation = revoke() (valid_to).
 * Le plafond vs total_vente_amount est une orchestration Application
 * (somme des actifs + nouveau montant) — pas une règle Domain pure.
 * Devise = devise vente du booking (colonne absente du schéma → hydrate).
 */
final class BookingPayerSplit
{
    private function __construct(
        private ?int $id,
        private int $bookingId,
        private int $payerAccountId,
        private int $amount,
        private string $currencyCode,
        private DateTimeImmutable $validFrom,
        private ?DateTimeImmutable $validTo,
        private ?int $createdBy,
    ) {
    }

    public static function assign(
        int $bookingId,
        int $payerAccountId,
        Money $amount,
        ?int $createdBy = null,
    ): self {
        return new self(
            id: null,
            bookingId: $bookingId,
            payerAccountId: $payerAccountId,
            amount: $amount->amount(),
            currencyCode: $amount->currencyCode(),
            validFrom: new DateTimeImmutable(),
            validTo: null,
            createdBy: $createdBy,
        );
    }

    /**
     * Post-load Infrastructure : devise vente lue depuis le booking parent.
     */
    public function hydrateCurrency(string $currencyCode): void
    {
        $this->currencyCode = strtoupper(trim($currencyCode));
    }

    public function revoke(): void
    {
        if ($this->validTo !== null) {
            throw InvalidBookingPayerSplitException::alreadyRevoked((int) ($this->id ?? 0));
        }

        $this->validTo = new DateTimeImmutable();
    }

    public function isActive(): bool
    {
        return $this->validTo === null;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function bookingId(): int
    {
        return $this->bookingId;
    }

    public function payerAccountId(): int
    {
        return $this->payerAccountId;
    }

    public function amount(): Money
    {
        return Money::fromMinorUnits($this->amount, $this->currencyCode);
    }

    public function currencyCode(): string
    {
        return $this->currencyCode;
    }

    public function validFrom(): DateTimeImmutable
    {
        return $this->validFrom;
    }

    public function validTo(): ?DateTimeImmutable
    {
        return $this->validTo;
    }

    public function createdBy(): ?int
    {
        return $this->createdBy;
    }
}
