<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\PostSettlementTransfer;

/**
 * Transfert de solde via settlement_post_transfer() — pas Doctrine.
 */
final readonly class PostSettlementTransferCommand
{
    public function __construct(
        public int $sourceAccountId,
        public string $sourceRole,
        public int $targetAccountId,
        public string $targetRole,
        public string $currencyCode,
        public int $amountMinor,
        public string $effectiveDate,
        public ?string $reason = null,
        public ?int $createdBy = null,
    ) {
    }
}
