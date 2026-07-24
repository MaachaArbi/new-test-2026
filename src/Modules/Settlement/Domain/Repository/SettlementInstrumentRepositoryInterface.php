<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Domain\Repository;

use App\Modules\Settlement\Domain\Entity\SettlementInstrument;
use App\Shared\Domain\ValueObject\PublicId;

interface SettlementInstrumentRepositoryInterface
{
    public function findById(int $id): ?SettlementInstrument;

    public function findByPublicId(PublicId $publicId): ?SettlementInstrument;

    public function save(SettlementInstrument $instrument): void;
}
