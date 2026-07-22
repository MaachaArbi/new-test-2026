<?php

declare(strict_types=1);

namespace App\Modules\Party\Application\ListPartyAccounts;

use Doctrine\DBAL\Connection;

/**
 * Query handler — SQL DBAL pur (ADR-003). Aucune réhydratation PartyAccount.
 *
 * Service invocable (__invoke), pas Messenger.
 */
final class ListPartyAccountsHandler
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function __invoke(ListPartyAccountsQuery $query): ListPartyAccountsResult
    {
        $where = ['deleted_at IS NULL'];
        $params = [];
        $types = [];

        if ($query->nature !== null) {
            $where[] = 'nature = :nature';
            $params['nature'] = $query->nature;
        }

        if ($query->search !== null && $query->search !== '') {
            $where[] = 'display_name ILIKE :search';
            $params['search'] = '%'.$this->escapeIlike($query->search).'%';
        }

        $whereSql = implode(' AND ', $where);

        $totalRaw = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM party_account WHERE '.$whereSql,
            $params,
            $types,
        );
        $total = is_numeric($totalRaw) ? (int) $totalRaw : 0;

        $offset = ($query->page - 1) * $query->limit;
        $params['limit'] = $query->limit;
        $params['offset'] = $offset;
        $types['limit'] = \Doctrine\DBAL\ParameterType::INTEGER;
        $types['offset'] = \Doctrine\DBAL\ParameterType::INTEGER;

        /** @var list<array{public_id: string, nature: string, display_name: string, email: string|null}> $rows */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT public_id, nature, display_name, email
             FROM party_account
             WHERE '.$whereSql.'
             ORDER BY id ASC
             LIMIT :limit OFFSET :offset',
            $params,
            $types,
        );

        $data = [];
        foreach ($rows as $row) {
            $data[] = [
                'publicId' => $row['public_id'],
                'nature' => $row['nature'],
                'displayName' => $row['display_name'],
                'email' => $row['email'],
            ];
        }

        return new ListPartyAccountsResult(
            data: $data,
            page: $query->page,
            limit: $query->limit,
            total: $total,
        );
    }

    private function escapeIlike(string $value): string
    {
        return str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\\%', '\\_'],
            $value,
        );
    }
}
