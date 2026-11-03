<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Test\Unit\Fixture\Invalid;

use MageOS\PageBuilderComponents\Attribute\PageBuilderComponent;
use MageOS\PageBuilderComponents\Block\AbstractComponent;

/**
 * A sort order Page Builder's schema refuses (xs:byte).
 */
#[PageBuilderComponent(
    name: 'test_invalid',
    label: 'Invalid',
    template: 'Vendor_Module::invalid.phtml',
    sortOrder: 200,
)]
class BadSortOrder extends AbstractComponent
{
}
