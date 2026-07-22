<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Party\Domain\Entity;

use App\Modules\Party\Domain\Entity\PartyAccount;
use App\Modules\Party\Domain\Exception\InvalidPartyAccountStateException;
use App\Shared\Domain\ValueObject\Email;
use App\Modules\Party\Domain\ValueObject\PartyAccountNature;
use App\Shared\Domain\ValueObject\PublicId;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;

final class PartyAccountTest extends TestCase
{
    #[Test]
    public function create_person_ok(): void
    {
        $account = PartyAccount::createPerson(
            'Alice Martin',
            Email::fromString('alice@example.com'),
        );

        self::assertSame(PartyAccountNature::Person, $account->nature());
        self::assertSame('Alice Martin', $account->displayName());
        self::assertNotNull($account->email());
        self::assertTrue($account->email()->equals(Email::fromString('alice@example.com')));
        self::assertNull($account->parentAccountId());
        self::assertFalse($account->isDisabled());
        self::assertNull($account->id());
        self::assertInstanceOf(PublicId::class, $account->publicId());
        self::assertTrue(Uuid::isValid($account->publicId()->toString()));
        self::assertSame(4, Uuid::fromString($account->publicId()->toString())->getVersion());
    }

    #[Test]
    public function create_organization_ok(): void
    {
        $account = PartyAccount::createOrganization(
            'Agence Demo',
            Email::fromString('agence@example.com'),
            parentAccountId: 42,
        );

        self::assertSame(PartyAccountNature::Organization, $account->nature());
        self::assertSame('Agence Demo', $account->displayName());
        self::assertSame(42, $account->parentAccountId());
        self::assertInstanceOf(PublicId::class, $account->publicId());
    }

    #[Test]
    public function create_person_with_parent_account_id_is_rejected(): void
    {
        try {
            PartyAccount::createPerson('Alice', null, parentAccountId: 1);
            self::fail('Expected InvalidPartyAccountStateException');
        } catch (InvalidPartyAccountStateException $exception) {
            self::assertSame('party_account.parent_account_not_allowed_for_person', $exception->errorCode());
            self::assertSame(
                [
                    'attempted_parent_id' => 1,
                    'display_name' => 'Alice',
                ],
                $exception->context(),
            );
        }
    }

    #[Test]
    public function disable_changes_state(): void
    {
        $account = PartyAccount::createPerson('Alice');
        self::assertFalse($account->isDisabled());

        $account->disable();

        self::assertTrue($account->isDisabled());
        self::assertFalse($account->isDeleted());
        self::assertNull($account->deletedAt());

        $account->enable();
        self::assertFalse($account->isDisabled());
    }

    #[Test]
    public function delete_sets_soft_delete_without_disabling(): void
    {
        $account = PartyAccount::createPerson('Alice');
        self::assertFalse($account->isDeleted());
        self::assertNull($account->deletedAt());

        $account->delete();

        self::assertTrue($account->isDeleted());
        self::assertNotNull($account->deletedAt());
        self::assertFalse($account->isDisabled());

        $firstDeletedAt = $account->deletedAt();
        $account->delete();
        self::assertSame($firstDeletedAt, $account->deletedAt());
    }
}
