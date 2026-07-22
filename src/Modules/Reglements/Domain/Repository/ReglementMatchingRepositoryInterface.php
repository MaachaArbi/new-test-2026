<?php

declare(strict_types=1);

namespace App\Modules\Reglements\Domain\Repository;

use App\Modules\Reglements\Domain\Entity\ReglementMatching;
use App\Shared\Domain\ValueObject\PublicId;

interface ReglementMatchingRepositoryInterface
{
    public function findById(int $id): ?ReglementMatching;

    public function findByPublicId(PublicId $publicId): ?ReglementMatching;

    /** SUM(matched_amount_minor) actifs — ADR-003 DBAL. */
    public function sumActiveMatchedForCreditEntry(int $creditEntryId): int;

    /** SUM(matched_amount_minor) actifs — ADR-003 DBAL. */
    public function sumActiveMatchedForDebitEntry(int $debitEntryId): int;

    public function match(ReglementMatching $matching): void;

    /**
     * Mutation Domain (unmatchedAt) déjà appliquée.
     * Commit = responsabilité de l'appelant (UnitOfWork).
     */
    public function unmatch(ReglementMatching $matching): void;
}
