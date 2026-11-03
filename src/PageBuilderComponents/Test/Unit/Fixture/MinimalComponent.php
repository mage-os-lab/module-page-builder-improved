<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Test\Unit\Fixture;

use MageOS\PageBuilderComponents\Attribute\PageBuilderComponent;
use MageOS\PageBuilderComponents\Block\AbstractComponent;
use MageOS\PageBuilderComponents\Field\InputField;

/**
 * Attribute defaults and one field.
 */
#[PageBuilderComponent(name: 'test_minimal', label: 'Minimal', template: 'Vendor_Module::minimal.phtml')]
class MinimalComponent extends AbstractComponent
{
    #[InputField(label: 'Heading')]
    /**
     * @var string
     */
    public string $heading = '';
}
