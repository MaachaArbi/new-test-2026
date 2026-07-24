<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Settlement\Domain\Entity;

use App\Modules\Settlement\Domain\Entity\SettlementInstrument;
use App\Modules\Settlement\Domain\Exception\InvalidSettlementInstrumentException;
use App\Modules\Settlement\Domain\Exception\SettlementInstrumentStatusUnchangedException;
use App\Modules\Settlement\Domain\ValueObject\InstrumentPartyRole;
use App\Modules\Settlement\Domain\ValueObject\SettlementInstrumentStatus;
use PHPUnit\Framework\TestCase;

final class SettlementInstrumentTest extends TestCase
{
    public function test_create_rejects_non_positive_amount(): void
    {
        try {
            SettlementInstrument::create(
                partyAccountId: 1,
                partyRole: InstrumentPartyRole::Client,
                currencyCode: 'TND',
                paymentMethodId: 1,
                amountMinor: 0,
            );
            self::fail('Expected InvalidSettlementInstrumentException');
        } catch (InvalidSettlementInstrumentException $exception) {
            self::assertSame('settlement_instrument.invalid_amount', $exception->errorCode());
            self::assertSame(0, $exception->context()['amount_minor']);
        }

        try {
            SettlementInstrument::create(
                partyAccountId: 1,
                partyRole: InstrumentPartyRole::Client,
                currencyCode: 'TND',
                paymentMethodId: 1,
                amountMinor: -100,
            );
            self::fail('Expected InvalidSettlementInstrumentException');
        } catch (InvalidSettlementInstrumentException $exception) {
            self::assertSame(-100, $exception->context()['amount_minor']);
        }
    }

    public function test_create_defaults_to_active_status(): void
    {
        $instrument = SettlementInstrument::create(
            partyAccountId: 10,
            partyRole: InstrumentPartyRole::Fournisseur,
            currencyCode: 'eur',
            paymentMethodId: 2,
            amountMinor: 1500,
        );

        self::assertSame(SettlementInstrumentStatus::Active, $instrument->statusCode());
        self::assertNull($instrument->statusChangedAt());
        self::assertNull($instrument->statusReason());
        self::assertSame('EUR', $instrument->currencyCode());
        self::assertSame(1500, $instrument->amountMinor());
    }

    public function test_transition_status_updates_audit_fields(): void
    {
        $instrument = SettlementInstrument::create(
            partyAccountId: 1,
            partyRole: InstrumentPartyRole::Client,
            currencyCode: 'TND',
            paymentMethodId: 1,
            amountMinor: 100,
        );

        $before = new \DateTimeImmutable('now');
        $instrument->transitionStatus(SettlementInstrumentStatus::Returned, 'chèque impayé');
        $after = new \DateTimeImmutable('now');

        self::assertSame(SettlementInstrumentStatus::Returned, $instrument->statusCode());
        self::assertSame('chèque impayé', $instrument->statusReason());
        self::assertNotNull($instrument->statusChangedAt());
        self::assertGreaterThanOrEqual($before->getTimestamp(), $instrument->statusChangedAt()->getTimestamp());
        self::assertLessThanOrEqual($after->getTimestamp(), $instrument->statusChangedAt()->getTimestamp());
    }

    public function test_transition_to_same_status_is_rejected(): void
    {
        $instrument = SettlementInstrument::create(
            partyAccountId: 1,
            partyRole: InstrumentPartyRole::Client,
            currencyCode: 'TND',
            paymentMethodId: 1,
            amountMinor: 100,
        );

        try {
            $instrument->transitionStatus(SettlementInstrumentStatus::Active, null);
            self::fail('Expected SettlementInstrumentStatusUnchangedException');
        } catch (SettlementInstrumentStatusUnchangedException $exception) {
            self::assertSame('settlement_instrument.status_unchanged', $exception->errorCode());
            self::assertSame('active', $exception->context()['status_code']);
        }
    }
}
