<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Http\Dto;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Body POST …/charges — champs plats Money (pas de structure imbriquée).
 *
 * Miroir exact de AddBookingChargeCommand (hors bookingId résolu via URL).
 */
final class AddBookingChargeRequest
{
    #[Assert\NotBlank]
    #[Assert\Type('string')]
    public mixed $chargeTypeCode = null;

    #[Assert\NotBlank]
    #[Assert\Type('integer')]
    public mixed $achatAmountMinor = null;

    #[Assert\NotBlank]
    #[Assert\Type('string')]
    #[Assert\Length(exactly: 3)]
    public mixed $achatCurrencyCode = null;

    #[Assert\NotBlank]
    #[Assert\Type('integer')]
    public mixed $venteAmountMinor = null;

    #[Assert\NotBlank]
    #[Assert\Type('string')]
    #[Assert\Length(exactly: 3)]
    public mixed $venteCurrencyCode = null;

    #[Assert\Type('integer')]
    #[Assert\Positive]
    public mixed $travelerId = null;

    #[Assert\Type('integer')]
    #[Assert\Positive]
    public mixed $segmentId = null;

    #[Assert\Type('string')]
    public mixed $label = null;

    /**
     * Objet JSON libre — transmis tel quel au Command.
     *
     * @var array<string, mixed>|mixed
     */
    #[Assert\Type('array')]
    public mixed $metadata = [];

    #[Assert\Type('integer')]
    public mixed $sortOrder = 0;

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $request = new self();
        $request->chargeTypeCode = $data['chargeTypeCode'] ?? null;
        $request->achatAmountMinor = $data['achatAmountMinor'] ?? null;
        $request->achatCurrencyCode = $data['achatCurrencyCode'] ?? null;
        $request->venteAmountMinor = $data['venteAmountMinor'] ?? null;
        $request->venteCurrencyCode = $data['venteCurrencyCode'] ?? null;
        $request->travelerId = $data['travelerId'] ?? null;
        $request->segmentId = $data['segmentId'] ?? null;
        $label = $data['label'] ?? null;
        $request->label = $label === '' ? null : $label;
        $request->metadata = array_key_exists('metadata', $data)
            ? $data['metadata']
            : [];
        $request->sortOrder = array_key_exists('sortOrder', $data)
            ? $data['sortOrder']
            : 0;

        return $request;
    }
}
