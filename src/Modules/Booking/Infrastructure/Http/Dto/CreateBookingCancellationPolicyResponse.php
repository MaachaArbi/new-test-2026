<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Http\Dto;

use App\Modules\Booking\Domain\Entity\BookingCancellationPolicy;

/**
 * Réponse POST cancellation-policy.
 *
 * Exception à « zéro id » : `id` est la clé fonctionnelle obligatoire pour
 * créer des paliers (`…/cancellation-policy/{policyId}/tiers`).
 */
final readonly class CreateBookingCancellationPolicyResponse
{
    /**
     * @return array{id: int, roomId: int|null}
     */
    public static function fromDomain(BookingCancellationPolicy $policy): array
    {
        return [
            'id' => (int) $policy->id(),
            'roomId' => $policy->roomId(),
        ];
    }
}
