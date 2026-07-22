<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidBookingPayerSplitException extends DomainException
{
    public function errorCode(): string
    {
        return 'booking_payer_split.invalid';
    }

    public static function alreadyRevoked(int $splitId): self
    {
        return new self(
            'Cannot revoke a booking payer split that is already closed (valid_to set).',
            [
                'split_id' => $splitId,
                'reason' => 'already_revoked',
            ],
        );
    }
}
