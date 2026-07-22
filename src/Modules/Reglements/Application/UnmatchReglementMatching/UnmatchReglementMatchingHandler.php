<?php

declare(strict_types=1);

namespace App\Modules\Reglements\Application\UnmatchReglementMatching;

use App\Modules\Reglements\Domain\Entity\ReglementMatching;
use App\Modules\Reglements\Domain\Exception\ReglementMatchingNotFoundException;
use App\Modules\Reglements\Domain\Repository\ReglementMatchingRepositoryInterface;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

final class UnmatchReglementMatchingHandler
{
    public function __construct(
        private readonly ReglementMatchingRepositoryInterface $matchingRepository,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function __invoke(UnmatchReglementMatchingCommand $command): ReglementMatching
    {
        $matching = $this->matchingRepository->findById($command->matchingId);
        if ($matching === null) {
            throw ReglementMatchingNotFoundException::forId($command->matchingId);
        }

        $matching->unmatch($command->unmatchedBy);
        $this->matchingRepository->unmatch($matching);
        $this->unitOfWork->commit();

        return $matching;
    }
}
