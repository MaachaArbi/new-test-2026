<?php

declare(strict_types=1);

namespace App\Modules\Party\Domain\ValueObject;

enum PartyAccountNature: string
{
    case Person = 'person';
    case Organization = 'organization';
}
