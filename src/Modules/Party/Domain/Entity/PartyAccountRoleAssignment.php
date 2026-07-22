<?php

declare(strict_types=1);

namespace App\Modules\Party\Domain\Entity;

use App\Modules\Party\Domain\Exception\InvalidPartyAccountRoleAssignmentException;
use App\Modules\Party\Domain\ValueObject\PartyRoleCode;
use DateTimeImmutable;

/**
 * Agrégat d'assignation historisée account ↔ rôle (table party_account_role).
 *
 * Append-only sur le contenu (décision #11) : la seule mutation autorisée est
 * la clôture via valid_to (revoke). Pas de prévention de doublon actif ici
 * (lecture base → vague Infrastructure / Application).
 */
final class PartyAccountRoleAssignment
{
    private function __construct(
        private ?int $id,
        private int $accountId,
        private PartyRoleCode $roleCode,
        private DateTimeImmutable $validFrom,
        private ?DateTimeImmutable $validTo,
        private ?int $createdBy,
    ) {
    }

    public static function assign(
        int $accountId,
        PartyRoleCode $roleCode,
        ?int $createdBy,
    ): self {
        return new self(
            id: null,
            accountId: $accountId,
            roleCode: $roleCode,
            validFrom: new DateTimeImmutable(),
            validTo: null,
            createdBy: $createdBy,
        );
    }

    public function revoke(): void
    {
        if ($this->validTo !== null) {
            throw InvalidPartyAccountRoleAssignmentException::alreadyRevoked(
                $this->accountId,
                $this->roleCode->toString(),
            );
        }

        $this->validTo = new DateTimeImmutable();
    }

    public function isActive(): bool
    {
        return $this->validTo === null;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function accountId(): int
    {
        $accountId = $this->accountId;

        return $accountId;
    }

    public function roleCode(): PartyRoleCode
    {
        return $this->roleCode;
    }

    public function validFrom(): DateTimeImmutable
    {
        $from = $this->validFrom;

        return $from;
    }

    public function validTo(): ?DateTimeImmutable
    {
        return $this->validTo;
    }

    public function createdBy(): ?int
    {
        $actorId = $this->createdBy;

        return $actorId;
    }
}
