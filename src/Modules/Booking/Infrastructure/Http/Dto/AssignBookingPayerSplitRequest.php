<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Http\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body POST …/payer-splits — miroir AssignBookingPayerSplitCommand
 * (hors bookingId résolu via URL).
 */
final class AssignBookingPayerSplitRequest
{
    #[Assert\NotBlank]
    #[Assert\Type('integer')]
    #[Assert\Positive]
    public mixed $payerAccountId = null;

    #[Assert\NotBlank]
    #[Assert\Type('integer')]
    #[Assert\PositiveOrZero]
    public mixed $amountMinor = null;

    #[Assert\NotBlank]
    #[Assert\Type('string')]
    #[Assert\Length(exactly: 3)]
    public mixed $currencyCode = null;

    #[Assert\Type('integer')]
    #[Assert\Positive]
    public mixed $createdBy = null;

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $request = new self();
        $request->payerAccountId = $data['payerAccountId'] ?? null;
        $request->amountMinor = $data['amountMinor'] ?? null;
        $request->currencyCode = $data['currencyCode'] ?? null;
        $request->createdBy = $data['createdBy'] ?? null;

        return $request;
    }
}
