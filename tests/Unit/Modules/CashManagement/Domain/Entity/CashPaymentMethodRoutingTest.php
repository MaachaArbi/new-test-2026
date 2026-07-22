<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\CashManagement\Domain\Entity;

use App\Modules\CashManagement\Domain\Entity\CashPaymentMethodRouting;
use App\Modules\CashManagement\Domain\Exception\InvalidCashPaymentMethodRoutingException;
use App\Modules\CashManagement\Domain\ValueObject\InstrumentTrackingMode;
use PHPUnit\Framework\TestCase;

final class CashPaymentMethodRoutingTest extends TestCase
{
    public function test_create_rejects_aucun_with_individual(): void
    {
        try {
            CashPaymentMethodRouting::create(
                paymentMethodId: 1,
                routingTypeCode: 'aucun',
                instrumentTrackingMode: InstrumentTrackingMode::Individual,
                strictSourceIsolation: false,
            );
            self::fail('Expected InvalidCashPaymentMethodRoutingException');
        } catch (InvalidCashPaymentMethodRoutingException $exception) {
            self::assertSame('cash_payment_method_routing.inconsistent_tracking', $exception->errorCode());
        }
    }

    public function test_create_rejects_caisse_with_not_applicable(): void
    {
        try {
            CashPaymentMethodRouting::create(
                paymentMethodId: 1,
                routingTypeCode: 'caisse',
                instrumentTrackingMode: InstrumentTrackingMode::NotApplicable,
                strictSourceIsolation: false,
            );
            self::fail('Expected InvalidCashPaymentMethodRoutingException');
        } catch (InvalidCashPaymentMethodRoutingException $exception) {
            self::assertSame('cash_payment_method_routing.inconsistent_tracking', $exception->errorCode());
        }
    }

    public function test_update_rejects_inconsistent_pair(): void
    {
        $routing = CashPaymentMethodRouting::create(
            paymentMethodId: 1,
            routingTypeCode: 'caisse',
            instrumentTrackingMode: InstrumentTrackingMode::Aggregate,
            strictSourceIsolation: false,
        );

        try {
            $routing->update(
                routingTypeCode: 'banque_directe',
                instrumentTrackingMode: InstrumentTrackingMode::NotApplicable,
                strictSourceIsolation: false,
                requiresCustodyCheck: true,
                isActive: true,
            );
            self::fail('Expected InvalidCashPaymentMethodRoutingException');
        } catch (InvalidCashPaymentMethodRoutingException $exception) {
            self::assertSame('cash_payment_method_routing.inconsistent_tracking', $exception->errorCode());
        }
    }

    public function test_create_aucun_with_not_applicable_ok(): void
    {
        $routing = CashPaymentMethodRouting::create(
            paymentMethodId: 7,
            routingTypeCode: 'aucun',
            instrumentTrackingMode: InstrumentTrackingMode::NotApplicable,
            strictSourceIsolation: false,
        );

        self::assertSame(7, $routing->paymentMethodId());
        self::assertSame('aucun', $routing->routingTypeCode());
        self::assertSame(InstrumentTrackingMode::NotApplicable, $routing->instrumentTrackingMode());
    }
}
