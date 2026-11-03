<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Field;

use Attribute;

/**
 * Single-line text.
 *
 * @api
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class InputField extends AbstractField
{
    /**
     * @inheritdoc
     */
    public function getPropertyTypes(): array
    {
        return ['string', 'int'];
    }

    /**
     * @inheritdoc
     */
    public function getFormXml(string $name, string $default, string $formNamespace): string
    {
        return $this->buildFieldXml($name, 'input', 'text', $default);
    }
}
