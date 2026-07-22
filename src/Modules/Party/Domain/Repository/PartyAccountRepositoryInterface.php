<?php

declare(strict_types=1);

namespace App\Modules\Party\Domain\Repository;

use App\Modules\Party\Domain\Entity\PartyAccount;
use App\Shared\Domain\ValueObject\Email;
use App\Shared\Domain\ValueObject\PublicId;

interface PartyAccountRepositoryInterface
{
    public function findById(int $id): ?PartyAccount;

    /**
     * ADR-003 : nature seule via DBAL (existence / garde-fou), sans hydrate ORM.
     * null = compte introuvable ou soft-deleted.
     */
    public function findNatureById(int $id): ?string;

    public function findByPublicId(PublicId $publicId): ?PartyAccount;

    /**
     * Lookup incluant soft-deleted — usage DELETE idempotent uniquement.
     */
    public function findByPublicIdIncludingDeleted(PublicId $publicId): ?PartyAccount;

    public function findByEmail(Email $email): ?PartyAccount;

    public function save(PartyAccount $account): void;

    /**
     * Persiste un soft-delete Domain ({@see PartyAccount::delete()}).
     * Aucun DELETE SQL — uniquement flush de deleted_at.
     */
    public function delete(PartyAccount $account): void;
}
