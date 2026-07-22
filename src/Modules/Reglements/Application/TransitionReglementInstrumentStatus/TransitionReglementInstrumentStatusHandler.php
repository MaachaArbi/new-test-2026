<?php

declare(strict_types=1);

namespace App\Modules\Reglements\Application\TransitionReglementInstrumentStatus;

use App\Modules\Reglements\Domain\Entity\ReglementInstrument;
use App\Modules\Reglements\Domain\Exception\InvalidReglementInstrumentStatusException;
use App\Modules\Reglements\Domain\Exception\ReglementInstrumentNotFoundException;
use App\Modules\Reglements\Domain\Repository\ReglementInstrumentRepositoryInterface;
use App\Modules\Reglements\Domain\ValueObject\ReglementInstrumentStatus;
use App\Shared\Infrastructure\Persistence\UnitOfWork;
use ValueError;

/**
 * Use case : transitionner le statut d'un instrument.
 *
 * Pas d'écriture grand livre ici — voir note Domain::transitionStatus().
 */
final class TransitionReglementInstrumentStatusHandler
{
    public function __construct(
        private readonly ReglementInstrumentRepositoryInterface $instrumentRepository,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function __invoke(TransitionReglementInstrumentStatusCommand $command): ReglementInstrument
    {
        $instrument = $this->instrumentRepository->findById($command->instrumentId);
        if ($instrument === null) {
            throw ReglementInstrumentNotFoundException::forId($command->instrumentId);
        }

        try {
            $newStatus = ReglementInstrumentStatus::from($command->statusCode);
        } catch (ValueError) {
            throw InvalidReglementInstrumentStatusException::forValue($command->statusCode);
        }

        $instrument->transitionStatus($newStatus, $command->reason);
        $this->instrumentRepository->save($instrument);
        $this->unitOfWork->commit();

        return $instrument;
    }
}
