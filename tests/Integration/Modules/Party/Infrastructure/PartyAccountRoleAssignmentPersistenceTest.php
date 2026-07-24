<?php

declare(strict_types=1);

namespace App\Tests\Integration\Modules\Party\Infrastructure;

use App\Modules\Party\Application\AssignPartyAccountRole\AssignPartyAccountRoleCommand;
use App\Modules\Party\Application\AssignPartyAccountRole\AssignPartyAccountRoleHandler;
use App\Modules\Party\Application\RevokePartyAccountRole\RevokePartyAccountRoleCommand;
use App\Modules\Party\Application\RevokePartyAccountRole\RevokePartyAccountRoleHandler;
use App\Modules\Party\Domain\Entity\PartyAccount;
use App\Modules\Party\Domain\Entity\PartyAccountRoleAssignment;
use App\Modules\Party\Domain\Exception\InvalidPartyAccountRoleAssignmentException;
use App\Modules\Party\Domain\Exception\PartyAccountRoleAlreadyActiveException;
use App\Modules\Party\Domain\Exception\PartyAccountRoleAssignmentNotFoundException;
use App\Modules\Party\Domain\Repository\PartyAccountRepositoryInterface;
use App\Modules\Party\Domain\Repository\PartyAccountRoleAssignmentRepositoryInterface;
use App\Modules\Party\Domain\ValueObject\PartyRoleCode;
use App\Shared\Domain\ValueObject\Email;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * PostgreSQL réel — jamais SQLite.
 */
final class PartyAccountRoleAssignmentPersistenceTest extends KernelTestCase
{
    private UnitOfWork $unitOfWork;

    private EntityManagerInterface $em;

    private PartyAccountRepositoryInterface $accountRepository;

    private PartyAccountRoleAssignmentRepositoryInterface $roleAssignmentRepository;

    private AssignPartyAccountRoleHandler $assignHandler;

    private RevokePartyAccountRoleHandler $revokeHandler;

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

        /** @var PartyAccountRoleAssignmentRepositoryInterface $roleAssignmentRepository */
        $roleAssignmentRepository = $container->get(PartyAccountRoleAssignmentRepositoryInterface::class);
        $this->roleAssignmentRepository = $roleAssignmentRepository;

        $this->assignHandler = new AssignPartyAccountRoleHandler($this->roleAssignmentRepository, $this->unitOfWork);
        $this->revokeHandler = new RevokePartyAccountRoleHandler($this->roleAssignmentRepository, $this->unitOfWork);
    }

    public function test_assign_round_trip_persists_mapped_fields(): void
    {
        $account = $this->createPersonAccount('Role RoundTrip');
        $assignment = ($this->assignHandler)(new AssignPartyAccountRoleCommand(
            accountId: (int) $account->id(),
            roleCode: 'customer',
            createdBy: null,
        ));

        $id = $assignment->id();
        self::assertNotNull($id);
        $validFrom = $assignment->validFrom();

        $this->em->clear();

        $reloaded = $this->roleAssignmentRepository->findById($id);
        self::assertNotNull($reloaded);
        self::assertSame($id, $reloaded->id());
        self::assertSame($account->id(), $reloaded->accountId());
        self::assertSame('customer', $reloaded->roleCode()->toString());
        self::assertTrue($reloaded->isActive());
        self::assertNull($reloaded->validTo());
        self::assertSame(
            $validFrom->format('Y-m-d H:i:s'),
            $reloaded->validFrom()->format('Y-m-d H:i:s'),
        );
    }

    public function test_revoke_persists_valid_to(): void
    {
        $account = $this->createPersonAccount('Role Revoke');
        $assignment = ($this->assignHandler)(new AssignPartyAccountRoleCommand(
            (int) $account->id(),
            'supplier',
            null,
        ));
        $id = (int) $assignment->id();

        $assignment->revoke();
        $this->roleAssignmentRepository->revoke($assignment);
        $this->unitOfWork->commit();

        $this->em->clear();

        $reloaded = $this->roleAssignmentRepository->findById($id);
        self::assertNotNull($reloaded);
        self::assertFalse($reloaded->isActive());
        self::assertNotNull($reloaded->validTo());
    }

    public function test_handler_rejects_duplicate_active_role_before_sql(): void
    {
        $account = $this->createPersonAccount('Role Duplicate');
        $accountId = (int) $account->id();

        ($this->assignHandler)(new AssignPartyAccountRoleCommand($accountId, 'channel', null));
        self::assertSame(1, $this->countAssignments($accountId, 'channel'));

        try {
            ($this->assignHandler)(new AssignPartyAccountRoleCommand($accountId, 'channel', null));
            self::fail('Expected PartyAccountRoleAlreadyActiveException');
        } catch (PartyAccountRoleAlreadyActiveException $exception) {
            self::assertSame('party_account_role.already_active', $exception->errorCode());
            self::assertSame($accountId, $exception->context()['account_id']);
            self::assertSame('channel', $exception->context()['role_code']);
        }

        self::assertSame(1, $this->countAssignments($accountId, 'channel'));
    }

    public function test_same_role_can_be_reassigned_after_revoke(): void
    {
        $account = $this->createPersonAccount('Role Reassign');
        $accountId = (int) $account->id();

        $first = ($this->assignHandler)(new AssignPartyAccountRoleCommand($accountId, 'system', null));
        $firstId = (int) $first->id();

        $first->revoke();
        $this->roleAssignmentRepository->revoke($first);
        $this->unitOfWork->commit();

        $second = ($this->assignHandler)(new AssignPartyAccountRoleCommand($accountId, 'system', null));
        $secondId = (int) $second->id();

        self::assertNotSame($firstId, $secondId);
        self::assertSame(2, $this->countAssignments($accountId, 'system'));

        $this->em->clear();

        $closed = $this->roleAssignmentRepository->findById($firstId);
        $active = $this->roleAssignmentRepository->findById($secondId);
        self::assertNotNull($closed);
        self::assertNotNull($active);
        self::assertFalse($closed->isActive());
        self::assertNotNull($closed->validTo());
        self::assertTrue($active->isActive());
        self::assertNull($active->validTo());
        self::assertTrue(
            $this->roleAssignmentRepository->hasActiveRole($accountId, PartyRoleCode::fromString('system')),
        );
    }

    public function test_revoke_handler_persists_valid_to(): void
    {
        $account = $this->createPersonAccount('Role Revoke Handler');
        $assignment = ($this->assignHandler)(new AssignPartyAccountRoleCommand(
            (int) $account->id(),
            'customer',
            null,
        ));
        $id = (int) $assignment->id();

        $revoked = ($this->revokeHandler)(new RevokePartyAccountRoleCommand($id));
        self::assertFalse($revoked->isActive());
        self::assertNotNull($revoked->validTo());

        $this->em->clear();

        $reloaded = $this->roleAssignmentRepository->findById($id);
        self::assertNotNull($reloaded);
        self::assertFalse($reloaded->isActive());
        self::assertNotNull($reloaded->validTo());
    }

    public function test_revoke_handler_rejects_missing_assignment(): void
    {
        $missingId = 999_999_992;

        try {
            ($this->revokeHandler)(new RevokePartyAccountRoleCommand($missingId));
            self::fail('Expected PartyAccountRoleAssignmentNotFoundException');
        } catch (PartyAccountRoleAssignmentNotFoundException $exception) {
            self::assertSame('party_account_role.assignment_not_found', $exception->errorCode());
            self::assertSame(['assignment_id' => $missingId], $exception->context());
        }
    }

    public function test_revoke_handler_propagates_already_revoked(): void
    {
        $account = $this->createPersonAccount('Role Already Revoked');
        $assignment = ($this->assignHandler)(new AssignPartyAccountRoleCommand(
            (int) $account->id(),
            'supplier',
            null,
        ));
        $id = (int) $assignment->id();

        ($this->revokeHandler)(new RevokePartyAccountRoleCommand($id));

        try {
            ($this->revokeHandler)(new RevokePartyAccountRoleCommand($id));
            self::fail('Expected InvalidPartyAccountRoleAssignmentException');
        } catch (InvalidPartyAccountRoleAssignmentException $exception) {
            self::assertSame('party_account_role.already_revoked', $exception->errorCode());
            self::assertSame((int) $account->id(), $exception->context()['account_id']);
            self::assertSame('supplier', $exception->context()['role_code']);
        }
    }

    private function createPersonAccount(string $label): PartyAccount
    {
        $suffix = bin2hex(random_bytes(4));
        $account = PartyAccount::createPerson(
            $label.' '.$suffix,
            Email::fromString('role.'.$suffix.'@example.com'),
        );
        $this->accountRepository->save($account);
        $this->unitOfWork->commit();
        self::assertNotNull($account->id());

        return $account;
    }

    private function countAssignments(int $accountId, string $roleCode): int
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(a.id)')
            ->from(PartyAccountRoleAssignment::class, 'a')
            ->where('a.accountId = :accountId')
            ->andWhere('a.roleCode = :roleCode')
            ->setParameter('accountId', $accountId)
            ->setParameter('roleCode', $roleCode)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
