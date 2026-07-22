<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Reglements\Domain\Entity;

use App\Modules\Reglements\Domain\Entity\ReglementLedgerEntry;
use App\Modules\Reglements\Domain\Exception\InvalidReglementLedgerEntryException;
use App\Modules\Reglements\Domain\ValueObject\InstrumentPartyRole;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class ReglementLedgerEntryTest extends TestCase
{
    public function test_post_rejects_zero_amount(): void
    {
        try {
            ReglementLedgerEntry::post(
                partyAccountId: 1,
                partyRole: InstrumentPartyRole::Client,
                currencyCode: 'TND',
                entryTypeId: 1,
                amountMinor: 0,
                effectiveDate: new DateTimeImmutable('today'),
                bookingId: 10,
            );
            self::fail('Expected InvalidReglementLedgerEntryException');
        } catch (InvalidReglementLedgerEntryException $exception) {
            self::assertSame('reglement_ledger_entry.invalid', $exception->errorCode());
            self::assertSame(0, $exception->context()['amount_minor']);
        }
    }

    public function test_post_rejects_missing_origin(): void
    {
        try {
            ReglementLedgerEntry::post(
                partyAccountId: 1,
                partyRole: InstrumentPartyRole::Client,
                currencyCode: 'TND',
                entryTypeId: 1,
                amountMinor: 100,
                effectiveDate: new DateTimeImmutable('today'),
            );
            self::fail('Expected InvalidReglementLedgerEntryException');
        } catch (InvalidReglementLedgerEntryException $exception) {
            self::assertSame('reglement_ledger_entry.invalid', $exception->errorCode());
            self::assertSame([], $exception->context());
        }
    }

    public function test_post_accepts_signed_amount_with_origin(): void
    {
        $entry = ReglementLedgerEntry::post(
            partyAccountId: 5,
            partyRole: InstrumentPartyRole::Fournisseur,
            currencyCode: 'eur',
            entryTypeId: 2,
            amountMinor: -500,
            effectiveDate: new DateTimeImmutable('2026-07-22'),
            transferId: 99,
            memo: 'test',
        );

        self::assertNull($entry->id());
        self::assertSame(-500, $entry->amountMinor());
        self::assertSame('EUR', $entry->currencyCode());
        self::assertSame(99, $entry->transferId());
        self::assertNull($entry->bookingId());
    }
}
