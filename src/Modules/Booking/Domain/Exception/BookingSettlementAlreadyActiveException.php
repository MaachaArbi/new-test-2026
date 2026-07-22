<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

/**
 * Doublon actif sur (booking_id, beneficiary_role, beneficiary_account_id).
 */
final class BookingSettlementAlreadyActiveException extends DomainException
{
    public function errorCode(): string
    {
        return 'booking_settlement.already_active';
    }

    public static function forTriplet(int $bookingId, string $beneficiaryRole, int $beneficiaryAccountId): self
    {
        return new self(
            sprintf(
                'Booking %d already has an active settlement for role "%s" and account %d.',
                $bookingId,
                $beneficiaryRole,
                $beneficiaryAccountId,
            ),
            [
                'booking_id' => $bookingId,
                'beneficiary_role' => $beneficiaryRole,
                'beneficiary_account_id' => $beneficiaryAccountId,
            ],
        );
    }
}
