<?php

declare(strict_types=1);

namespace App\Modules\CashManagement\Domain\Entity;

use App\Modules\CashManagement\Domain\Exception\InvalidCashPaymentMethodRoutingException;
use App\Modules\CashManagement\Domain\ValueObject\InstrumentTrackingMode;

/**
 * Extension 1-1 de settlement_payment_method — routing Cash Management.
 *
 * PK = payment_method_id (FK, strategy NONE). Mutable (UPDATE métier autorisé).
 * Contrainte croisée Domain = chk_routing_tracking_consistency :
 * routing_type_code='aucun' ⟺ instrument_tracking_mode=not_applicable.
 */
final class CashPaymentMethodRouting
{
    private function __construct(
        private int $paymentMethodId,
        private string $routingTypeCode,
        private InstrumentTrackingMode $instrumentTrackingMode,
        private bool $strictSourceIsolation,
        private bool $requiresCustodyCheck,
        private bool $isActive,
    ) {
    }

    public static function create(
        int $paymentMethodId,
        string $routingTypeCode,
        InstrumentTrackingMode $instrumentTrackingMode,
        bool $strictSourceIsolation,
        bool $requiresCustodyCheck = true,
        bool $isActive = true,
    ): self {
        self::assertTrackingConsistency($routingTypeCode, $instrumentTrackingMode);

        return new self(
            $paymentMethodId,
            $routingTypeCode,
            $instrumentTrackingMode,
            $strictSourceIsolation,
            $requiresCustodyCheck,
            $isActive,
        );
    }

    public function update(
        string $routingTypeCode,
        InstrumentTrackingMode $instrumentTrackingMode,
        bool $strictSourceIsolation,
        bool $requiresCustodyCheck,
        bool $isActive,
    ): void {
        self::assertTrackingConsistency($routingTypeCode, $instrumentTrackingMode);

        $this->routingTypeCode = $routingTypeCode;
        $this->instrumentTrackingMode = $instrumentTrackingMode;
        $this->strictSourceIsolation = $strictSourceIsolation;
        $this->requiresCustodyCheck = $requiresCustodyCheck;
        $this->isActive = $isActive;
    }

    public function paymentMethodId(): int
    {
        return $this->paymentMethodId;
    }

    public function routingTypeCode(): string
    {
        return $this->routingTypeCode;
    }

    public function instrumentTrackingMode(): InstrumentTrackingMode
    {
        return $this->instrumentTrackingMode;
    }

    public function strictSourceIsolation(): bool
    {
        return $this->strictSourceIsolation;
    }

    public function requiresCustodyCheck(): bool
    {
        return $this->requiresCustodyCheck;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    private static function assertTrackingConsistency(
        string $routingTypeCode,
        InstrumentTrackingMode $instrumentTrackingMode,
    ): void {
        $isAucun = $routingTypeCode === 'aucun';
        $isNotApplicable = $instrumentTrackingMode === InstrumentTrackingMode::NotApplicable;

        if ($isAucun !== $isNotApplicable) {
            throw InvalidCashPaymentMethodRoutingException::inconsistentTracking(
                $routingTypeCode,
                $instrumentTrackingMode->value,
            );
        }
    }
}
