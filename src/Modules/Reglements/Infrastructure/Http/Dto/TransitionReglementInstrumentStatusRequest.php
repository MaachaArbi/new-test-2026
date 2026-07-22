<?php

declare(strict_types=1);

namespace App\Modules\Reglements\Infrastructure\Http\Dto;

use App\Modules\Reglements\Domain\ValueObject\ReglementInstrumentStatus;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body PATCH …/instruments/{publicId}/status.
 */
final class TransitionReglementInstrumentStatusRequest
{
    #[Assert\NotBlank]
    #[Assert\Choice(callback: [self::class, 'statusChoices'])]
    public mixed $status = null;

    #[Assert\Type('string')]
    #[Assert\Length(max: 255)]
    public mixed $reason = null;

    /**
     * @return list<string>
     */
    public static function statusChoices(): array
    {
        return array_map(
            static fn (ReglementInstrumentStatus $case): string => $case->value,
            ReglementInstrumentStatus::cases(),
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $request = new self();
        $request->status = $data['status'] ?? null;
        $reason = $data['reason'] ?? null;
        $request->reason = $reason === '' ? null : $reason;

        return $request;
    }
}
