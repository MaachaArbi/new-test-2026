<?php

declare(strict_types=1);

namespace App\Modules\Party\Domain\Entity;

/**
 * Extension 1-1 party_account_office (PK = account_id).
 * Pas d'historisation. country_id non mappé (hors Domain actuel).
 */
final class PartyAccountOffice
{
    private function __construct(
        private int $accountId,
        private string $officeCode,
        private string $defaultCurrencyCode,
    ) {
    }

    public static function create(
        int $accountId,
        string $officeCode,
        string $defaultCurrencyCode,
    ): self {
        return new self($accountId, $officeCode, $defaultCurrencyCode);
    }

    public function accountId(): int
    {
        return $this->accountId;
    }

    public function officeCode(): string
    {
        return $this->officeCode;
    }

    public function defaultCurrencyCode(): string
    {
        return $this->defaultCurrencyCode;
    }
}
