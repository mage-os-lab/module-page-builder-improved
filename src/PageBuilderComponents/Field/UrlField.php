<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Field;

use Attribute;
use MageOS\PageBuilderComponents\Model\Value\Link;

/**
 * Link to a URL, a product, a category or a CMS page, with an "Open in new tab" setting.
 *
 * Type the property `?Link` to receive a {@see Link} (or null when no link is set), or `string` to receive
 * the resolved URL only.
 *
 * @api
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class UrlField extends AbstractField
{
    /**
     * @inheritdoc
     */
    public function getValueType(): string
    {
        return FieldInterface::VALUE_LINK;
    }

    /**
     * @inheritdoc
     */
    public function getPropertyTypes(): array
    {
        return [Link::class, 'string'];
    }

    /**
     * @inheritdoc
     */
    public function getFormXml(string $name, string $default, string $formNamespace): string
    {
        $name = $this->escape($name);
        $label = $this->escape($this->getLabel());

        return <<<XML
            <urlInput name="$name" sortOrder="{$this->getSortOrder()}">
                <settings>
                    {$this->buildValidationXml()}
                    <dataType>text</dataType>
                    <label translate="true">$label</label>
                    <dataScope>$name</dataScope>
                    <urlTypes class="Magento\Ui\Model\UrlInput\LinksConfigProvider"/>
                </settings>
            </urlInput>
            XML;
    }
}
