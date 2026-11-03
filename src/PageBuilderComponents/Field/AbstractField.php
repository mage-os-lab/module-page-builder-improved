<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Field;

use MageOS\PageBuilderComponents\Model\Config\Xml;

/**
 * Label, sort order and the required rule, shared by the fields, and the XML they have in common.
 */
abstract class AbstractField implements FieldInterface
{
    /**
     * @param string $label
     * @param int $sortOrder
     * @param bool $required
     */
    public function __construct(
        private readonly string $label,
        private readonly int $sortOrder = 10,
        private readonly bool $required = false,
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * @inheritdoc
     */
    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    /**
     * Whether the form refuses to save without a value.
     *
     * @return bool
     */
    public function isRequired(): bool
    {
        return $this->required;
    }

    /**
     * @inheritdoc
     */
    public function getValueType(): string
    {
        return FieldInterface::VALUE_TEXT;
    }

    /**
     * @inheritdoc
     */
    public function getPropertyTypes(): array
    {
        return ['string'];
    }

    /**
     * Build a `<field>` element.
     *
     * Every value is escaped here; `$formElementsXml` and `$configXml` are XML, and their values must be
     * escaped by the caller.
     *
     * @param string $name
     * @param string $formElement
     * @param string $dataType
     * @param string $default
     * @param string $formElementsXml Contents of `<formElements>`, if any.
     * @param string $configXml Extra `<item>`s of the `config` data argument.
     * @return string
     */
    protected function buildFieldXml(
        string $name,
        string $formElement,
        string $dataType,
        string $default,
        string $formElementsXml = '',
        string $configXml = ''
    ): string {
        $name = $this->escape($name);
        $label = $this->escape($this->label);
        $default = $this->escape($default);
        $formElement = $this->escape($formElement);
        $dataType = $this->escape($dataType);
        $formElementsXml = $formElementsXml !== ''
            ? "<formElements><$formElement>$formElementsXml</$formElement></formElements>"
            : '';

        return <<<XML
            <field name="$name" formElement="$formElement" sortOrder="$this->sortOrder">
                <argument name="data" xsi:type="array">
                    <item name="config" xsi:type="array">
                        <item name="default" xsi:type="string">$default</item>
                        $configXml
                    </item>
                </argument>
                <settings>
                    {$this->buildValidationXml()}
                    <dataType>$dataType</dataType>
                    <label translate="true">$label</label>
                    <dataScope>$name</dataScope>
                </settings>
                $formElementsXml
            </field>
            XML;
    }

    /**
     * Build the `<validation>` setting.
     *
     * @return string
     */
    protected function buildValidationXml(): string
    {
        return $this->required
            ? '<validation><rule name="required-entry" xsi:type="boolean">true</rule></validation>'
            : '';
    }

    /**
     * Escape a value for an XML attribute or text node.
     *
     * @param string $value
     * @return string
     */
    protected function escape(string $value): string
    {
        // Attributes are built by reflection, not by the object manager, so nothing can be injected.
        return (new Xml())->escape($value);
    }
}
