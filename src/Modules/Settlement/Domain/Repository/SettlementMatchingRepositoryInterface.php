<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Repository;

use App\Modules\Settlement\Domain\Entity\SettlementMatching;
use App\Shared\Domain\ValueObject\PublicId;

interface SettlementMatchingRepositoryInterface
{
    public function findById(int $id): ?SettlementMatching;

    public function findByPublicId(PublicId $publicId): ?SettlementMatching;

    /** SUM(matched_amount_minor) actifs — ADR-003 DBAL. */
    public function sumActiveMatchedForCreditEntry(int $creditEntryId): int;

    /** SUM(matched_amount_minor) actifs — ADR-003 DBAL. */
    public function sumActiveMatchedForDebitEntry(int $debitEntryId): int;

    public function match(SettlementMatching $matching): void;

    /**
     * Mutation Domain (unmatchedAt) déjà appliquée.
     * Commit = responsabilité de l'appelant (UnitOfWork).
     */
    public function unmatch(SettlementMatching $matching): void;
}
