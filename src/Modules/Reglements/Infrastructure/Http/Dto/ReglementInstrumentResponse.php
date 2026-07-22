<?php

declare(strict_types=1);

namespace App\Modules\Reglements\Infrastructure\Http\Dto;

use App\Modules\Reglements\Domain\Entity\ReglementInstrument;

/**
 * Réponse instrument — publicId uniquement (pas d'id interne).
 *
 * @phpstan-type InstrumentPayload array{
 *     publicId: string,
 *     partyAccountId: int,
 *     partyRole: string,
 *     currencyCode: string,
 *     paymentMethodId: int,
 *     amountMinor: int,
 *     instrumentRef: string|null,
 *     bankName: string|null,
 *     dueDate: string|null,
 *     issuedOn: string|null,
 *     metadata: array<string, mixed>,
 *     statusCode: string,
 *     statusChangedAt: string|null,
 *     statusReason: string|null,
 *     officeAccountId: int|null
 * }
 */
final readonly class ReglementInstrumentResponse
{
    /**
     * @return InstrumentPayload
     */
    public static function fromDomain(ReglementInstrument $instrument): array
    {
        return [
            'publicId' => $instrument->publicId()->toString(),
            'partyAccountId' => $instrument->partyAccountId(),
            'partyRole' => $instrument->partyRole()->value,
            'currencyCode' => $instrument->currencyCode(),
            'paymentMethodId' => $instrument->paymentMethodId(),
            'amountMinor' => $instrument->amountMinor(),
            'instrumentRef' => $instrument->instrumentRef(),
            'bankName' => $instrument->bankName(),
            'dueDate' => $instrument->dueDate()?->format('Y-m-d'),
            'issuedOn' => $instrument->issuedOn()?->format('Y-m-d'),
            'metadata' => $instrument->metadata(),
            'statusCode' => $instrument->statusCode()->value,
            'statusChangedAt' => $instrument->statusChangedAt()?->format(DATE_ATOM),
            'statusReason' => $instrument->statusReason(),
            'officeAccountId' => $instrument->officeAccountId(),
        ];
    }
}
