<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class BookingCancellationPolicyNotFoundException extends DomainException
{
    public function errorCode(): string
    {
        return 'booking_cancellation_policy.not_found';
    }

    public static function forId(int $id): self
    {
        return new self(
            sprintf('Booking cancellation policy not found for id %d.', $id),
            ['id' => $id],
        );
    }
}
