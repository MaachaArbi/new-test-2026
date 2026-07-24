<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Infrastructure\Http\Dto;

use App\Modules\Settlement\Domain\ValueObject\SettlementInstrumentStatus;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body PATCH …/instruments/{publicId}/status.
 */
final class TransitionSettlementInstrumentStatusRequest
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
            static fn (SettlementInstrumentStatus $case): string => $case->value,
            SettlementInstrumentStatus::cases(),
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
