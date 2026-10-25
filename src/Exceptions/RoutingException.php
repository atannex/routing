<?php

declare(strict_types=1);

namespace Atannex\Routing\Exceptions;

use LogicException;

/** A route declaration that cannot be registered (bad target, unknown pattern...). */
class RoutingException extends LogicException {}
