<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Test\Unit\Fixture\Invalid;

use MageOS\PageBuilderComponents\Attribute\PageBuilderComponent;
use MageOS\PageBuilderComponents\Block\AbstractComponent;

/**
 * An icon that is not CSS class names: it would break out of the panel's class attribute.
 */
#[PageBuilderComponent(
    name: 'test_invalid',
    label: 'Invalid',
    template: 'Vendor_Module::invalid.phtml',
    icon: 'icon"><img src=x onerror=alert(1)>',
)]
class BadIcon extends AbstractComponent
{
}
