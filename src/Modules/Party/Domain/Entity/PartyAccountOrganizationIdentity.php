<?php

declare(strict_types=1);

namespace App\Modules\Party\Domain\Entity;

/**
 * Extension 1-1 party_account_organization_identity (PK = account_id).
 * Pas d'historisation. Pas de setters — corrections hors périmètre de ce prompt.
 */
final class PartyAccountOrganizationIdentity
{
    private function __construct(
        private int $accountId,
        private ?string $taxId,
        private ?string $tradeRegister,
        private ?string $legalFormCode,
        private bool $isVatSubject,
        private ?string $website,
    ) {
    }

    public static function create(
        int $accountId,
        ?string $taxId,
        ?string $tradeRegister,
        ?string $legalFormCode,
        bool $isVatSubject,
        ?string $website,
    ): self {
        return new self(
            $accountId,
            $taxId,
            $tradeRegister,
            $legalFormCode,
            $isVatSubject,
            $website,
        );
    }

    public function accountId(): int
    {
        return $this->accountId;
    }

    public function taxId(): ?string
    {
        return $this->taxId;
    }

    public function tradeRegister(): ?string
    {
        return $this->tradeRegister;
    }

    public function legalFormCode(): ?string
    {
        return $this->legalFormCode;
    }

    public function isVatSubject(): bool
    {
        return $this->isVatSubject;
    }

    public function website(): ?string
    {
        return $this->website;
    }
}
