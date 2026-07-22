<?php

declare(strict_types=1);

namespace App\Modules\Party\Application\AssignPartyAccountFunction;

use App\Modules\Party\Domain\Entity\PartyAccountFunctionAssignment;
use App\Modules\Party\Domain\Exception\PartyAccountFunctionAlreadyActiveException;
use App\Modules\Party\Domain\Repository\PartyAccountFunctionAssignmentRepositoryInterface;
use App\Modules\Party\Domain\ValueObject\PartyFunctionCode;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * Use case : assigner une fonction — vérification explicite du doublon actif
 * AVANT écriture (la contrainte uq_party_account_function_active n'est qu'un filet DB).
 *
 * Service invocable simple (__invoke) — PAS un #[AsMessageHandler] Messenger.
 * ADR-003 (command bus) sera branché plus tard ; le futur Controller pourra
 * soit appeler ce handler directement, soit basculer vers le bus sans changer
 * la logique métier.
 */
final class AssignPartyAccountFunctionHandler
{
    public function __construct(
        private readonly PartyAccountFunctionAssignmentRepositoryInterface $functionAssignmentRepository,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function __invoke(AssignPartyAccountFunctionCommand $command): PartyAccountFunctionAssignment
    {
        $functionCode = PartyFunctionCode::fromString($command->functionCode);

        if ($this->functionAssignmentRepository->hasActiveFunction(
            $command->personAccountId,
            $command->organizationAccountId,
            $functionCode,
        )) {
            throw PartyAccountFunctionAlreadyActiveException::forTriplet(
                $command->personAccountId,
                $command->organizationAccountId,
                $functionCode->toString(),
            );
        }

        $assignment = PartyAccountFunctionAssignment::assign(
            $command->personAccountId,
            $command->organizationAccountId,
            $functionCode,
            $command->createdBy,
        );

        $this->functionAssignmentRepository->assign($assignment);
        $this->unitOfWork->commit();

        return $assignment;
    }
}
