<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Http\Dto;

use App\Modules\Booking\Domain\Entity\Booking;
use App\Shared\Domain\ValueObject\Money;

/**
 * DTO réponse HTTP — expose publicId (jamais l'id interne).
 */
final readonly class BookingResponse
{
    /**
     * @param array{
     *     serviceTypeCode: string,
     *     statusCode: string,
     *     channelCode: string
     * } $status
     * @param array{
     *     achatCurrencyCode: string,
     *     venteCurrencyCode: string,
     *     totalAchatAmount: array{amount: int, currencyCode: string},
     *     totalVenteAmount: array{amount: int, currencyCode: string},
     *     margeAgenceAmount: array{amount: int, currencyCode: string},
     *     margeDistributeurAmount: array{amount: int, currencyCode: string},
     *     paidAmount: array{amount: int, currencyCode: string},
     *     paymentStatus: string
     * } $montants
     * @param array{
     *     isOnRequest: bool,
     *     assignedAgentAccountId: int|null,
     *     isLocked: bool,
     *     isDisputed: bool,
     *     supplierStatusLabel: string|null
     * } $workflow
     */
    private function __construct(
        public string $publicId,
        public string $bookingDate,
        public int $folderId,
        public array $status,
        public int $customerAccountId,
        public ?int $supplierAccountId,
        public int $officeAccountId,
        public string $startDate,
        public ?string $endDate,
        public array $montants,
        public array $workflow,
    ) {
    }

    public static function fromDomain(Booking $booking): self
    {
        return new self(
            publicId: $booking->publicId()->toString(),
            bookingDate: $booking->bookingDate()->format('Y-m-d'),
            folderId: $booking->folderId(),
            status: [
                'serviceTypeCode' => $booking->serviceTypeCode()->toString(),
                'statusCode' => $booking->statusCode()->toString(),
                'channelCode' => $booking->channelCode()->toString(),
            ],
            customerAccountId: $booking->customerAccountId(),
            supplierAccountId: $booking->supplierAccountId(),
            officeAccountId: $booking->officeAccountId(),
            startDate: $booking->startDate()->format('Y-m-d'),
            endDate: $booking->endDate()?->format('Y-m-d'),
            montants: [
                'achatCurrencyCode' => $booking->achatCurrencyCode(),
                'venteCurrencyCode' => $booking->venteCurrencyCode(),
                'totalAchatAmount' => self::moneyToArray($booking->totalAchatAmount()),
                'totalVenteAmount' => self::moneyToArray($booking->totalVenteAmount()),
                'margeAgenceAmount' => self::moneyToArray($booking->margeAgenceAmount()),
                'margeDistributeurAmount' => self::moneyToArray($booking->margeDistributeurAmount()),
                'paidAmount' => self::moneyToArray($booking->paidAmount()),
                'paymentStatus' => $booking->paymentStatus()->value,
            ],
            workflow: [
                'isOnRequest' => $booking->isOnRequest(),
                'assignedAgentAccountId' => $booking->assignedAgentAccountId(),
                'isLocked' => $booking->isLocked(),
                'isDisputed' => $booking->isDisputed(),
                'supplierStatusLabel' => $booking->supplierStatusLabel(),
            ],
        );
    }

    /**
     * @return array{amount: int, currencyCode: string}
     */
    private static function moneyToArray(Money $money): array
    {
        return [
            'amount' => $money->amount(),
            'currencyCode' => $money->currencyCode(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'publicId' => $this->publicId,
            'bookingDate' => $this->bookingDate,
            'folderId' => $this->folderId,
            'status' => $this->status,
            'customerAccountId' => $this->customerAccountId,
            'supplierAccountId' => $this->supplierAccountId,
            'officeAccountId' => $this->officeAccountId,
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
            'montants' => $this->montants,
            'workflow' => $this->workflow,
        ];
    }
}
