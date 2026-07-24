<?php

declare(strict_types=1);

namespace App\Modules\CashManagement\Application\ReceiveCashInstrument;

use App\Modules\CashManagement\Domain\Exception\CashReceiveInstrumentAlreadyInSessionException;
use App\Modules\CashManagement\Domain\Exception\CashReceiveInstrumentNotActiveException;
use App\Modules\CashManagement\Domain\Exception\CashReceiveInstrumentNotFoundException;
use App\Modules\CashManagement\Domain\Exception\CashReceiveInstrumentRoutingNotCaisseException;
use App\Modules\CashManagement\Domain\Exception\CashReceiveReceivedByNotFoundException;
use App\Modules\CashManagement\Domain\Exception\CashSessionNotOpenException;
use App\Modules\CashManagement\Domain\Repository\CashPaymentMethodRoutingRepositoryInterface;
use App\Modules\CashManagement\Domain\Repository\CashSessionRepositoryInterface;
use App\Modules\CashManagement\Domain\ValueObject\CashSessionStatus;
use App\Modules\Settlement\Domain\Repository\SettlementInstrumentRepositoryInterface;
use App\Modules\Settlement\Domain\ValueObject\SettlementInstrumentStatus;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

/**
 * Appelle cash_receive_instrument() via DBAL.
 *
 * Cinq validations Application avant SQL (ordre fixe) — voir journal.
 */
final class ReceiveCashInstrumentHandler
{
    private const UNIQUE_INSTRUMENT_PER_SESSION = 'uq_cash_movement_instrument_per_session';

    public function __construct(
        private readonly Connection $connection,
        private readonly CashSessionRepositoryInterface $sessionRepository,
        private readonly SettlementInstrumentRepositoryInterface $instrumentRepository,
        private readonly CashPaymentMethodRoutingRepositoryInterface $routingRepository,
    ) {
    }

    /**
     * @return int id du cash_movement créé
     */
    public function __invoke(ReceiveCashInstrumentCommand $command): int
    {
        if ($command->receivedBy !== null) {
            $this->assertReceivedByExists($command->receivedBy);
        }

        // 1. Session strictement open
        $session = $this->sessionRepository->findById($command->sessionId);
        if ($session === null || $session->statusCode() !== CashSessionStatus::Open) {
            throw CashSessionNotOpenException::forId(
                $command->sessionId,
                $session?->statusCode()->value,
            );
        }

        // 2. Instrument existe et actif
        $instrument = $this->instrumentRepository->findById($command->instrumentId);
        if ($instrument === null) {
            throw CashReceiveInstrumentNotFoundException::forId($command->instrumentId);
        }
        if ($instrument->statusCode() !== SettlementInstrumentStatus::Active) {
            throw CashReceiveInstrumentNotActiveException::forId(
                $command->instrumentId,
                $instrument->statusCode()->value,
            );
        }

        // 3. Routing = caisse (absence = rejet)
        $routing = $this->routingRepository->findByPaymentMethodId($instrument->paymentMethodId());
        if ($routing === null || $routing->routingTypeCode() !== 'caisse') {
            throw CashReceiveInstrumentRoutingNotCaisseException::forPaymentMethod(
                $instrument->paymentMethodId(),
                $routing?->routingTypeCode(),
            );
        }

        // 4. Doublon même-session — structurel SQL (23505), pas pré-vérifié ici
        try {
            $raw = $this->connection->fetchOne(
                'SELECT cash_receive_instrument(:session_id, :instrument_id, :received_by)',
                [
                    'session_id' => $command->sessionId,
                    'instrument_id' => $command->instrumentId,
                    'received_by' => $command->receivedBy,
                ],
            );
        } catch (UniqueConstraintViolationException $exception) {
            if ($this->isInstrumentPerSessionUniqueViolation($exception)) {
                throw CashReceiveInstrumentAlreadyInSessionException::forSessionAndInstrument(
                    $command->sessionId,
                    $command->instrumentId,
                );
            }

            throw $exception;
        } catch (DbalException $exception) {
            // 5. Défensif — RAISE SQL "Instrument % introuvable" (normalement inatteignable)
            if ($this->isSqlInstrumentNotFound($exception)) {
                throw CashReceiveInstrumentNotFoundException::forId($command->instrumentId);
            }

            throw $exception;
        }

        if (is_int($raw)) {
            return $raw;
        }

        if (is_numeric($raw)) {
            return (int) $raw;
        }

        throw new \RuntimeException('cash_receive_instrument returned a non-numeric result.');
    }

    private function assertReceivedByExists(int $accountId): void
    {
        $raw = $this->connection->fetchOne(
            'SELECT 1 FROM party_account WHERE id = :id AND deleted_at IS NULL',
            ['id' => $accountId],
        );

        if ($raw === false || $raw === null) {
            throw CashReceiveReceivedByNotFoundException::forId($accountId);
        }
    }

    private function isInstrumentPerSessionUniqueViolation(UniqueConstraintViolationException $exception): bool
    {
        $haystack = $exception->getMessage();
        $previous = $exception->getPrevious();
        if ($previous !== null) {
            $haystack .= ' '.$previous->getMessage();
        }

        return str_contains($haystack, self::UNIQUE_INSTRUMENT_PER_SESSION);
    }

    private function isSqlInstrumentNotFound(DbalException $exception): bool
    {
        $haystack = $exception->getMessage();
        $previous = $exception->getPrevious();
        if ($previous !== null) {
            $haystack .= ' '.$previous->getMessage();
        }

        return (bool) preg_match('/Instrument \d+ introuvable/', $haystack);
    }
}
