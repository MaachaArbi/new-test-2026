<?php

declare(strict_types=1);

namespace App\Modules\Party\Application\CreatePartyAccountGroup;

use App\Modules\Party\Domain\Entity\PartyAccountGroup;
use App\Modules\Party\Domain\Exception\PartyAccountGroupNameAlreadyUsedException;
use App\Modules\Party\Domain\Repository\PartyAccountGroupRepositoryInterface;
use App\Modules\Party\Domain\ValueObject\PartyAccountGroupTypeCode;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * Use case : créer un groupe — vérification explicite de l'unicité
 * (group_type_code, name) AVANT écriture (uq_party_account_group_type_name
 * n'est qu'un filet DB).
 *
 * Service invocable simple (__invoke) — PAS un #[AsMessageHandler] Messenger.
 * ADR-003 (command bus) sera branché plus tard ; le futur Controller pourra
 * soit appeler ce handler directement, soit basculer vers le bus sans changer
 * la logique métier.
 */
final class CreatePartyAccountGroupHandler
{
    public function __construct(
        private readonly PartyAccountGroupRepositoryInterface $groupRepository,
        private readonly UnitOfWork $unitOfWork,
    ) {
    }

    public function __invoke(CreatePartyAccountGroupCommand $command): PartyAccountGroup
    {
        $groupTypeCode = PartyAccountGroupTypeCode::fromString($command->groupTypeCode);

        if ($this->groupRepository->existsByTypeAndName($groupTypeCode, $command->name)) {
            throw PartyAccountGroupNameAlreadyUsedException::forTypeAndName(
                $groupTypeCode->toString(),
                $command->name,
            );
        }

        $group = PartyAccountGroup::create($groupTypeCode, $command->name);
        $this->groupRepository->save($group);
        $this->unitOfWork->commit();

        return $group;
    }
}
