<?php

declare(strict_types=1);

namespace App\Modules\Party\Domain\Entity;

use App\Modules\Party\Domain\Exception\InvalidPartyAccountFunctionAssignmentException;
use App\Modules\Party\Domain\ValueObject\PartyFunctionCode;
use DateTimeImmutable;

/**
 * Agrégat d'assignation historisée person ↔ fonction ↔ organisation
 * (table party_account_function).
 *
 * Append-only sur le contenu (décision #11) : la seule mutation autorisée est
 * la clôture via valid_to (revoke). Unicité active = triplet
 * (person_account_id, function_code, organization_account_id) — pas de
 * prévention de doublon ici (lecture base → Application).
 * organization_account_id toujours obligatoire (décision #13).
 */
final class PartyAccountFunctionAssignment
{
    private ?DateTimeImmutable $validTo = null;

    private function __construct(
        private ?int $id,
        private readonly int $personAccountId,
        private readonly int $organizationAccountId,
        private readonly PartyFunctionCode $functionCode,
        private readonly DateTimeImmutable $validFrom,
        private readonly ?int $createdBy,
    ) {
    }

    public static function assign(
        int $personAccountId,
        int $organizationAccountId,
        PartyFunctionCode $functionCode,
        ?int $createdBy,
    ): self {
        return new self(
            null,
            $personAccountId,
            $organizationAccountId,
            $functionCode,
            new DateTimeImmutable(),
            $createdBy,
        );
    }

    public function revoke(): void
    {
        if (null !== $this->validTo) {
            throw InvalidPartyAccountFunctionAssignmentException::alreadyRevoked(
                personAccountId: $this->personAccountId,
                organizationAccountId: $this->organizationAccountId,
                functionCode: $this->functionCode->toString(),
            );
        }

        $this->validTo = new DateTimeImmutable('now');
    }

    public function isActive(): bool
    {
        return null === $this->validTo;
    }

    public function personAccountId(): int
    {
        return $this->personAccountId;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function organizationAccountId(): int
    {
        return $this->organizationAccountId;
    }

    public function functionCode(): PartyFunctionCode
    {
        return $this->functionCode;
    }

    public function validFrom(): DateTimeImmutable
    {
        return $this->validFrom;
    }

    public function validTo(): ?DateTimeImmutable
    {
        return $this->validTo;
    }

    public function createdBy(): ?int
    {
        return $this->createdBy;
    }
}
