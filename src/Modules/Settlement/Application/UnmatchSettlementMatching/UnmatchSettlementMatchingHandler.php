<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\UnmatchSettlementMatching;

use App\Modules\Settlement\Domain\Entity\SettlementMatching;
use App\Modules\Settlement\Domain\Exception\SettlementMatchingNotFoundException;
use App\Modules\Settlement\Domain\Repository\SettlementMatchingRepositoryInterface;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

final class UnmatchSettlementMatchingHandler
{
    public function __construct(
        private readonly SettlementMatchingRepositoryInterface $matchingRepository,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function __invoke(UnmatchSettlementMatchingCommand $command): SettlementMatching
    {
        $matching = $this->matchingRepository->findById($command->matchingId);
        if ($matching === null) {
            throw SettlementMatchingNotFoundException::forId($command->matchingId);
        }

        $matching->unmatch($command->unmatchedBy);
        $this->matchingRepository->unmatch($matching);
        $this->unitOfWork->commit();

        return $matching;
    }
}
