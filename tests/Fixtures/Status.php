<?php

declare(strict_types=1);

namespace Atannex\Routing\Tests\Fixtures;

enum Status: string
{
    case Draft = 'draft';
    case Published = 'published';
}
