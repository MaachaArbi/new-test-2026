<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Http\Dto;

use App\Modules\Booking\Domain\Entity\BookingCharge;

/**
 * Réponse POST charge — métier, sans id interne ni bookingId
 * (pas de ré-référence HTTP ultérieure prévue).
 */
final readonly class AddBookingChargeResponse
{
    /**
     * @return array{
     *     chargeTypeCode: string,
     *     travelerId: int|null,
     *     segmentId: int|null,
     *     label: string|null,
     *     metadata: array<string, mixed>,
     *     achatAmountMinor: int,
     *     achatCurrencyCode: string,
     *     venteAmountMinor: int,
     *     venteCurrencyCode: string,
     *     sortOrder: int
     * }
     */
    public static function fromDomain(BookingCharge $charge): array
    {
        $achat = $charge->achatAmount();
        $vente = $charge->venteAmount();

        return [
            'chargeTypeCode' => $charge->chargeTypeCode(),
            'travelerId' => $charge->travelerId(),
            'segmentId' => $charge->segmentId(),
            'label' => $charge->label(),
            'metadata' => $charge->metadata(),
            'achatAmountMinor' => $achat->amount(),
            'achatCurrencyCode' => $achat->currencyCode(),
            'venteAmountMinor' => $vente->amount(),
            'venteCurrencyCode' => $vente->currencyCode(),
            'sortOrder' => $charge->sortOrder(),
        ];
    }
}
