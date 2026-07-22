<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Http\Dto;

use App\Modules\Booking\Domain\ValueObject\BeneficiaryRole;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body POST …/settlements.
 *
 * beneficiaryRole via Choice (valeurs enum) — évite ValueError de
 * BeneficiaryRole::from() qui fuirait en 500.
 * rate : string optionnelle, format laissé au VO SettlementRate.
 */
final class AssignBookingSettlementRequest
{
    #[Assert\NotBlank]
    #[Assert\Type('integer')]
    #[Assert\Positive]
    public mixed $beneficiaryAccountId = null;

    #[Assert\NotBlank]
    #[Assert\Choice(callback: [self::class, 'beneficiaryRoleChoices'])]
    public mixed $beneficiaryRole = null;

    #[Assert\NotBlank]
    #[Assert\Type('integer')]
    #[Assert\PositiveOrZero]
    public mixed $amountOwedMinor = null;

    #[Assert\NotBlank]
    #[Assert\Type('string')]
    #[Assert\Length(exactly: 3)]
    public mixed $currencyCode = null;

    #[Assert\Type('integer')]
    #[Assert\PositiveOrZero]
    public mixed $amountSettledDirectMinor = 0;

    #[Assert\Type('string')]
    public mixed $rate = null;

    #[Assert\Type('integer')]
    #[Assert\PositiveOrZero]
    public mixed $resalePriceAmountMinor = null;

    #[Assert\Type('integer')]
    #[Assert\Positive]
    public mixed $createdBy = null;

    /**
     * @return list<string>
     */
    public static function beneficiaryRoleChoices(): array
    {
        return array_map(
            static fn (BeneficiaryRole $case): string => $case->value,
            BeneficiaryRole::cases(),
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $request = new self();
        $request->beneficiaryAccountId = $data['beneficiaryAccountId'] ?? null;
        $request->beneficiaryRole = $data['beneficiaryRole'] ?? null;
        $request->amountOwedMinor = $data['amountOwedMinor'] ?? null;
        $request->currencyCode = $data['currencyCode'] ?? null;
        $request->amountSettledDirectMinor = array_key_exists('amountSettledDirectMinor', $data)
            ? $data['amountSettledDirectMinor']
            : 0;
        $rate = $data['rate'] ?? null;
        $request->rate = $rate === '' ? null : $rate;
        $request->resalePriceAmountMinor = $data['resalePriceAmountMinor'] ?? null;
        $request->createdBy = $data['createdBy'] ?? null;

        return $request;
    }
}
