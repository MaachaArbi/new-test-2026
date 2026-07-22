<?php

declare(strict_types=1);

namespace App\Modules\Party\Domain\Entity;

use App\Modules\Party\Domain\Exception\InvalidPartyAccountGroupMembershipException;
use DateTimeImmutable;

/**
 * Agrégat d'appartenance historisée account ↔ groupe (table party_account_group_member).
 *
 * Append-only sur le contenu : seule mutation = clôture via valid_to (revoke).
 * Unicité active = paire (account_id, group_id) — pas d'exclusivité par type
 * (plusieurs groupes commercial actifs pour le même compte sont autorisés).
 */
final class PartyAccountGroupMembership
{
    private readonly int $accountId;
    private readonly int $groupId;
    private readonly DateTimeImmutable $openedAt;
    private readonly ?int $actorId;
    private ?DateTimeImmutable $closedAt = null;
    private ?int $id;

    private function __construct(
        ?int $id,
        int $accountId,
        int $groupId,
        DateTimeImmutable $openedAt,
        ?int $actorId,
    ) {
        $this->id = $id;
        $this->accountId = $accountId;
        $this->groupId = $groupId;
        $this->openedAt = $openedAt;
        $this->actorId = $actorId;
    }

    public static function assign(
        int $accountId,
        int $groupId,
        ?int $createdBy,
    ): self {
        $membership = new self(
            null,
            $accountId,
            $groupId,
            new DateTimeImmutable(),
            $createdBy,
        );

        return $membership;
    }

    public function revoke(): void
    {
        if (null !== $this->closedAt) {
            throw InvalidPartyAccountGroupMembershipException::alreadyRevoked(
                accountId: $this->accountId,
                groupId: $this->groupId,
            );
        }

        $this->closedAt = new DateTimeImmutable('now');
    }

    public function isActive(): bool
    {
        return null === $this->closedAt;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function accountId(): int
    {
        return $this->accountId;
    }

    public function groupId(): int
    {
        return $this->groupId;
    }

    public function createdBy(): ?int
    {
        return $this->actorId;
    }

    public function validFrom(): DateTimeImmutable
    {
        return $this->openedAt;
    }

    public function validTo(): ?DateTimeImmutable
    {
        return $this->closedAt;
    }
}
