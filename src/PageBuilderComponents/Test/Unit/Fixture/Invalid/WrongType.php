<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Test\Unit\Fixture\Invalid;

use MageOS\PageBuilderComponents\Attribute\PageBuilderComponent;
use MageOS\PageBuilderComponents\Block\AbstractComponent;
use MageOS\PageBuilderComponents\Field\ToggleField;

/**
 * A toggle on a string property.
 */
#[PageBuilderComponent(name: 'test_invalid', label: 'Invalid', template: 'Vendor_Module::invalid.phtml')]
class WrongType extends AbstractComponent
{
    #[ToggleField(label: 'Enabled')]
    /**
     * @var string
     */
    public string $enabled = '';
}
