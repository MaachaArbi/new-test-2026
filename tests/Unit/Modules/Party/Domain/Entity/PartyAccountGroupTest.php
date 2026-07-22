<?php

declare(strict_types=1);

namespace App\Tests\Unit\Modules\Party\Domain\Entity;

use App\Modules\Party\Domain\Entity\PartyAccountGroup;
use App\Modules\Party\Domain\ValueObject\PartyAccountGroupTypeCode;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PartyAccountGroupTest extends TestCase
{
    #[Test]
    public function create_builds_a_group_with_generated_public_id(): void
    {
        $type = PartyAccountGroupTypeCode::fromString('commercial');
        $group = PartyAccountGroup::create($type, 'Top Partners');

        self::assertNull($group->id());
        self::assertSame('commercial', $group->groupTypeCode()->toString());
        self::assertSame('Top Partners', $group->name());
        self::assertNotSame('', $group->publicId()->toString());
    }

    #[Test]
    public function rename_changes_the_name(): void
    {
        $group = PartyAccountGroup::create(
            PartyAccountGroupTypeCode::fromString('commercial'),
            'Amicale 1',
        );

        $group->rename('Amicale 1 bis');

        self::assertSame('Amicale 1 bis', $group->name());
    }
}
