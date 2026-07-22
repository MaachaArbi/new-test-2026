<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Reglements\Domain\Entity;

use App\Modules\Reglements\Domain\Entity\ReglementInstrument;
use App\Modules\Reglements\Domain\Exception\InvalidReglementInstrumentException;
use App\Modules\Reglements\Domain\Exception\ReglementInstrumentStatusUnchangedException;
use App\Modules\Reglements\Domain\ValueObject\InstrumentPartyRole;
use App\Modules\Reglements\Domain\ValueObject\ReglementInstrumentStatus;
use PHPUnit\Framework\TestCase;

final class ReglementInstrumentTest extends TestCase
{
    public function test_create_rejects_non_positive_amount(): void
    {
        try {
            ReglementInstrument::create(
                partyAccountId: 1,
                partyRole: InstrumentPartyRole::Client,
                currencyCode: 'TND',
                paymentMethodId: 1,
                amountMinor: 0,
            );
            self::fail('Expected InvalidReglementInstrumentException');
        } catch (InvalidReglementInstrumentException $exception) {
            self::assertSame('reglement_instrument.invalid_amount', $exception->errorCode());
            self::assertSame(0, $exception->context()['amount_minor']);
        }

        try {
            ReglementInstrument::create(
                partyAccountId: 1,
                partyRole: InstrumentPartyRole::Client,
                currencyCode: 'TND',
                paymentMethodId: 1,
                amountMinor: -100,
            );
            self::fail('Expected InvalidReglementInstrumentException');
        } catch (InvalidReglementInstrumentException $exception) {
            self::assertSame(-100, $exception->context()['amount_minor']);
        }
    }

    public function test_create_defaults_to_active_status(): void
    {
        $instrument = ReglementInstrument::create(
            partyAccountId: 10,
            partyRole: InstrumentPartyRole::Fournisseur,
            currencyCode: 'eur',
            paymentMethodId: 2,
            amountMinor: 1500,
        );

        self::assertSame(ReglementInstrumentStatus::Active, $instrument->statusCode());
        self::assertNull($instrument->statusChangedAt());
        self::assertNull($instrument->statusReason());
        self::assertSame('EUR', $instrument->currencyCode());
        self::assertSame(1500, $instrument->amountMinor());
    }

    public function test_transition_status_updates_audit_fields(): void
    {
        $instrument = ReglementInstrument::create(
            partyAccountId: 1,
            partyRole: InstrumentPartyRole::Client,
            currencyCode: 'TND',
            paymentMethodId: 1,
            amountMinor: 100,
        );

        $before = new \DateTimeImmutable('now');
        $instrument->transitionStatus(ReglementInstrumentStatus::Returned, 'chèque impayé');
        $after = new \DateTimeImmutable('now');

        self::assertSame(ReglementInstrumentStatus::Returned, $instrument->statusCode());
        self::assertSame('chèque impayé', $instrument->statusReason());
        self::assertNotNull($instrument->statusChangedAt());
        self::assertGreaterThanOrEqual($before->getTimestamp(), $instrument->statusChangedAt()->getTimestamp());
        self::assertLessThanOrEqual($after->getTimestamp(), $instrument->statusChangedAt()->getTimestamp());
    }

    public function test_transition_to_same_status_is_rejected(): void
    {
        $instrument = ReglementInstrument::create(
            partyAccountId: 1,
            partyRole: InstrumentPartyRole::Client,
            currencyCode: 'TND',
            paymentMethodId: 1,
            amountMinor: 100,
        );

        try {
            $instrument->transitionStatus(ReglementInstrumentStatus::Active, null);
            self::fail('Expected ReglementInstrumentStatusUnchangedException');
        } catch (ReglementInstrumentStatusUnchangedException $exception) {
            self::assertSame('reglement_instrument.status_unchanged', $exception->errorCode());
            self::assertSame('active', $exception->context()['status_code']);
        }
    }
}
