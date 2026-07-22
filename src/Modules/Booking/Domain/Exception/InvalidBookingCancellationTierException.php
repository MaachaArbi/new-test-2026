<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Exception;

use App\Modules\Booking\Domain\ValueObject\PenaltyType;
use App\Shared\Domain\Exception\DomainException;

/**
 * Invariants sur un palier d'annulation (pénalité incohérente).
 */
final class InvalidBookingCancellationTierException extends DomainException
{
    public function errorCode(): string
    {
        return 'booking_cancellation_tier.invalid_penalty';
    }

    public static function freeMustHaveNullValue(string $penaltyValue): self
    {
        return new self(
            'penaltyValue must be null when penaltyType is free.',
            [
                'penalty_type' => PenaltyType::Free->value,
                'penalty_value' => $penaltyValue,
            ],
        );
    }

    public static function valueRequired(PenaltyType $penaltyType): self
    {
        return new self(
            sprintf('penaltyValue is required when penaltyType is "%s".', $penaltyType->value),
            ['penalty_type' => $penaltyType->value],
        );
    }

    public static function invalidValueFormat(string $penaltyValue): self
    {
        return new self(
            'penaltyValue must match NUMERIC(14,3) as a decimal string.',
            ['penalty_value' => $penaltyValue],
        );
    }

    public static function percentageOutOfRange(string $penaltyValue): self
    {
        return new self(
            'Percentage penaltyValue must be between 0 and 100.',
            [
                'penalty_type' => PenaltyType::Percentage->value,
                'penalty_value' => $penaltyValue,
            ],
        );
    }

    public static function fixedAmountMustBePositive(string $penaltyValue): self
    {
        return new self(
            'Fixed amount penaltyValue must be strictly positive.',
            [
                'penalty_type' => PenaltyType::FixedAmount->value,
                'penalty_value' => $penaltyValue,
            ],
        );
    }
}
