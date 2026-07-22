<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Http\Dto;

use App\Modules\Booking\Domain\Entity\BookingCancellationTier;

/**
 * Réponse POST cancellation tier — métier, sans id de palier.
 * policyId repris (clé fonctionnelle du barème parent, déjà exposée).
 */
final readonly class AddBookingCancellationTierResponse
{
    /**
     * @return array{
     *     policyId: int,
     *     daysBeforeStart: int,
     *     penaltyType: string,
     *     penaltyValue: string|null,
     *     thresholdTime: string|null,
     *     minStayNights: int|null,
     *     maxStayNights: int|null,
     *     sortOrder: int
     * }
     */
    public static function fromDomain(BookingCancellationTier $tier): array
    {
        return [
            'policyId' => $tier->policyId(),
            'daysBeforeStart' => $tier->daysBeforeStart(),
            'penaltyType' => $tier->penaltyType()->value,
            'penaltyValue' => $tier->penaltyValue(),
            'thresholdTime' => $tier->thresholdTime(),
            'minStayNights' => $tier->minStayNights(),
            'maxStayNights' => $tier->maxStayNights(),
            'sortOrder' => $tier->sortOrder(),
        ];
    }
}
