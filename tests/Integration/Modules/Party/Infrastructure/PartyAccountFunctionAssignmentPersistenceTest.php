<?php

declare(strict_types=1);

namespace App\Tests\Integration\Modules\Party\Infrastructure;

use App\Modules\Party\Application\AssignPartyAccountFunction\AssignPartyAccountFunctionCommand;
use App\Modules\Party\Application\AssignPartyAccountFunction\AssignPartyAccountFunctionHandler;
use App\Modules\Party\Application\RevokePartyAccountFunction\RevokePartyAccountFunctionCommand;
use App\Modules\Party\Application\RevokePartyAccountFunction\RevokePartyAccountFunctionHandler;
use App\Modules\Party\Domain\Entity\PartyAccount;
use App\Modules\Party\Domain\Entity\PartyAccountFunctionAssignment;
use App\Modules\Party\Domain\Exception\InvalidPartyAccountFunctionAssignmentException;
use App\Modules\Party\Domain\Exception\PartyAccountFunctionAlreadyActiveException;
use App\Modules\Party\Domain\Exception\PartyAccountFunctionAssignmentNotFoundException;
use App\Modules\Party\Domain\Repository\PartyAccountFunctionAssignmentRepositoryInterface;
use App\Modules\Party\Domain\Repository\PartyAccountRepositoryInterface;
use App\Modules\Party\Domain\ValueObject\PartyFunctionCode;
use App\Shared\Domain\ValueObject\Email;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use App\Shared\Infrastructure\Persistence\UnitOfWork;

/**
 * PostgreSQL réel — jamais SQLite.
 * Unicité active = triplet (person, function_code, organization).
 */
final class PartyAccountFunctionAssignmentPersistenceTest extends KernelTestCase
{
    private UnitOfWork $unitOfWork;

    private EntityManagerInterface $em;

    private PartyAccountRepositoryInterface $accountRepository;

    private PartyAccountFunctionAssignmentRepositoryInterface $functionAssignmentRepository;

    private AssignPartyAccountFunctionHandler $assignHandler;

    private RevokePartyAccountFunctionHandler $revokeHandler;

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

        /** @var PartyAccountFunctionAssignmentRepositoryInterface $functionAssignmentRepository */
        $functionAssignmentRepository = $container->get(PartyAccountFunctionAssignmentRepositoryInterface::class);
        $this->functionAssignmentRepository = $functionAssignmentRepository;

        $this->assignHandler = new AssignPartyAccountFunctionHandler($this->functionAssignmentRepository, $this->unitOfWork);
        $this->revokeHandler = new RevokePartyAccountFunctionHandler($this->functionAssignmentRepository, $this->unitOfWork);
    }

    public function test_assign_round_trip_persists_mapped_fields(): void
    {
        $person = $this->createPersonAccount('Fn RoundTrip Person');
        $org = $this->createOrganizationAccount('Fn RoundTrip Org');

        $assignment = ($this->assignHandler)(new AssignPartyAccountFunctionCommand(
            personAccountId: (int) $person->id(),
            organizationAccountId: (int) $org->id(),
            functionCode: 'member',
            createdBy: null,
        ));

        $id = $assignment->id();
        self::assertNotNull($id);
        $validFrom = $assignment->validFrom();

        $this->em->clear();

        $reloaded = $this->functionAssignmentRepository->findById($id);
        self::assertNotNull($reloaded);
        self::assertSame($id, $reloaded->id());
        self::assertSame($person->id(), $reloaded->personAccountId());
        self::assertSame($org->id(), $reloaded->organizationAccountId());
        self::assertSame('member', $reloaded->functionCode()->toString());
        self::assertTrue($reloaded->isActive());
        self::assertNull($reloaded->validTo());
        self::assertSame(
            $validFrom->format('Y-m-d H:i:s'),
            $reloaded->validFrom()->format('Y-m-d H:i:s'),
        );
    }

    public function test_revoke_persists_valid_to(): void
    {
        $person = $this->createPersonAccount('Fn Revoke Person');
        $org = $this->createOrganizationAccount('Fn Revoke Org');

        $assignment = ($this->assignHandler)(new AssignPartyAccountFunctionCommand(
            (int) $person->id(),
            (int) $org->id(),
            'manager',
            null,
        ));
        $id = (int) $assignment->id();

        $assignment->revoke();
        $this->functionAssignmentRepository->revoke($assignment);
        $this->unitOfWork->commit();

        $this->em->clear();

        $reloaded = $this->functionAssignmentRepository->findById($id);
        self::assertNotNull($reloaded);
        self::assertFalse($reloaded->isActive());
        self::assertNotNull($reloaded->validTo());
    }

    public function test_same_person_and_function_for_two_organizations_is_allowed(): void
    {
        $person = $this->createPersonAccount('Fn TwoOrgs Person');
        $orgA = $this->createOrganizationAccount('Fn TwoOrgs A');
        $orgB = $this->createOrganizationAccount('Fn TwoOrgs B');
        $personId = (int) $person->id();
        $orgAId = (int) $orgA->id();
        $orgBId = (int) $orgB->id();

        ($this->assignHandler)(new AssignPartyAccountFunctionCommand($personId, $orgAId, 'member', null));
        ($this->assignHandler)(new AssignPartyAccountFunctionCommand($personId, $orgBId, 'member', null));

        self::assertSame(1, $this->countAssignments($personId, $orgAId, 'member'));
        self::assertSame(1, $this->countAssignments($personId, $orgBId, 'member'));
        self::assertTrue(
            $this->functionAssignmentRepository->hasActiveFunction(
                $personId,
                $orgAId,
                PartyFunctionCode::fromString('member'),
            ),
        );
        self::assertTrue(
            $this->functionAssignmentRepository->hasActiveFunction(
                $personId,
                $orgBId,
                PartyFunctionCode::fromString('member'),
            ),
        );
    }

    public function test_handler_rejects_duplicate_active_triplet_before_sql(): void
    {
        $person = $this->createPersonAccount('Fn Duplicate Person');
        $org = $this->createOrganizationAccount('Fn Duplicate Org');
        $personId = (int) $person->id();
        $orgId = (int) $org->id();

        ($this->assignHandler)(new AssignPartyAccountFunctionCommand($personId, $orgId, 'contracting', null));
        self::assertSame(1, $this->countAssignments($personId, $orgId, 'contracting'));

        try {
            ($this->assignHandler)(new AssignPartyAccountFunctionCommand($personId, $orgId, 'contracting', null));
            self::fail('Expected PartyAccountFunctionAlreadyActiveException');
        } catch (PartyAccountFunctionAlreadyActiveException $exception) {
            self::assertSame('party_account_function.already_active', $exception->errorCode());
            self::assertSame($personId, $exception->context()['person_account_id']);
            self::assertSame($orgId, $exception->context()['organization_account_id']);
            self::assertSame('contracting', $exception->context()['function_code']);
        }

        self::assertSame(1, $this->countAssignments($personId, $orgId, 'contracting'));
    }

    public function test_same_triplet_can_be_reassigned_after_revoke(): void
    {
        $person = $this->createPersonAccount('Fn Reassign Person');
        $org = $this->createOrganizationAccount('Fn Reassign Org');
        $personId = (int) $person->id();
        $orgId = (int) $org->id();

        $first = ($this->assignHandler)(new AssignPartyAccountFunctionCommand(
            $personId,
            $orgId,
            'booking_agent',
            null,
        ));
        $firstId = (int) $first->id();

        $first->revoke();
        $this->functionAssignmentRepository->revoke($first);
        $this->unitOfWork->commit();

        $second = ($this->assignHandler)(new AssignPartyAccountFunctionCommand(
            $personId,
            $orgId,
            'booking_agent',
            null,
        ));
        $secondId = (int) $second->id();

        self::assertNotSame($firstId, $secondId);
        self::assertSame(2, $this->countAssignments($personId, $orgId, 'booking_agent'));

        $this->em->clear();

        $closed = $this->functionAssignmentRepository->findById($firstId);
        $active = $this->functionAssignmentRepository->findById($secondId);
        self::assertNotNull($closed);
        self::assertNotNull($active);
        self::assertFalse($closed->isActive());
        self::assertNotNull($closed->validTo());
        self::assertTrue($active->isActive());
        self::assertNull($active->validTo());
        self::assertTrue(
            $this->functionAssignmentRepository->hasActiveFunction(
                $personId,
                $orgId,
                PartyFunctionCode::fromString('booking_agent'),
            ),
        );
    }

    public function test_revoke_handler_persists_valid_to(): void
    {
        $person = $this->createPersonAccount('Fn Revoke Handler Person');
        $org = $this->createOrganizationAccount('Fn Revoke Handler Org');

        $assignment = ($this->assignHandler)(new AssignPartyAccountFunctionCommand(
            (int) $person->id(),
            (int) $org->id(),
            'member',
            null,
        ));
        $id = (int) $assignment->id();

        $revoked = ($this->revokeHandler)(new RevokePartyAccountFunctionCommand($id));
        self::assertFalse($revoked->isActive());
        self::assertNotNull($revoked->validTo());

        $this->em->clear();

        $reloaded = $this->functionAssignmentRepository->findById($id);
        self::assertNotNull($reloaded);
        self::assertFalse($reloaded->isActive());
        self::assertNotNull($reloaded->validTo());
    }

    public function test_revoke_handler_rejects_missing_assignment(): void
    {
        $missingId = 999_999_993;

        try {
            ($this->revokeHandler)(new RevokePartyAccountFunctionCommand($missingId));
            self::fail('Expected PartyAccountFunctionAssignmentNotFoundException');
        } catch (PartyAccountFunctionAssignmentNotFoundException $exception) {
            self::assertSame('party_account_function.assignment_not_found', $exception->errorCode());
            self::assertSame(['assignment_id' => $missingId], $exception->context());
        }
    }

    public function test_revoke_handler_propagates_already_revoked(): void
    {
        $person = $this->createPersonAccount('Fn Already Revoked Person');
        $org = $this->createOrganizationAccount('Fn Already Revoked Org');

        $assignment = ($this->assignHandler)(new AssignPartyAccountFunctionCommand(
            (int) $person->id(),
            (int) $org->id(),
            'manager',
            null,
        ));
        $id = (int) $assignment->id();

        ($this->revokeHandler)(new RevokePartyAccountFunctionCommand($id));

        try {
            ($this->revokeHandler)(new RevokePartyAccountFunctionCommand($id));
            self::fail('Expected InvalidPartyAccountFunctionAssignmentException');
        } catch (InvalidPartyAccountFunctionAssignmentException $exception) {
            self::assertSame('party_account_function.already_revoked', $exception->errorCode());
            self::assertSame((int) $person->id(), $exception->context()['person_account_id']);
            self::assertSame((int) $org->id(), $exception->context()['organization_account_id']);
            self::assertSame('manager', $exception->context()['function_code']);
        }
    }

    private function createPersonAccount(string $label): PartyAccount
    {
        $suffix = bin2hex(random_bytes(4));
        $account = PartyAccount::createPerson(
            $label.' '.$suffix,
            Email::fromString('fn.person.'.$suffix.'@example.com'),
        );
        $this->accountRepository->save($account);
        $this->unitOfWork->commit();
        self::assertNotNull($account->id());

        return $account;
    }

    private function createOrganizationAccount(string $label): PartyAccount
    {
        $suffix = bin2hex(random_bytes(4));
        $account = PartyAccount::createOrganization(
            $label.' '.$suffix,
            Email::fromString('fn.org.'.$suffix.'@example.com'),
        );
        $this->accountRepository->save($account);
        $this->unitOfWork->commit();
        self::assertNotNull($account->id());

        return $account;
    }

    private function countAssignments(int $personAccountId, int $organizationAccountId, string $functionCode): int
    {
        return (int) $this->em->createQueryBuilder()
            ->select('COUNT(a.id)')
            ->from(PartyAccountFunctionAssignment::class, 'a')
            ->where('a.personAccountId = :personAccountId')
            ->andWhere('a.organizationAccountId = :organizationAccountId')
            ->andWhere('a.functionCode = :functionCode')
            ->setParameter('personAccountId', $personAccountId)
            ->setParameter('organizationAccountId', $organizationAccountId)
            ->setParameter('functionCode', $functionCode)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
