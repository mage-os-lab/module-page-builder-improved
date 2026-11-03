<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Test\Unit\Fixture\Invalid;

use MageOS\PageBuilderComponents\Attribute\PageBuilderComponent;
use MageOS\PageBuilderComponents\Block\AbstractComponent;

/**
 * A name with characters a content type name cannot have.
 */
#[PageBuilderComponent(name: 'Test-Invalid', label: 'Invalid', template: 'Vendor_Module::invalid.phtml')]
class BadName extends AbstractComponent
{
}
