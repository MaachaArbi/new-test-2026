<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Settlement\Domain\Entity;

use App\Modules\Settlement\Domain\Entity\SettlementLedgerEntry;
use App\Modules\Settlement\Domain\Exception\InvalidSettlementLedgerEntryException;
use App\Modules\Settlement\Domain\ValueObject\InstrumentPartyRole;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class SettlementLedgerEntryTest extends TestCase
{
    public function test_post_rejects_zero_amount(): void
    {
        try {
            SettlementLedgerEntry::post(
                partyAccountId: 1,
                partyRole: InstrumentPartyRole::Client,
                currencyCode: 'TND',
                entryTypeId: 1,
                amountMinor: 0,
                effectiveDate: new DateTimeImmutable('today'),
                bookingId: 10,
            );
            self::fail('Expected InvalidSettlementLedgerEntryException');
        } catch (InvalidSettlementLedgerEntryException $exception) {
            self::assertSame('settlement_ledger_entry.invalid', $exception->errorCode());
            self::assertSame(0, $exception->context()['amount_minor']);
        }
    }

    public function test_post_rejects_missing_origin(): void
    {
        try {
            SettlementLedgerEntry::post(
                partyAccountId: 1,
                partyRole: InstrumentPartyRole::Client,
                currencyCode: 'TND',
                entryTypeId: 1,
                amountMinor: 100,
                effectiveDate: new DateTimeImmutable('today'),
            );
            self::fail('Expected InvalidSettlementLedgerEntryException');
        } catch (InvalidSettlementLedgerEntryException $exception) {
            self::assertSame('settlement_ledger_entry.invalid', $exception->errorCode());
            self::assertSame([], $exception->context());
        }
    }

    public function test_post_accepts_signed_amount_with_origin(): void
    {
        $entry = SettlementLedgerEntry::post(
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
