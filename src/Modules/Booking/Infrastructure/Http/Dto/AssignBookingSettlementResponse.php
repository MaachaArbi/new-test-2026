<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Http\Dto;

use App\Modules\Booking\Domain\Entity\BookingSettlement;

/**
 * Réponse POST settlement — métier, sans id interne ni bookingId.
 */
final readonly class AssignBookingSettlementResponse
{
    /**
     * @return array{
     *     beneficiaryAccountId: int,
     *     beneficiaryRole: string,
     *     amountOwedMinor: int,
     *     amountSettledDirectMinor: int,
     *     currencyCode: string,
     *     rate: string|null,
     *     resalePriceAmountMinor: int|null
     * }
     */
    public static function fromDomain(BookingSettlement $settlement): array
    {
        $owed = $settlement->amountOwed();
        $settled = $settlement->amountSettledDirect();
        $resale = $settlement->resalePriceAmount();

        return [
            'beneficiaryAccountId' => $settlement->beneficiaryAccountId(),
            'beneficiaryRole' => $settlement->beneficiaryRole()->value,
            'amountOwedMinor' => $owed->amount(),
            'amountSettledDirectMinor' => $settled->amount(),
            'currencyCode' => $settlement->currencyCode(),
            'rate' => $settlement->rate()?->toString(),
            'resalePriceAmountMinor' => $resale?->amount(),
        ];
    }
}
