<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Entity;

use App\Modules\Booking\Domain\Exception\InvalidBookingCancellationTierException;
use App\Modules\Booking\Domain\ValueObject\PenaltyType;

/**
 * Palier de barème d'annulation (booking_cancellation_tier) — collection 1-N.
 * Pas d'update dans cette vague.
 */
final class BookingCancellationTier
{
    /**
     * NUMERIC(14,3) — jamais float ; string comme ExchangeRate.
     */
    private const NUMERIC_PATTERN = '/^(?:0(?:\.\d{1,3})?|[1-9]\d{0,10}(?:\.\d{1,3})?)$/';

    private function __construct(
        private ?int $id,
        private int $policyId,
        private int $daysBeforeStart,
        private ?string $thresholdTime,
        private ?int $minStayNights,
        private ?int $maxStayNights,
        private PenaltyType $penaltyType,
        private ?string $penaltyValue,
        private int $sortOrder,
    ) {
    }

    public static function create(
        int $policyId,
        int $daysBeforeStart,
        PenaltyType $penaltyType,
        ?string $penaltyValue = null,
        ?string $thresholdTime = null,
        ?int $minStayNights = null,
        ?int $maxStayNights = null,
        int $sortOrder = 0,
    ): self {
        self::assertPenaltyConsistency($penaltyType, $penaltyValue);

        return new self(
            id: null,
            policyId: $policyId,
            daysBeforeStart: $daysBeforeStart,
            thresholdTime: $thresholdTime,
            minStayNights: $minStayNights,
            maxStayNights: $maxStayNights,
            penaltyType: $penaltyType,
            penaltyValue: $penaltyValue,
            sortOrder: $sortOrder,
        );
    }

    private static function assertPenaltyConsistency(
        PenaltyType $penaltyType,
        ?string $penaltyValue,
    ): void {
        if ($penaltyType === PenaltyType::Free) {
            if ($penaltyValue !== null) {
                throw InvalidBookingCancellationTierException::freeMustHaveNullValue($penaltyValue);
            }

            return;
        }

        if ($penaltyValue === null || trim($penaltyValue) === '') {
            throw InvalidBookingCancellationTierException::valueRequired($penaltyType);
        }

        $trimmed = trim($penaltyValue);
        if (preg_match(self::NUMERIC_PATTERN, $trimmed) !== 1) {
            throw InvalidBookingCancellationTierException::invalidValueFormat($trimmed);
        }

        if ($penaltyType === PenaltyType::Percentage) {
            if (!self::isBetweenZeroAndHundred($trimmed)) {
                throw InvalidBookingCancellationTierException::percentageOutOfRange($trimmed);
            }

            return;
        }

        // FixedAmount
        if (!self::isStrictlyPositive($trimmed)) {
            throw InvalidBookingCancellationTierException::fixedAmountMustBePositive($trimmed);
        }
    }

    /**
     * Valeur déjà validée NUMERIC(14,3) — comparaison string, pas de float.
     */
    private static function isBetweenZeroAndHundred(string $value): bool
    {
        $parts = explode('.', $value, 2);
        $integerPart = $parts[0];
        $fraction = $parts[1] ?? '0';

        $intVal = (int) $integerPart;
        if ($intVal < 0 || $intVal > 100) {
            return false;
        }

        if ($intVal === 100 && (int) $fraction !== 0) {
            return false;
        }

        return true;
    }

    private static function isStrictlyPositive(string $value): bool
    {
        return !preg_match('/^0(?:\.0+)?$/', $value);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function policyId(): int
    {
        return $this->policyId;
    }

    public function daysBeforeStart(): int
    {
        return $this->daysBeforeStart;
    }

    public function thresholdTime(): ?string
    {
        return $this->thresholdTime;
    }

    public function minStayNights(): ?int
    {
        return $this->minStayNights;
    }

    public function maxStayNights(): ?int
    {
        return $this->maxStayNights;
    }

    public function penaltyType(): PenaltyType
    {
        return $this->penaltyType;
    }

    public function penaltyValue(): ?string
    {
        return $this->penaltyValue;
    }

    public function sortOrder(): int
    {
        return $this->sortOrder;
    }
}
