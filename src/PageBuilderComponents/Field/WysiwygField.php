<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Field;

use Attribute;

/**
 * HTML from the WYSIWYG editor.
 *
 * The block receives it run through the CMS block filter, so `{{media}}`, `{{store}}` and `{{widget}}`
 * directives in it are rendered. Print it without escaping.
 *
 * @api
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class WysiwygField extends AbstractField
{
    /**
     * @inheritdoc
     */
    public function getFormXml(string $name, string $default, string $formNamespace): string
    {
        return $this->buildFieldXml(
            $name,
            'wysiwyg',
            'text',
            $default,
            '<settings><wysiwyg>true</wysiwyg></settings>',
            <<<XML
                <item name="source" xsi:type="string">page</item>
                <item name="wysiwygConfigData" xsi:type="array">
                    <item name="is_pagebuilder_enabled" xsi:type="boolean">false</item>
                    <item name="toggle_button" xsi:type="boolean">false</item>
                </item>
                XML
        );
    }
}
