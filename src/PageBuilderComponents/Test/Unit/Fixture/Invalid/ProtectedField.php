<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Test\Unit\Fixture\Invalid;

use MageOS\PageBuilderComponents\Attribute\PageBuilderComponent;
use MageOS\PageBuilderComponents\Block\AbstractComponent;
use MageOS\PageBuilderComponents\Field\InputField;

/**
 * A field on a property the hydrator cannot set.
 */
#[PageBuilderComponent(name: 'test_invalid', label: 'Invalid', template: 'Vendor_Module::invalid.phtml')]
class ProtectedField extends AbstractComponent
{
    #[InputField(label: 'Title')]
    /**
     * @var string
     */
    protected string $title = '';
}
