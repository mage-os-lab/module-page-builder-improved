<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Exception;

use LogicException;

/**
 * A registered component class is not a valid component. The message says what to fix.
 *
 * @api
 */
class InvalidComponentException extends LogicException
{
}
