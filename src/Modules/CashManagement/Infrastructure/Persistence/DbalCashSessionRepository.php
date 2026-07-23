<?php

declare(strict_types=1);

namespace App\Modules\CashManagement\Infrastructure\Persistence;

use App\Modules\CashManagement\Domain\Entity\CashSession;
use App\Modules\CashManagement\Domain\Repository\CashSessionRepositoryInterface;
use App\Modules\CashManagement\Domain\ValueObject\CashSessionStatus;
use App\Shared\Domain\ValueObject\PublicId;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use RuntimeException;
use ValueError;

/**
 * Lecture cash_session — DBAL pur (ADR-003). Pas de chemin écriture.
 */
final class DbalCashSessionRepository implements CashSessionRepositoryInterface
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function findById(int $id): ?CashSession
    {
        /** @var array{
         *     id: int|string,
         *     public_id: string,
         *     holder_account_id: int|string,
         *     office_account_id: int|string|null,
         *     status_code: string,
         *     opened_at: string,
         *     opened_by: int|string|null,
         *     closed_at: string|null,
         *     closed_by: int|string|null
         * }|false $row
         */
        $row = $this->connection->fetchAssociative(
            'SELECT id, public_id, holder_account_id, office_account_id, status_code,
                    opened_at, opened_by, closed_at, closed_by
             FROM cash_session
             WHERE id = :id',
            ['id' => $id],
        );

        if ($row === false) {
            return null;
        }

        return $this->hydrate($row);
    }

    /**
     * @param array{
     *     id: int|string,
     *     public_id: string,
     *     holder_account_id: int|string,
     *     office_account_id: int|string|null,
     *     status_code: string,
     *     opened_at: string,
     *     opened_by: int|string|null,
     *     closed_at: string|null,
     *     closed_by: int|string|null
     * } $row
     */
    private function hydrate(array $row): CashSession
    {
        try {
            $status = CashSessionStatus::from($row['status_code']);
        } catch (ValueError $exception) {
            throw new RuntimeException(
                sprintf('Unknown cash_session.status_code "%s".', $row['status_code']),
                0,
                $exception,
            );
        }

        return CashSession::fromPersistence(
            id: $this->toInt($row['id']),
            publicId: PublicId::fromString($row['public_id']),
            holderAccountId: $this->toInt($row['holder_account_id']),
            officeAccountId: $row['office_account_id'] === null ? null : $this->toInt($row['office_account_id']),
            statusCode: $status,
            openedAt: new DateTimeImmutable($row['opened_at']),
            openedBy: $row['opened_by'] === null ? null : $this->toInt($row['opened_by']),
            closedAt: $row['closed_at'] === null ? null : new DateTimeImmutable($row['closed_at']),
            closedBy: $row['closed_by'] === null ? null : $this->toInt($row['closed_by']),
        );
    }

    private function toInt(int|string $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        throw new RuntimeException('Expected numeric DBAL scalar.');
    }
}
