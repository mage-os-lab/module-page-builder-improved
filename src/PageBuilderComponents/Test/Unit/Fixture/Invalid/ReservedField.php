<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Test\Unit\Fixture\Invalid;

use MageOS\PageBuilderComponents\Attribute\PageBuilderComponent;
use MageOS\PageBuilderComponents\Block\AbstractComponent;
use MageOS\PageBuilderComponents\Field\InputField;

/**
 * A field named like the directive's block class parameter.
 */
#[PageBuilderComponent(name: 'test_invalid', label: 'Invalid', template: 'Vendor_Module::invalid.phtml')]
class ReservedField extends AbstractComponent
{
    #[InputField(label: 'Type')]
    /**
     * @var string
     */
    public string $type = '';
}
