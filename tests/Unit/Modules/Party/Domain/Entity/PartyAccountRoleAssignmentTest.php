<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Party\Domain\Entity;

use App\Modules\Party\Domain\Entity\PartyAccountRoleAssignment;
use App\Modules\Party\Domain\Exception\InvalidPartyAccountRoleAssignmentException;
use App\Modules\Party\Domain\ValueObject\PartyRoleCode;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PartyAccountRoleAssignmentTest extends TestCase
{
    #[Test]
    public function assign_creates_an_active_assignment(): void
    {
        $roleCode = PartyRoleCode::fromString('client');
        $assignment = PartyAccountRoleAssignment::assign(
            accountId: 42,
            roleCode: $roleCode,
            createdBy: 7,
        );

        self::assertNull($assignment->id());
        self::assertSame(42, $assignment->accountId());
        self::assertSame('client', $assignment->roleCode()->toString());
        self::assertSame(7, $assignment->createdBy());
        self::assertTrue($assignment->isActive());
        self::assertNull($assignment->validTo());
        self::assertInstanceOf(\DateTimeImmutable::class, $assignment->validFrom());
    }

    #[Test]
    public function revoke_closes_the_assignment_via_valid_to(): void
    {
        $assignment = PartyAccountRoleAssignment::assign(
            42,
            PartyRoleCode::fromString('fournisseur'),
            null,
        );
        self::assertTrue($assignment->isActive());

        $assignment->revoke();

        self::assertFalse($assignment->isActive());
        self::assertNotNull($assignment->validTo());
        self::assertGreaterThanOrEqual(
            $assignment->validFrom()->getTimestamp(),
            $assignment->validTo()->getTimestamp(),
        );
    }

    #[Test]
    public function revoke_on_already_closed_assignment_is_rejected(): void
    {
        $assignment = PartyAccountRoleAssignment::assign(
            42,
            PartyRoleCode::fromString('channel'),
            1,
        );
        $assignment->revoke();

        try {
            $assignment->revoke();
            self::fail('Expected InvalidPartyAccountRoleAssignmentException');
        } catch (InvalidPartyAccountRoleAssignmentException $exception) {
            self::assertSame('party_account_role.already_revoked', $exception->errorCode());
            self::assertSame(42, $exception->context()['account_id']);
            self::assertSame('channel', $exception->context()['role_code']);
        }
    }
}
