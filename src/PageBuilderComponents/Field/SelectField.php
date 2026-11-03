<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Field;

use Attribute;

/**
 * Choice from a fixed list, such as a layout variant or a colour scheme.
 *
 * The property's default value is the preselected option. The block receives one of the option values; any
 * other stored value becomes the property's default.
 *
 * @api
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class SelectField extends AbstractField
{
    /**
     * @param string $label
     * @param string[] $options Option value => label.
     * @param int $sortOrder
     * @param bool $required
     */
    public function __construct(
        string $label,
        private readonly array $options,
        int $sortOrder = 10,
        bool $required = false,
    ) {
        parent::__construct($label, $sortOrder, $required);
    }

    /**
     * Option value => label.
     *
     * @return array<string|int, string>
     */
    public function getOptions(): array
    {
        return $this->options;
    }

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
        $options = '';
        $index = 0;
        foreach ($this->options as $value => $label) {
            $value = $this->escape((string) $value);
            $label = $this->escape($label);
            $options .= <<<XML
                <option name="$index" xsi:type="array">
                    <item name="value" xsi:type="string">$value</item>
                    <item name="label" xsi:type="string" translate="true">$label</item>
                </option>
                XML;
            $index++;
        }

        return $this->buildFieldXml(
            $name,
            'select',
            'text',
            $default,
            "<settings><options>$options</options></settings>"
        );
    }
}
