<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Field;

use Attribute;

/**
 * Multi-line plain text.
 *
 * @api
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class TextAreaField extends AbstractField
{
    /**
     * @inheritdoc
     */
    public function getFormXml(string $name, string $default, string $formNamespace): string
    {
        return $this->buildFieldXml($name, 'textarea', 'text', $default);
    }
}
