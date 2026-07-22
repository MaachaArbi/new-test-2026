<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\ListBookings;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

/**
 * Query handler — SQL DBAL pur (ADR-003). Aucune réhydratation Booking.
 *
 * Service invocable (__invoke), pas Messenger.
 *
 * Filtrer sur booking_date (bookingDateFrom / bookingDateTo) permet le
 * partition pruning PostgreSQL : seules les partitions RANGE concernées
 * sont scannées.
 */
final class ListBookingsHandler
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function __invoke(ListBookingsQuery $query): ListBookingsResult
    {
        $where = ['1=1'];
        $params = [];
        $types = [];

        if ($query->folderId !== null) {
            $where[] = 'folder_id = :folderId';
            $params['folderId'] = $query->folderId;
            $types['folderId'] = ParameterType::INTEGER;
        }

        if ($query->customerAccountId !== null) {
            $where[] = 'customer_account_id = :customerAccountId';
            $params['customerAccountId'] = $query->customerAccountId;
            $types['customerAccountId'] = ParameterType::INTEGER;
        }

        if ($query->serviceTypeCode !== null) {
            $where[] = 'service_type_code = :serviceTypeCode';
            $params['serviceTypeCode'] = $query->serviceTypeCode;
        }

        if ($query->statusCode !== null) {
            $where[] = 'status_code = :statusCode';
            $params['statusCode'] = $query->statusCode;
        }

        if ($query->bookingDateFrom !== null) {
            $where[] = 'booking_date >= :bookingDateFrom';
            $params['bookingDateFrom'] = $query->bookingDateFrom;
        }

        if ($query->bookingDateTo !== null) {
            $where[] = 'booking_date <= :bookingDateTo';
            $params['bookingDateTo'] = $query->bookingDateTo;
        }

        $whereSql = implode(' AND ', $where);

        $totalRaw = $this->connection->fetchOne(
            'SELECT COUNT(*) FROM booking WHERE '.$whereSql,
            $params,
            $types,
        );
        $total = is_numeric($totalRaw) ? (int) $totalRaw : 0;

        $offset = ($query->page - 1) * $query->limit;
        $params['limit'] = $query->limit;
        $params['offset'] = $offset;
        $types['limit'] = ParameterType::INTEGER;
        $types['offset'] = ParameterType::INTEGER;

        /** @var list<array{
         *     public_id: string,
         *     booking_date: string,
         *     service_type_code: string,
         *     status_code: string,
         *     customer_account_id: int|string,
         *     total_vente_amount: int|string,
         *     vente_currency_code: string
         * }> $rows
         */
        $rows = $this->connection->fetchAllAssociative(
            'SELECT public_id, booking_date, service_type_code, status_code,
                    customer_account_id, total_vente_amount, vente_currency_code
             FROM booking
             WHERE '.$whereSql.'
             ORDER BY booking_date ASC, id ASC
             LIMIT :limit OFFSET :offset',
            $params,
            $types,
        );

        $data = [];
        foreach ($rows as $row) {
            $data[] = [
                'publicId' => $row['public_id'],
                'bookingDate' => $row['booking_date'],
                'serviceTypeCode' => $row['service_type_code'],
                'statusCode' => $row['status_code'],
                'customerAccountId' => (int) $row['customer_account_id'],
                'totalVenteAmount' => [
                    'amount' => (int) $row['total_vente_amount'],
                    'currencyCode' => $row['vente_currency_code'],
                ],
            ];
        }

        return new ListBookingsResult(
            data: $data,
            page: $query->page,
            limit: $query->limit,
            total: $total,
        );
    }
}
