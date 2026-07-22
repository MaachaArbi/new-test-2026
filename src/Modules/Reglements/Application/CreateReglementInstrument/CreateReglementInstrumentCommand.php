<?php

declare(strict_types=1);

namespace App\Modules\Reglements\Application\CreateReglementInstrument;

/**
 * Création d'un instrument de règlement (pièce).
 */
final readonly class CreateReglementInstrumentCommand
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public int $partyAccountId,
        public string $partyRole,
        public string $currencyCode,
        public int $paymentMethodId,
        public int $amountMinor,
        public ?string $instrumentRef = null,
        public ?string $bankName = null,
        public ?string $dueDate = null,
        public ?string $issuedOn = null,
        public array $metadata = [],
        public ?int $officeAccountId = null,
    ) {
    }
}
