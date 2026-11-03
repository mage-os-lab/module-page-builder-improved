<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Test\Unit\Fixture\Invalid;

use MageOS\PageBuilderComponents\Attribute\PageBuilderComponent;
use MageOS\PageBuilderComponents\Block\AbstractComponent;
use MageOS\PageBuilderComponents\Field\SelectField;

/**
 * A select whose default is not among its options.
 */
#[PageBuilderComponent(name: 'test_invalid', label: 'Invalid', template: 'Vendor_Module::invalid.phtml')]
class BadSelectDefault extends AbstractComponent
{
    #[SelectField(label: 'Layout', options: ['left' => 'Left', 'right' => 'Right'])]
    /**
     * @var string
     */
    public string $layout = 'centre';
}
