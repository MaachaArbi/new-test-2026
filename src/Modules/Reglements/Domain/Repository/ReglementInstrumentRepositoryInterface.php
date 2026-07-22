<?php

declare(strict_types=1);

namespace App\Modules\Reglements\Domain\Repository;

use App\Modules\Reglements\Domain\Entity\ReglementInstrument;
use App\Shared\Domain\ValueObject\PublicId;

interface ReglementInstrumentRepositoryInterface
{
    public function findById(int $id): ?ReglementInstrument;

    public function findByPublicId(PublicId $publicId): ?ReglementInstrument;

    public function save(ReglementInstrument $instrument): void;
}
