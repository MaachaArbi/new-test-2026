<?php

declare(strict_types=1);

namespace App\Modules\Reglements\Application\PostReglementTransfer;

/**
 * Transfert de solde via reglement_post_transfer() — pas Doctrine.
 */
final readonly class PostReglementTransferCommand
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
