<?php

declare(strict_types=1);

namespace App\Tests\Integration\Modules\Party\Infrastructure;

use App\Modules\Party\Application\AssignPartyAccountGroupMembership\AssignPartyAccountGroupMembershipCommand;
use App\Modules\Party\Application\AssignPartyAccountGroupMembership\AssignPartyAccountGroupMembershipHandler;
use App\Modules\Party\Application\CreatePartyAccountGroup\CreatePartyAccountGroupCommand;
use App\Modules\Party\Application\CreatePartyAccountGroup\CreatePartyAccountGroupHandler;
use App\Modules\Party\Application\RevokePartyAccountGroupMembership\RevokePartyAccountGroupMembershipCommand;
use App\Modules\Party\Application\RevokePartyAccountGroupMembership\RevokePartyAccountGroupMembershipHandler;
use App\Modules\Party\Domain\Entity\PartyAccount;
use App\Modules\Party\Domain\Entity\PartyAccountGroupMembership;
use App\Modules\Party\Domain\Exception\InvalidPartyAccountGroupMembershipException;
use App\Modules\Party\Domain\Exception\PartyAccountGroupMembershipAlreadyActiveException;
use App\Modules\Party\Domain\Exception\PartyAccountGroupMembershipNotFoundException;
use App\Modules\Party\Domain\Repository\PartyAccountGroupMembershipRepositoryInterface;
use App\Modules\Party\Domain\Repository\PartyAccountGroupRepositoryInterface;
use App\Modules\Party\Domain\Repository\PartyAccountRepositoryInterface;
use App\Shared\Domain\ValueObject\Email;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * PostgreSQL réel — jamais SQLite.
 * Unicité active = paire (account_id, group_id) — pas d'exclusivité par type.
 */
final class PartyAccountGroupMembershipPersistenceTest extends KernelTestCase
{
    private UnitOfWork $unitOfWork;

    private EntityManagerInterface $em;

    private PartyAccountRepositoryInterface $accountRepository;

    private PartyAccountGroupRepositoryInterface $groupRepository;

    private PartyAccountGroupMembershipRepositoryInterface $membershipRepository;

    private CreatePartyAccountGroupHandler $createGroupHandler;

    private AssignPartyAccountGroupMembershipHandler $assignHandler;

    private RevokePartyAccountGroupMembershipHandler $revokeHandler;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        /** @var UnitOfWork $unitOfWork */
        $unitOfWork = $container->get(UnitOfWork::class);
        $this->unitOfWork = $unitOfWork;

        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        $this->em = $em;

        /** @var PartyAccountRepositoryInterface $accountRepository */
        $accountRepository = $container->get(PartyAccountRepositoryInterface::class);
        $this->accountRepository = $accountRepository;

        /** @var PartyAccountGroupRepositoryInterface $groupRepository */
        $groupRepository = $container->get(PartyAccountGroupRepositoryInterface::class);
        $this->groupRepository = $groupRepository;

        /** @var PartyAccountGroupMembershipRepositoryInterface $membershipRepository */
        $membershipRepository = $container->get(PartyAccountGroupMembershipRepositoryInterface::class);
        $this->membershipRepository = $membershipRepository;

        $this->createGroupHandler = new CreatePartyAccountGroupHandler($this->groupRepository, $this->unitOfWork);
        $this->assignHandler = new AssignPartyAccountGroupMembershipHandler($this->membershipRepository, $this->unitOfWork);
        $this->revokeHandler = new RevokePartyAccountGroupMembershipHandler($this->membershipRepository, $this->unitOfWork);
    }

    public function test_assign_round_trip_persists_mapped_fields(): void
    {
        $account = $this->createPersonAccount('Grp RT Account');
        $group = $this->createCommercialGroup('Grp RT Group');

        $membership = ($this->assignHandler)(new AssignPartyAccountGroupMembershipCommand(
            accountId: (int) $account->id(),
            groupId: (int) $group->id(),
            createdBy: null,
        ));

        $id = $membership->id();
        self::assertNotNull($id);
        $validFrom = $membership->validFrom();

        $this->em->clear();

        $reloaded = $this->membershipRepository->findById($id);
        self::assertNotNull($reloaded);
        self::assertSame($id, $reloaded->id());
        self::assertSame($account->id(), $reloaded->accountId());
        self::assertSame($group->id(), $reloaded->groupId());
        self::assertTrue($reloaded->isActive());
        self::assertNull($reloaded->validTo());
        self::assertSame(
            $validFrom->format('Y-m-d H:i:s'),
            $reloaded->validFrom()->format('Y-m-d H:i:s'),
        );
    }

    public function test_revoke_persists_valid_to(): void
    {
        $account = $this->createPersonAccount('Grp Revoke Account');
        $group = $this->createCommercialGroup('Grp Revoke Group');

        $membership = ($this->assignHandler)(new AssignPartyAccountGroupMembershipCommand(
            (int) $account->id(),
            (int) $group->id(),
            null,
        ));
        $id = (int) $membership->id();

        $membership->revoke();
        $this->membershipRepository->revoke($membership);
        $this->unitOfWork->commit();

        $this->em->clear();

        $reloaded = $this->membershipRepository->findById($id);
        self::assertNotNull($reloaded);
        self::assertFalse($reloaded->isActive());
        self::assertNotNull($reloaded->validTo());
    }

    public function test_same_account_can_join_two_commercial_groups_simultaneously(): void
    {
        $account = $this->createPersonAccount('Grp TwoGroups Account');
        $groupA = $this->createCommercialGroup('Grp TwoGroups A');
        $groupB = $this->createCommercialGroup('Grp TwoGroups B');
        $accountId = (int) $account->id();
        $groupAId = (int) $groupA->id();
        $groupBId = (int) $groupB->id();

        $first = ($this->assignHandler)(new AssignPartyAccountGroupMembershipCommand($accountId, $groupAId, null));
        $second = ($this->assignHandler)(new AssignPartyAccountGroupMembershipCommand($accountId, $groupBId, null));

        self::assertTrue($this->membershipRepository->hasActiveMembership($accountId, $groupAId));
        self::assertTrue($this->membershipRepository->hasActiveMembership($accountId, $groupBId));
        self::assertNotSame($first->id(), $second->id());

        $this->em->clear();

        $reloadedA = $this->membershipRepository->findById((int) $first->id());
        $reloadedB = $this->membershipRepository->findById((int) $second->id());
        self::assertNotNull($reloadedA);
        self::assertNotNull($reloadedB);
        self::assertTrue($reloadedA->isActive());
        self::assertTrue($reloadedB->isActive());
        self::assertSame($groupAId, $reloadedA->groupId());
        self::assertSame($groupBId, $reloadedB->groupId());
    }

    public function test_handler_rejects_duplicate_active_pair_before_sql(): void
    {
        $account = $this->createPersonAccount('Grp Dup Account');
        $group = $this->createCommercialGroup('Grp Dup Group');
        $accountId = (int) $account->id();
        $groupId = (int) $group->id();

        ($this->assignHandler)(new AssignPartyAccountGroupMembershipCommand($accountId, $groupId, null));
        self::assertSame(1, $this->countMemberships($accountId, $groupId));

        try {
            ($this->assignHandler)(new AssignPartyAccountGroupMembershipCommand($accountId, $groupId, null));
            self::fail('Expected PartyAccountGroupMembershipAlreadyActiveException');
        } catch (PartyAccountGroupMembershipAlreadyActiveException $exception) {
            self::assertSame('party_account_group_member.already_active', $exception->errorCode());
            self::assertSame($accountId, $exception->context()['account_id']);
            self::assertSame($groupId, $exception->context()['group_id']);
        }

        self::assertSame(1, $this->countMemberships($accountId, $groupId));
    }

    public function test_same_pair_can_be_reassigned_after_revoke(): void
    {
        $account = $this->createPersonAccount('Grp Reassign Account');
        $group = $this->createCommercialGroup('Grp Reassign Group');
        $accountId = (int) $account->id();
        $groupId = (int) $group->id();

        $first = ($this->assignHandler)(new AssignPartyAccountGroupMembershipCommand($accountId, $groupId, null));
        $firstId = (int) $first->id();

        $first->revoke();
        $this->membershipRepository->revoke($first);
        $this->unitOfWork->commit();

        $second = ($this->assignHandler)(new AssignPartyAccountGroupMembershipCommand($accountId, $groupId, null));
        $secondId = (int) $second->id();

        self::assertNotSame($firstId, $secondId);
        self::assertSame(2, $this->countMemberships($accountId, $groupId));

        $this->em->clear();

        $closed = $this->membershipRepository->findById($firstId);
        $active = $this->membershipRepository->findById($secondId);
        self::assertNotNull($closed);
        self::assertNotNull($active);
        self::assertFalse($closed->isActive());
        self::assertNotNull($closed->validTo());
        self::assertTrue($active->isActive());
        self::assertNull($active->validTo());
        self::assertTrue($this->membershipRepository->hasActiveMembership($accountId, $groupId));
    }

    public function test_revoke_handler_persists_valid_to(): void
    {
        $account = $this->createPersonAccount('Grp Revoke Handler Account');
        $group = $this->createCommercialGroup('Grp Revoke Handler Group');

        $membership = ($this->assignHandler)(new AssignPartyAccountGroupMembershipCommand(
            (int) $account->id(),
            (int) $group->id(),
            null,
        ));
        $id = (int) $membership->id();

        $revoked = ($this->revokeHandler)(new RevokePartyAccountGroupMembershipCommand($id));
        self::assertFalse($revoked->isActive());
        self::assertNotNull($revoked->validTo());

        $this->em->clear();

        $reloaded = $this->membershipRepository->findById($id);
        self::assertNotNull($reloaded);
        self::assertFalse($reloaded->isActive());
        self::assertNotNull($reloaded->validTo());
    }

    public function test_revoke_handler_rejects_missing_membership(): void
    {
        $missingId = 999_999_994;

        try {
            ($this->revokeHandler)(new RevokePartyAccountGroupMembershipCommand($missingId));
            self::fail('Expected PartyAccountGroupMembershipNotFoundException');
        } catch (PartyAccountGroupMembershipNotFoundException $exception) {
            self::assertSame('party_account_group_member.membership_not_found', $exception->errorCode());
            self::assertSame(['membership_id' => $missingId], $exception->context());
        }
    }

    public function test_revoke_handler_propagates_already_revoked(): void
    {
        $account = $this->createPersonAccount('Grp Already Revoked Account');
        $group = $this->createCommercialGroup('Grp Already Revoked Group');

        $membership = ($this->assignHandler)(new AssignPartyAccountGroupMembershipCommand(
            (int) $account->id(),
            (int) $group->id(),
            null,
        ));
        $id = (int) $membership->id();

        ($this->revokeHandler)(new RevokePartyAccountGroupMembershipCommand($id));

        try {
            ($this->revokeHandler)(new RevokePartyAccountGroupMembershipCommand($id));
            self::fail('Expected InvalidPartyAccountGroupMembershipException');
        } catch (InvalidPartyAccountGroupMembershipException $exception) {
            self::assertSame('party_account_group_member.already_revoked', $exception->errorCode());
            self::assertSame((int) $account->id(), $exception->context()['account_id']);
            self::assertSame((int) $group->id(), $exception->context()['group_id']);
        }
    }

    private function createPersonAccount(string $label): PartyAccount
    {
        $suffix = bin2hex(random_bytes(4));
        $account = PartyAccount::createPerson(
            $label.' '.$suffix,
            Email::fromString('grp.'.$suffix.'@example.com'),
        );
        $this->accountRepository->save($account);
        $this->unitOfWork->commit();
        self::assertNotNull($account->id());

        return $account;
    }

    private function createCommercialGroup(string $label): \App\Modules\Party\Domain\Entity\PartyAccountGroup
    {
        $suffix = bin2hex(random_bytes(4));

        return ($this->createGroupHandler)(new CreatePartyAccountGroupCommand(
            'commercial',
            $label.' '.$suffix,
        ));
    }

    private function countMemberships(int $accountId, int $groupId): int
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(m.id)')
            ->from(PartyAccountGroupMembership::class, 'm')
            ->where('m.accountId = :accountId')
            ->andWhere('m.groupId = :groupId')
            ->setParameter('accountId', $accountId)
            ->setParameter('groupId', $groupId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
