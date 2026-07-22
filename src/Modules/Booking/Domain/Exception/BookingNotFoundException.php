<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class BookingNotFoundException extends DomainException
{
    public function errorCode(): string
    {
        return 'booking.not_found';
    }

    public static function forId(int $id): self
    {
        return new self(
            sprintf('Booking not found for id %d.', $id),
            ['id' => $id],
        );
    }

    public static function forPublicId(string $publicId): self
    {
        return new self(
            sprintf('Booking not found for public_id %s.', $publicId),
            ['public_id' => $publicId],
        );
    }
}
