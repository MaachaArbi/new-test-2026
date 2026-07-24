<?php

declare(strict_types=1);

namespace App\Modules\Party\Domain\Entity;

/**
 * Extension 1-1 party_account_organization_identity (PK = account_id).
 * Pas d'historisation. Pas de setters — corrections hors périmètre de ce prompt.
 * Assujettissement TVA : party_account_tax_exemption (agrégat distinct), pas un booléen ici.
 */
final class PartyAccountOrganizationIdentity
{
    private function __construct(
        private int $accountId,
        private ?string $taxId,
        private ?string $tradeRegister,
        private ?string $legalFormCode,
        private ?string $website,
    ) {
    }

    public static function create(
        int $accountId,
        ?string $taxId,
        ?string $tradeRegister,
        ?string $legalFormCode,
        ?string $website,
    ): self {
        return new self(
            $accountId,
            $taxId,
            $tradeRegister,
            $legalFormCode,
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

    public function website(): ?string
    {
        return $this->website;
    }
}
