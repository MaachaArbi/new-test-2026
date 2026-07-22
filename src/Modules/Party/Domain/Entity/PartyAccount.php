<?php

declare(strict_types=1);

namespace App\Modules\Party\Domain\Entity;

use App\Modules\Party\Domain\Exception\InvalidPartyAccountStateException;
use App\Shared\Domain\ValueObject\Email;
use App\Modules\Party\Domain\ValueObject\PartyAccountNature;
use App\Shared\Domain\ValueObject\PublicId;
use DateTimeImmutable;

/**
 * Agrégat racine PartyAccount.
 *
 * Pas de collections roles/functions/groups : party_account est le pivot le plus
 * joint du système (ADR-018 / modèle conceptuel).
 *
 * ## disable() vs delete() — distinction projet (à répliquer ailleurs)
 *
 * - {@see disable()} / `is_disabled` : **désactivation métier réversible**.
 *   Le compte reste dans le périmètre opérationnel (listes, FK, historique)
 *   mais ne doit plus être traité comme actif métier (login, nouvelles
 *   opérations). Ne pose **pas** `deleted_at`.
 *
 * - {@see delete()} / `deleted_at` : **soft-delete**. Retrait des listes /
 *   lectures par défaut (`deleted_at IS NULL`). La ligne et les assignations
 *   liées (role / function / group_member…) restent en base — append-only
 *   côté enfants, pas de hard delete en cascade. Distinct de `disable()`.
 *
 * Ne pas fusionner les deux : un compte peut être disabled sans être deleted,
 * et deleted implique le retrait de la liste même s'il n'était pas disabled.
 *
 * @see docs/decisions/2026-07-21-soft-delete-vs-disable-party-account.md
 */
final class PartyAccount
{
    private function __construct(
        private ?int $id,
        private PublicId $publicId,
        private PartyAccountNature $nature,
        private string $displayName,
        private ?Email $email,
        private ?int $parentAccountId,
        private bool $isDisabled,
        private bool $isProspect,
        private bool $isDisputed,
        private ?DateTimeImmutable $deletedAt,
    ) {
    }

    public static function createPerson(
        string $displayName,
        ?Email $email = null,
        ?int $parentAccountId = null,
    ): self {
        if ($parentAccountId !== null) {
            throw InvalidPartyAccountStateException::parentAccountNotAllowedForPerson(
                $parentAccountId,
                $displayName,
            );
        }

        return self::newAccount(
            PartyAccountNature::Person,
            $displayName,
            $email,
            null,
        );
    }

    public static function createOrganization(
        string $displayName,
        ?Email $email = null,
        ?int $parentAccountId = null,
    ): self {
        return self::newAccount(
            PartyAccountNature::Organization,
            $displayName,
            $email,
            $parentAccountId,
        );
    }

    private static function newAccount(
        PartyAccountNature $nature,
        string $displayName,
        ?Email $email,
        ?int $parentAccountId,
    ): self {
        return new self(
            id: null,
            publicId: PublicId::generate(),
            nature: $nature,
            displayName: $displayName,
            email: $email,
            parentAccountId: $parentAccountId,
            isDisabled: false,
            isProspect: false,
            isDisputed: false,
            deletedAt: null,
        );
    }

    /**
     * Désactivation métier réversible (`is_disabled`). Ne touche pas à deleted_at.
     */
    public function disable(): void
    {
        $this->isDisabled = true;
    }

    /**
     * Réactivation métier (`is_disabled = false`). Ne touche pas à deleted_at.
     */
    public function enable(): void
    {
        $this->isDisabled = false;
    }

    /**
     * Soft-delete (`deleted_at`). Idempotent : second appel no-op.
     * N'implique pas disable() — les deux axes restent orthogonaux.
     */
    public function delete(): void
    {
        if ($this->deletedAt !== null) {
            return;
        }

        $this->deletedAt = new DateTimeImmutable();
    }

    public function markAsProspect(): void
    {
        $this->isProspect = true;
    }

    public function markAsDisputed(): void
    {
        $this->isDisputed = true;
    }

    public function updateDisplayName(string $displayName): void
    {
        $this->displayName = $displayName;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function publicId(): PublicId
    {
        return $this->publicId;
    }

    public function nature(): PartyAccountNature
    {
        return $this->nature;
    }

    public function displayName(): string
    {
        return $this->displayName;
    }

    public function email(): ?Email
    {
        return $this->email;
    }

    public function parentAccountId(): ?int
    {
        return $this->parentAccountId;
    }

    public function isDisabled(): bool
    {
        return $this->isDisabled;
    }

    public function isProspect(): bool
    {
        return $this->isProspect;
    }

    public function isDisputed(): bool
    {
        return $this->isDisputed;
    }

    /**
     * Soft-delete actif (équivalent Domain de `deleted_at IS NOT NULL`).
     */
    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    public function deletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }
}
