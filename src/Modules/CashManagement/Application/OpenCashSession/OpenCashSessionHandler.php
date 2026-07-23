<?php

declare(strict_types=1);

namespace App\Modules\CashManagement\Application\OpenCashSession;

use App\Modules\CashManagement\Application\CashSessionPartyAccountValidator;
use App\Modules\CashManagement\Domain\Exception\CashSessionAlreadyOpenException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

/**
 * Appelle cash_open_session() via DBAL.
 *
 * Pas de UnitOfWork / persist ORM : la fonction SQL crée la ligne.
 */
final class OpenCashSessionHandler
{
    private const UNIQUE_OPEN_HOLDER_CONSTRAINT = 'uq_cash_session_one_open_per_holder';

    public function __construct(
        private readonly Connection $connection,
        private readonly CashSessionPartyAccountValidator $partyAccountValidator,
    ) {
    }

    /**
     * @return int id de cash_session créé
     */
    public function __invoke(OpenCashSessionCommand $command): int
    {
        $this->partyAccountValidator->assertHolderExists($command->holderAccountId);
        if ($command->officeAccountId !== null) {
            $this->partyAccountValidator->assertOfficeExists($command->officeAccountId);
        }
        if ($command->openedBy !== null) {
            $this->partyAccountValidator->assertOpenedByExists($command->openedBy);
        }

        try {
            $raw = $this->connection->fetchOne(
                'SELECT cash_open_session(:holder_id, :office_id, :opened_by)',
                [
                    'holder_id' => $command->holderAccountId,
                    'office_id' => $command->officeAccountId,
                    'opened_by' => $command->openedBy,
                ],
            );
        } catch (UniqueConstraintViolationException $exception) {
            if ($this->isOpenHolderUniqueViolation($exception)) {
                throw CashSessionAlreadyOpenException::forHolder($command->holderAccountId);
            }

            throw $exception;
        }

        if (is_int($raw)) {
            return $raw;
        }

        if (is_numeric($raw)) {
            return (int) $raw;
        }

        throw new \RuntimeException('cash_open_session returned a non-numeric result.');
    }

    private function isOpenHolderUniqueViolation(UniqueConstraintViolationException $exception): bool
    {
        $haystack = $exception->getMessage();
        $previous = $exception->getPrevious();
        if ($previous !== null) {
            $haystack .= ' '.$previous->getMessage();
        }

        return str_contains($haystack, self::UNIQUE_OPEN_HOLDER_CONSTRAINT);
    }
}
