<?php

declare(strict_types=1);

namespace Atannex\Routing\Tests\Fixtures\Controllers;

use Atannex\Routing\Attributes\Get;
use Atannex\Routing\Attributes\Where;

/** Sorted first by file name; specificity must still put it last. */
#[Get('/{path}', name: 'content')]
#[Where('path', '@slug-path')]
final class AaCatchAllController
{
    public function __invoke(string $path): string
    {
        return 'content:'.$path;
    }
}
