<?php

declare(strict_types=1);

namespace App\Modules\CashManagement\Application\UpdateCashPaymentMethodRouting;

use App\Modules\CashManagement\Domain\Entity\CashPaymentMethodRouting;
use App\Modules\CashManagement\Domain\Exception\CashPaymentMethodRoutingNotFoundException;
use App\Modules\CashManagement\Domain\Exception\CashRoutingTypeNotFoundException;
use App\Modules\CashManagement\Domain\Exception\InvalidCashPaymentMethodRoutingException;
use App\Modules\CashManagement\Domain\Repository\CashPaymentMethodRoutingRepositoryInterface;
use App\Modules\CashManagement\Domain\Repository\CashRoutingTypeRepositoryInterface;
use App\Modules\CashManagement\Domain\ValueObject\InstrumentTrackingMode;
use App\Shared\Infrastructure\Persistence\UnitOfWork;
use ValueError;

final class UpdateCashPaymentMethodRoutingHandler
{
    public function __construct(
        private readonly CashRoutingTypeRepositoryInterface $routingTypeRepository,
        private readonly CashPaymentMethodRoutingRepositoryInterface $routingRepository,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function __invoke(UpdateCashPaymentMethodRoutingCommand $command): CashPaymentMethodRouting
    {
        $routing = $this->routingRepository->findByPaymentMethodId($command->paymentMethodId);
        if ($routing === null) {
            throw CashPaymentMethodRoutingNotFoundException::forPaymentMethodId(
                $command->paymentMethodId,
            );
        }

        if ($this->routingTypeRepository->findByCode($command->routingTypeCode) === null) {
            throw CashRoutingTypeNotFoundException::forCode($command->routingTypeCode);
        }

        try {
            $trackingMode = InstrumentTrackingMode::from($command->instrumentTrackingMode);
        } catch (ValueError) {
            throw InvalidCashPaymentMethodRoutingException::inconsistentTracking(
                $command->routingTypeCode,
                $command->instrumentTrackingMode,
            );
        }

        $routing->update(
            routingTypeCode: $command->routingTypeCode,
            instrumentTrackingMode: $trackingMode,
            strictSourceIsolation: $command->strictSourceIsolation,
            requiresCustodyCheck: $command->requiresCustodyCheck,
            isActive: $command->isActive,
        );

        $this->routingRepository->update($routing);
        $this->unitOfWork->commit();

        return $routing;
    }
}
