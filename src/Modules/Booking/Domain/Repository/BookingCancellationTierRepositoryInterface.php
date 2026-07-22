<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Repository;

use App\Modules\Booking\Domain\Entity\BookingCancellationTier;

interface BookingCancellationTierRepositoryInterface
{
    /**
     * @return list<BookingCancellationTier>
     */
    public function findByPolicyId(int $policyId): array;

    public function save(BookingCancellationTier $tier): void;
}
