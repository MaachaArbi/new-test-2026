<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Repository;

use App\Modules\Settlement\Domain\Entity\SettlementLedgerEntry;
use App\Modules\Settlement\Domain\ValueObject\InstrumentPartyRole;
use App\Shared\Domain\ValueObject\PublicId;

/**
 * Grand livre append-only.
 *
 * append() (pas save) : INSERT uniquement — jamais d'UPDATE via ce port.
 */
interface SettlementLedgerEntryRepositoryInterface
{
    public function findById(int $id): ?SettlementLedgerEntry;

    public function findByPublicId(PublicId $publicId): ?SettlementLedgerEntry;

    /**
     * @return list<SettlementLedgerEntry>
     */
    public function findByBookingId(int $bookingId): array;

    /**
     * INSERT only — pas de chemin update.
     * Persist via UnitOfWork ; commit = Handler.
     */
    public function append(SettlementLedgerEntry $entry): void;

    /**
     * Solde à froid : SUM(amount_minor) sur le livre (ADR-003 DBAL).
     * Ne lit PAS settlement_balance (snapshot trigger).
     */
    public function sumActiveByBook(
        int $partyAccountId,
        InstrumentPartyRole $partyRole,
        string $currencyCode,
    ): int;
}
