<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Entity;

use App\Modules\Booking\Domain\Exception\InvalidBookingSettlementException;
use App\Modules\Booking\Domain\ValueObject\BeneficiaryRole;
use App\Modules\Booking\Domain\ValueObject\SettlementRate;
use App\Shared\Domain\ValueObject\Money;
use DateTimeImmutable;

/**
 * Fait de règlement historisé par bénéficiaire (booking_settlement).
 *
 * Append-only sur le contenu : seule mutation = revoke() (valid_to).
 * Ne recalcule JAMAIS les totaux/marges du Booking (frontière BookingCharge).
 * resalePriceAmount est purement informatif — aucune agrégation Domain.
 */
final class BookingSettlement
{
    private function __construct(
        private ?int $id,
        private int $bookingId,
        private int $beneficiaryAccountId,
        private BeneficiaryRole $beneficiaryRole,
        private int $amountOwed,
        private int $amountSettledDirect,
        private ?SettlementRate $rate,
        private ?int $resalePriceAmount,
        private string $currencyCode,
        private DateTimeImmutable $validFrom,
        private ?DateTimeImmutable $validTo,
        private ?int $createdBy,
    ) {
    }

    /**
     * @param Money|null $amountSettledDirect défaut = 0 dans la devise de amountOwed
     */
    public static function assign(
        int $bookingId,
        int $beneficiaryAccountId,
        BeneficiaryRole $beneficiaryRole,
        Money $amountOwed,
        ?Money $amountSettledDirect = null,
        ?SettlementRate $rate = null,
        ?Money $resalePriceAmount = null,
        ?int $createdBy = null,
    ): self {
        $currencyCode = $amountOwed->currencyCode();

        $settled = $amountSettledDirect ?? Money::fromMinorUnits(0, $currencyCode);
        self::assertMoneyCurrency('amount_settled_direct', $settled, $currencyCode);

        if ($resalePriceAmount !== null) {
            self::assertMoneyCurrency('resale_price_amount', $resalePriceAmount, $currencyCode);
        }

        return new self(
            id: null,
            bookingId: $bookingId,
            beneficiaryAccountId: $beneficiaryAccountId,
            beneficiaryRole: $beneficiaryRole,
            amountOwed: $amountOwed->amount(),
            amountSettledDirect: $settled->amount(),
            rate: $rate,
            resalePriceAmount: $resalePriceAmount?->amount(),
            currencyCode: $currencyCode,
            validFrom: new DateTimeImmutable(),
            validTo: null,
            createdBy: $createdBy,
        );
    }

    public function revoke(): void
    {
        if ($this->validTo !== null) {
            throw InvalidBookingSettlementException::alreadyRevoked((int) ($this->id ?? 0));
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

    public function beneficiaryAccountId(): int
    {
        return $this->beneficiaryAccountId;
    }

    public function beneficiaryRole(): BeneficiaryRole
    {
        return $this->beneficiaryRole;
    }

    public function amountOwed(): Money
    {
        return Money::fromMinorUnits($this->amountOwed, $this->currencyCode);
    }

    public function amountSettledDirect(): Money
    {
        return Money::fromMinorUnits($this->amountSettledDirect, $this->currencyCode);
    }

    public function rate(): ?SettlementRate
    {
        return $this->rate;
    }

    public function resalePriceAmount(): ?Money
    {
        if ($this->resalePriceAmount === null) {
            return null;
        }

        return Money::fromMinorUnits($this->resalePriceAmount, $this->currencyCode);
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

    private static function assertMoneyCurrency(string $field, Money $money, string $expected): void
    {
        if ($money->currencyCode() !== $expected) {
            throw InvalidBookingSettlementException::currencyMismatch(
                $field,
                $expected,
                $money->currencyCode(),
            );
        }
    }
}
