<?php

declare(strict_types=1);

namespace Atannex\Routing\Attributes;

use Attribute;

/**
 * Leaves a class out of discovery, even if it carries route attributes
 * (or inherits them).
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class NoRoutes {}
