<?php

declare(strict_types=1);

namespace App\Modules\CashManagement\Application\CreateCashPaymentMethodRouting;

use App\Modules\CashManagement\Domain\Entity\CashPaymentMethodRouting;
use App\Modules\CashManagement\Domain\Exception\CashPaymentMethodRoutingAlreadyExistsException;
use App\Modules\CashManagement\Domain\Exception\CashRoutingTypeNotFoundException;
use App\Modules\CashManagement\Domain\Exception\InvalidCashPaymentMethodRoutingException;
use App\Modules\CashManagement\Domain\Repository\CashPaymentMethodRoutingRepositoryInterface;
use App\Modules\CashManagement\Domain\Repository\CashRoutingTypeRepositoryInterface;
use App\Modules\CashManagement\Domain\ValueObject\InstrumentTrackingMode;
use App\Modules\Reglements\Domain\Exception\ReglementPaymentMethodInactiveException;
use App\Modules\Reglements\Domain\Repository\ReglementPaymentMethodRepositoryInterface;
use App\Shared\Infrastructure\Persistence\UnitOfWork;
use ValueError;

final class CreateCashPaymentMethodRoutingHandler
{
    public function __construct(
        private readonly ReglementPaymentMethodRepositoryInterface $paymentMethodRepository,
        private readonly CashRoutingTypeRepositoryInterface $routingTypeRepository,
        private readonly CashPaymentMethodRoutingRepositoryInterface $routingRepository,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function __invoke(CreateCashPaymentMethodRoutingCommand $command): CashPaymentMethodRouting
    {
        $paymentMethod = $this->paymentMethodRepository->findById($command->paymentMethodId);
        if ($paymentMethod === null) {
            throw ReglementPaymentMethodInactiveException::forId($command->paymentMethodId);
        }

        if ($this->routingTypeRepository->findByCode($command->routingTypeCode) === null) {
            throw CashRoutingTypeNotFoundException::forCode($command->routingTypeCode);
        }

        if ($this->routingRepository->findByPaymentMethodId($command->paymentMethodId) !== null) {
            throw CashPaymentMethodRoutingAlreadyExistsException::forPaymentMethodId(
                $command->paymentMethodId,
            );
        }

        try {
            $trackingMode = InstrumentTrackingMode::from($command->instrumentTrackingMode);
        } catch (ValueError) {
            throw InvalidCashPaymentMethodRoutingException::inconsistentTracking(
                $command->routingTypeCode,
                $command->instrumentTrackingMode,
            );
        }

        $routing = CashPaymentMethodRouting::create(
            paymentMethodId: $command->paymentMethodId,
            routingTypeCode: $command->routingTypeCode,
            instrumentTrackingMode: $trackingMode,
            strictSourceIsolation: $command->strictSourceIsolation,
            requiresCustodyCheck: $command->requiresCustodyCheck,
            isActive: $command->isActive,
        );

        $this->routingRepository->create($routing);
        $this->unitOfWork->commit();

        return $routing;
    }
}
