<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Persistence;

use App\Modules\Booking\Domain\Entity\BookingCharge;
use App\Modules\Booking\Domain\Repository\BookingChargeRepositoryInterface;
use App\Shared\Infrastructure\Persistence\UnitOfWork;
use Doctrine\DBAL\Connection;

final class DoctrineBookingChargeRepository implements BookingChargeRepositoryInterface
{
    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly Connection $connection,
    ) {
    }

    /**
     * @return list<BookingCharge>
     */
    public function findByBookingId(int $bookingId): array
    {
        /** @var list<BookingCharge> $charges */
        $charges = $this->unitOfWork->createQueryBuilder()
            ->select('charge')
            ->from(BookingCharge::class, 'charge')
            ->andWhere('charge.bookingId = :bookingId')
            ->setParameter('bookingId', $bookingId)
            ->orderBy('charge.sortOrder', 'ASC')
            ->addOrderBy('charge.id', 'ASC')
            ->getQuery()
            ->getResult();

        $currencies = $this->bookingCurrencies($bookingId);
        if ($currencies !== null) {
            foreach ($charges as $charge) {
                $charge->hydrateCurrencies($currencies['achat'], $currencies['vente']);
            }
        }

        return $charges;
    }

    public function save(BookingCharge $charge): void
    {
        $this->unitOfWork->persist($charge);
    }

    /**
     * ADR-003 : devises via DBAL (2 colonnes), jamais load ORM du booking.
     *
     * @return array{achat: string, vente: string}|null
     */
    private function bookingCurrencies(int $bookingId): ?array
    {
        /** @var array{achat: string, vente: string}|false $row */
        $row = $this->connection->fetchAssociative(
            'SELECT achat_currency_code AS achat, vente_currency_code AS vente
             FROM booking
             WHERE id = :id',
            ['id' => $bookingId],
        );

        if ($row === false) {
            return null;
        }

        return [
            'achat' => $row['achat'],
            'vente' => $row['vente'],
        ];
    }
}
