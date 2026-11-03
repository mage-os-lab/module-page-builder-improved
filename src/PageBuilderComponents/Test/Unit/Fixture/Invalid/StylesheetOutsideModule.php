<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Test\Unit\Fixture\Invalid;

use MageOS\PageBuilderComponents\Attribute\PageBuilderComponent;
use MageOS\PageBuilderComponents\Block\AbstractComponent;

/**
 * A stylesheet path that climbs out of the module's web directory.
 */
#[PageBuilderComponent(
    name: 'test_invalid',
    label: 'Invalid',
    template: 'Vendor_Module::invalid.phtml',
    stylesheets: ['Vendor_Module::../../etc/x.css'],
)]
class StylesheetOutsideModule extends AbstractComponent
{
}
