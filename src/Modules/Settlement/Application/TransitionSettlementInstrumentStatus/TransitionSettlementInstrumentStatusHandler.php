<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\TransitionSettlementInstrumentStatus;

use App\Modules\Settlement\Domain\Entity\SettlementInstrument;
use App\Modules\Settlement\Domain\Exception\InvalidSettlementInstrumentStatusException;
use App\Modules\Settlement\Domain\Exception\SettlementInstrumentNotFoundException;
use App\Modules\Settlement\Domain\Repository\SettlementInstrumentRepositoryInterface;
use App\Modules\Settlement\Domain\ValueObject\SettlementInstrumentStatus;
use App\Shared\Infrastructure\Persistence\UnitOfWork;
use ValueError;

/**
 * Use case : transitionner le statut d'un instrument.
 *
 * Pas d'écriture grand livre ici — voir note Domain::transitionStatus().
 */
final class TransitionSettlementInstrumentStatusHandler
{
    public function __construct(
        private readonly SettlementInstrumentRepositoryInterface $instrumentRepository,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function __invoke(TransitionSettlementInstrumentStatusCommand $command): SettlementInstrument
    {
        $instrument = $this->instrumentRepository->findById($command->instrumentId);
        if ($instrument === null) {
            throw SettlementInstrumentNotFoundException::forId($command->instrumentId);
        }

        try {
            $newStatus = SettlementInstrumentStatus::from($command->statusCode);
        } catch (ValueError) {
            throw InvalidSettlementInstrumentStatusException::forValue($command->statusCode);
        }

        $instrument->transitionStatus($newStatus, $command->reason);
        $this->instrumentRepository->save($instrument);
        $this->unitOfWork->commit();

        return $instrument;
    }
}
