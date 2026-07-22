<?php

declare(strict_types=1);

namespace App\Modules\Reglements\Infrastructure\Http\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body POST /api/v1/reglements/matchings.
 */
final class CreateReglementMatchingRequest
{
    #[Assert\NotBlank]
    #[Assert\Type('string')]
    #[Assert\Uuid]
    public mixed $debitEntryPublicId = null;

    #[Assert\NotBlank]
    #[Assert\Type('string')]
    #[Assert\Uuid]
    public mixed $creditEntryPublicId = null;

    #[Assert\NotBlank]
    #[Assert\Type('integer')]
    #[Assert\Positive]
    public mixed $matchedAmountMinor = null;

    #[Assert\Type('boolean')]
    public mixed $isAutomatic = false;

    #[Assert\Type('string')]
    #[Assert\Length(max: 30)]
    public mixed $matchGroup = null;

    #[Assert\Type('integer')]
    #[Assert\Positive]
    public mixed $matchedBy = null;

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $request = new self();
        $request->debitEntryPublicId = $data['debitEntryPublicId'] ?? null;
        $request->creditEntryPublicId = $data['creditEntryPublicId'] ?? null;
        $request->matchedAmountMinor = $data['matchedAmountMinor'] ?? null;
        $request->isAutomatic = array_key_exists('isAutomatic', $data)
            ? $data['isAutomatic']
            : false;
        $matchGroup = $data['matchGroup'] ?? null;
        $request->matchGroup = $matchGroup === '' ? null : $matchGroup;
        $request->matchedBy = $data['matchedBy'] ?? null;

        return $request;
    }
}
