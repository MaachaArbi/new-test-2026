<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\CreateBooking;

/**
 * Commande de création d'un booking (pivot + montants/devises).
 * channelCode / paymentStatus : défauts applicatifs (pas Domain).
 * Montants en unités mineures ; taux en string décimale.
 */
final readonly class CreateBookingCommand
{
    public function __construct(
        public int $folderId,
        public string $serviceTypeCode,
        public string $statusCode,
        public int $customerAccountId,
        public ?int $supplierAccountId,
        public int $officeAccountId,
        public string $startDate,
        public ?string $endDate,
        public string $achatCurrencyCode,
        public string $venteCurrencyCode,
        public string $achatExchangeRate,
        public string $venteExchangeRate,
        public int $totalAchatAmount,
        public int $totalVenteAmount,
        public int $margeAgenceAmount,
        public int $margeDistributeurAmount,
        public int $paidAmount,
        public string $channelCode = 'backoffice',
        public string $paymentStatus = 'unpaid',
    ) {
    }
}
