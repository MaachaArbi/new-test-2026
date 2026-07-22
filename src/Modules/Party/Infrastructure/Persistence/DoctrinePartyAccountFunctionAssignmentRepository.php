<?php

declare(strict_types=1);

namespace App\Modules\Party\Infrastructure\Persistence;

use App\Modules\Party\Domain\Entity\PartyAccountFunctionAssignment;
use App\Modules\Party\Domain\Repository\PartyAccountFunctionAssignmentRepositoryInterface;
use App\Modules\Party\Domain\ValueObject\PartyFunctionCode;
use App\Shared\Infrastructure\Persistence\UnitOfWork;
use Doctrine\DBAL\Connection;

final class DoctrinePartyAccountFunctionAssignmentRepository implements PartyAccountFunctionAssignmentRepositoryInterface
{
    public function __construct(
        private readonly UnitOfWork $unitOfWork,
        private readonly Connection $connection,
    ) {
    }

    public function findById(int $id): ?PartyAccountFunctionAssignment
    {
        /** @var PartyAccountFunctionAssignment|null $assignment */
        $assignment = $this->unitOfWork->find(PartyAccountFunctionAssignment::class, $id);

        return $assignment;
    }

    public function hasActiveFunction(
        int $personAccountId,
        int $organizationAccountId,
        PartyFunctionCode $functionCode,
    ): bool {
        $raw = $this->connection->fetchOne(
            'SELECT 1 FROM party_account_function
             WHERE person_account_id = :personAccountId
               AND organization_account_id = :organizationAccountId
               AND function_code = :functionCode
               AND valid_to IS NULL
             LIMIT 1',
            [
                'personAccountId' => $personAccountId,
                'organizationAccountId' => $organizationAccountId,
                'functionCode' => $functionCode->toString(),
            ],
        );

        return $raw !== false && $raw !== null;
    }

    public function assign(PartyAccountFunctionAssignment $assignment): void
    {
        $this->unitOfWork->persist($assignment);
    }

    /**
     * Mutation Domain (validTo) déjà appliquée par l'appelant.
     *
     * Commit (flush) is the caller's responsibility.
     */
    public function revoke(PartyAccountFunctionAssignment $assignment): void
    {
    }
}
