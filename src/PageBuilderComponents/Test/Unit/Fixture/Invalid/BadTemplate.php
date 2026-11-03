<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Test\Unit\Fixture\Invalid;

use MageOS\PageBuilderComponents\Attribute\PageBuilderComponent;
use MageOS\PageBuilderComponents\Block\AbstractComponent;

/**
 * A template path outside the module notation.
 */
#[PageBuilderComponent(name: 'test_invalid', label: 'Invalid', template: '../../../etc/passwd')]
class BadTemplate extends AbstractComponent
{
}
