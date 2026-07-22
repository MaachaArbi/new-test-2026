<?php

declare(strict_types=1);

namespace App\Modules\Reglements\Infrastructure\Http\Dto;

use App\Modules\Reglements\Domain\ValueObject\InstrumentPartyRole;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body POST /api/v1/reglements/instruments.
 */
final class CreateReglementInstrumentRequest
{
    #[Assert\NotBlank]
    #[Assert\Type('integer')]
    #[Assert\Positive]
    public mixed $partyAccountId = null;

    #[Assert\NotBlank]
    #[Assert\Choice(callback: [self::class, 'partyRoleChoices'])]
    public mixed $partyRole = null;

    #[Assert\NotBlank]
    #[Assert\Type('string')]
    #[Assert\Length(exactly: 3)]
    public mixed $currencyCode = null;

    #[Assert\NotBlank]
    #[Assert\Type('integer')]
    #[Assert\Positive]
    public mixed $paymentMethodId = null;

    #[Assert\NotBlank]
    #[Assert\Type('integer')]
    #[Assert\Positive]
    public mixed $amountMinor = null;

    #[Assert\Type('string')]
    #[Assert\Length(max: 100)]
    public mixed $instrumentRef = null;

    #[Assert\Type('string')]
    #[Assert\Length(max: 100)]
    public mixed $bankName = null;

    #[Assert\Type('string')]
    public mixed $dueDate = null;

    #[Assert\Type('string')]
    public mixed $issuedOn = null;

    #[Assert\Type('array')]
    public mixed $metadata = [];

    #[Assert\Type('integer')]
    #[Assert\Positive]
    public mixed $officeAccountId = null;

    /**
     * @return list<string>
     */
    public static function partyRoleChoices(): array
    {
        return array_map(
            static fn (InstrumentPartyRole $case): string => $case->value,
            InstrumentPartyRole::cases(),
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $request = new self();
        $request->partyAccountId = $data['partyAccountId'] ?? null;
        $request->partyRole = $data['partyRole'] ?? null;
        $request->currencyCode = $data['currencyCode'] ?? null;
        $request->paymentMethodId = $data['paymentMethodId'] ?? null;
        $request->amountMinor = $data['amountMinor'] ?? null;
        $request->instrumentRef = $data['instrumentRef'] ?? null;
        $request->bankName = $data['bankName'] ?? null;
        $dueDate = $data['dueDate'] ?? null;
        $request->dueDate = $dueDate === '' ? null : $dueDate;
        $issuedOn = $data['issuedOn'] ?? null;
        $request->issuedOn = $issuedOn === '' ? null : $issuedOn;
        $request->metadata = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];
        $request->officeAccountId = $data['officeAccountId'] ?? null;

        return $request;
    }
}
