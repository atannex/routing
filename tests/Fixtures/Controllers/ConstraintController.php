<?php

declare(strict_types=1);

namespace Atannex\Routing\Tests\Fixtures\Controllers;

use Atannex\Routing\Attributes\Get;
use Atannex\Routing\Attributes\WhereIn;
use Atannex\Routing\Tests\Fixtures\Status;

final class ConstraintController
{
    #[Get('/status/{status}', name: 'status')]
    #[WhereIn('status', enum: Status::class)]
    public function status(string $status): string
    {
        return $status;
    }

    #[Get('/colour/{colour}', name: 'colour')]
    #[WhereIn('colour', using: [ConstraintController::class, 'colours'])]
    public function colour(string $colour): string
    {
        return $colour;
    }

    #[Get('/size/{size}', name: 'size')]
    #[WhereIn('size', ['s', 'm', 'x.l'])]
    public function size(string $size): string
    {
        return $size;
    }

    #[Get('/teams/{team}', name: 'teams.show')]
    public function team(string $team): string
    {
        return $team;
    }

    /** @return list<string> */
    public static function colours(): array
    {
        return ['red', 'green'];
    }
}
