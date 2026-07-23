<?php

declare(strict_types=1);

namespace App\Modules\CashManagement\Application\CloseCashSession;

use App\Modules\CashManagement\Application\CashSessionPartyAccountValidator;
use App\Modules\CashManagement\Domain\Exception\CashSessionNotFoundOrAlreadyClosedException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;

/**
 * Appelle cash_close_session() via DBAL.
 *
 * Pas de UnitOfWork / persist ORM : la fonction SQL mute le statut.
 */
final class CloseCashSessionHandler
{
    public function __construct(
        private readonly Connection $connection,
        private readonly CashSessionPartyAccountValidator $partyAccountValidator,
    ) {
    }

    public function __invoke(CloseCashSessionCommand $command): void
    {
        if ($command->closedBy !== null) {
            $this->partyAccountValidator->assertClosedByExists($command->closedBy);
        }

        try {
            $this->connection->executeStatement(
                'SELECT cash_close_session(:session_id, :closed_by)',
                [
                    'session_id' => $command->sessionId,
                    'closed_by' => $command->closedBy,
                ],
            );
        } catch (DbalException $exception) {
            if ($this->isNotFoundOrAlreadyClosed($exception)) {
                throw CashSessionNotFoundOrAlreadyClosedException::forId($command->sessionId);
            }

            throw $exception;
        }
    }

    private function isNotFoundOrAlreadyClosed(DbalException $exception): bool
    {
        $haystack = $exception->getMessage();
        $previous = $exception->getPrevious();
        if ($previous !== null) {
            $haystack .= ' '.$previous->getMessage();
        }

        return str_contains($haystack, 'introuvable ou déjà fermée');
    }
}
