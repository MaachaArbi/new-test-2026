<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

/**
 * Transition vers le statut déjà actuel — refusée (pas de no-op silencieux).
 */
final class BookingStatusUnchangedException extends DomainException
{
    public function errorCode(): string
    {
        return 'booking.status_unchanged';
    }

    public static function forStatus(string $statusCode): self
    {
        return new self(
            sprintf('Booking is already in status "%s".', $statusCode),
            ['status_code' => $statusCode],
        );
    }
}
