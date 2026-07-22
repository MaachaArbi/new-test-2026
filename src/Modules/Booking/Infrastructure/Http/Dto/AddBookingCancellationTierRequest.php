<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Http\Dto;

use App\Modules\Booking\Domain\ValueObject\PenaltyType;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body POST …/cancellation-policy/{policyId}/tiers.
 *
 * penaltyType validé via Choice (valeurs enum) — évite ValueError de
 * PenaltyType::from() qui fuirait en 500.
 */
final class AddBookingCancellationTierRequest
{
    #[Assert\NotBlank]
    #[Assert\Type('integer')]
    #[Assert\PositiveOrZero]
    public mixed $daysBeforeStart = null;

    #[Assert\NotBlank]
    #[Assert\Choice(callback: [self::class, 'penaltyTypeChoices'])]
    public mixed $penaltyType = null;

    #[Assert\Type('string')]
    public mixed $penaltyValue = null;

    #[Assert\Type('string')]
    public mixed $thresholdTime = null;

    #[Assert\Type('integer')]
    #[Assert\PositiveOrZero]
    public mixed $minStayNights = null;

    #[Assert\Type('integer')]
    #[Assert\PositiveOrZero]
    public mixed $maxStayNights = null;

    #[Assert\Type('integer')]
    public mixed $sortOrder = 0;

    /**
     * @return list<string>
     */
    public static function penaltyTypeChoices(): array
    {
        return array_map(
            static fn (PenaltyType $case): string => $case->value,
            PenaltyType::cases(),
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $request = new self();
        $request->daysBeforeStart = $data['daysBeforeStart'] ?? null;
        $request->penaltyType = $data['penaltyType'] ?? null;
        $request->penaltyValue = $data['penaltyValue'] ?? null;
        $thresholdTime = $data['thresholdTime'] ?? null;
        $request->thresholdTime = $thresholdTime === '' ? null : $thresholdTime;
        $request->minStayNights = $data['minStayNights'] ?? null;
        $request->maxStayNights = $data['maxStayNights'] ?? null;
        $request->sortOrder = array_key_exists('sortOrder', $data)
            ? $data['sortOrder']
            : 0;

        return $request;
    }
}
