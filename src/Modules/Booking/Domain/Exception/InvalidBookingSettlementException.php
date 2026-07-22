<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

/**
 * Invariants BookingSettlement (double revoke, devises incohérentes).
 */
final class InvalidBookingSettlementException extends DomainException
{
    public function errorCode(): string
    {
        return 'booking_settlement.invalid';
    }

    public static function alreadyRevoked(int $settlementId): self
    {
        return new self(
            'Cannot revoke a booking settlement that is already closed (valid_to set).',
            [
                'settlement_id' => $settlementId,
                'reason' => 'already_revoked',
            ],
        );
    }

    public static function currencyMismatch(string $field, string $expected, string $actual): self
    {
        return new self(
            sprintf(
                'Settlement field "%s" currency "%s" does not match settlement currency "%s".',
                $field,
                $actual,
                $expected,
            ),
            [
                'field' => $field,
                'expected_currency' => $expected,
                'actual_currency' => $actual,
                'reason' => 'currency_mismatch',
            ],
        );
    }
}
