<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Test\Unit\Fixture\Invalid;

use MageOS\PageBuilderComponents\Attribute\PageBuilderComponent;

/**
 * Does not extend AbstractComponent.
 */
#[PageBuilderComponent(name: 'test_invalid', label: 'Invalid', template: 'Vendor_Module::invalid.phtml')]
class NotABlock
{
}
