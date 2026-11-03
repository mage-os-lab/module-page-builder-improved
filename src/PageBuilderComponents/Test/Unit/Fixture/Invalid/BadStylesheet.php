<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Test\Unit\Fixture\Invalid;

use MageOS\PageBuilderComponents\Attribute\PageBuilderComponent;
use MageOS\PageBuilderComponents\Block\AbstractComponent;

/**
 * A stylesheet that is a URL, not a view asset id.
 */
#[PageBuilderComponent(
    name: 'test_invalid',
    label: 'Invalid',
    template: 'Vendor_Module::invalid.phtml',
    stylesheets: ['Vendor_Module::css/fine.css', 'https://evil.test/x.css'],
)]
class BadStylesheet extends AbstractComponent
{
}
