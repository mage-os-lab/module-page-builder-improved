<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Test\Unit\Fixture\Invalid;

use MageOS\PageBuilderComponents\Attribute\PageBuilderComponent;
use MageOS\PageBuilderComponents\Block\AbstractComponent;
use MageOS\PageBuilderComponents\Field\InputField;
use MageOS\PageBuilderComponents\Field\TextAreaField;

/**
 * Two field attributes on one property.
 */
#[PageBuilderComponent(name: 'test_invalid', label: 'Invalid', template: 'Vendor_Module::invalid.phtml')]
class TwoFields extends AbstractComponent
{
    #[InputField(label: 'Title')]
    #[TextAreaField(label: 'Title')]
    /**
     * @var string
     */
    public string $title = '';
}
