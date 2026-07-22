<?php

declare(strict_types=1);

namespace App\Modules\Reglements\Domain\Repository;

use App\Modules\Reglements\Domain\Entity\ReglementLedgerEntry;
use App\Modules\Reglements\Domain\ValueObject\InstrumentPartyRole;
use App\Shared\Domain\ValueObject\PublicId;

/**
 * Grand livre append-only.
 *
 * append() (pas save) : INSERT uniquement — jamais d'UPDATE via ce port.
 */
interface ReglementLedgerEntryRepositoryInterface
{
    public function findById(int $id): ?ReglementLedgerEntry;

    public function findByPublicId(PublicId $publicId): ?ReglementLedgerEntry;

    /**
     * @return list<ReglementLedgerEntry>
     */
    public function findByBookingId(int $bookingId): array;

    /**
     * INSERT only — pas de chemin update.
     * Persist via UnitOfWork ; commit = Handler.
     */
    public function append(ReglementLedgerEntry $entry): void;

    /**
     * Solde à froid : SUM(amount_minor) sur le livre (ADR-003 DBAL).
     * Ne lit PAS reglement_balance (snapshot trigger).
     */
    public function sumActiveByBook(
        int $partyAccountId,
        InstrumentPartyRole $partyRole,
        string $currencyCode,
    ): int;
}
