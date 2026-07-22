<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Http\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body POST /api/v1/bookings/{publicId}/cancellation-policy.
 */
final class CreateBookingCancellationPolicyRequest
{
    #[Assert\Type('integer')]
    #[Assert\Positive]
    public mixed $roomId = null;

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $request = new self();
        $request->roomId = $data['roomId'] ?? null;

        return $request;
    }
}
