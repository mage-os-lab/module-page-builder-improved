<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Model\Config;

use MageOS\PageBuilderComponents\Field\ConditionsField;
use MageOS\PageBuilderComponents\Model\ComponentDefinition;

/**
 * Builds a component's edit form, as the UI component XML core reads from `ui_component/<form>.xml`.
 *
 * It extends `pagebuilder_base_form`, so the form has core's Advanced fieldset (alignment, border, CSS
 * classes, margins and padding) and its Save and Close buttons.
 */
class FormXmlGenerator
{
    /**
     * @param Xml $xml
     */
    public function __construct(
        private readonly Xml $xml,
    ) {
    }

    /**
     * Form XML of a component.
     *
     * @param ComponentDefinition $definition
     * @return string
     */
    public function generate(ComponentDefinition $definition): string
    {
        $formName = $definition->getFormName();
        $fields = '';
        $conditionsFields = '';
        foreach ($definition->fields as $name => $fieldDefinition) {
            $fields .= $fieldDefinition->field->getFormXml($name, $fieldDefinition->default, $formName) . "\n";
            if ($fieldDefinition->field instanceof ConditionsField) {
                $escapedName = $this->xml->escape($name);
                $conditionsFields .= "<item name=\"$escapedName\" xsi:type=\"string\">$escapedName</item>\n";
            }
        }
        $dataSource = $this->xml->escape($formName . '_data_source');
        $formName = $this->xml->escape($formName);
        $label = $this->xml->escape($definition->label);
        $provider = 'MageOS_PageBuilderComponents/js/form/provider';
        $fieldsetComponent = 'Magento_PageBuilder/js/form/element/dependent-fieldset';
        $selectComponent = 'Magento_PageBuilder/js/form/element/dependent-visual-select';

        return <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <form xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                  xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_Ui:etc/ui_configuration.xsd"
                  extends="pagebuilder_base_form">
                <argument name="data" xsi:type="array">
                    <item name="js_config" xsi:type="array">
                        <item name="provider" xsi:type="string">$formName.$dataSource</item>
                    </item>
                    <item name="label" xsi:type="string" translate="true">$label</item>
                </argument>
                <settings>
                    <namespace>$formName</namespace>
                    <deps>
                        <dep>$formName.$dataSource</dep>
                    </deps>
                </settings>
                <dataSource name="$dataSource">
                    <argument name="data" xsi:type="array">
                        <item name="js_config" xsi:type="array">
                            <item name="component" xsi:type="string">$provider</item>
                        </item>
                    </argument>
                    <dataProvider name="$dataSource" class="Magento\PageBuilder\Model\ContentType\DataProvider">
                        <argument name="data" xsi:type="array">
                            <item name="config" xsi:type="array">
                                <item name="conditionsFields" xsi:type="array">
                                    $conditionsFields
                                </item>
                            </item>
                        </argument>
                        <settings>
                            <requestFieldName/>
                            <primaryFieldName/>
                        </settings>
                    </dataProvider>
                </dataSource>
                <fieldset name="appearance_fieldset" sortOrder="10" component="$fieldsetComponent">
                    <settings>
                        <label translate="true">Appearance</label>
                        <additionalClasses>
                            <class name="admin__fieldset-visual-select-large">true</class>
                        </additionalClasses>
                        <collapsible>false</collapsible>
                        <opened>true</opened>
                        <imports>
                            <link name="hideFieldset">\${\$.name}.appearance:options</link>
                            <link name="hideLabel">\${\$.name}.appearance:options</link>
                        </imports>
                    </settings>
                    <field name="appearance" formElement="select" sortOrder="10" component="$selectComponent">
                        <argument name="data" xsi:type="array">
                            <item name="config" xsi:type="array">
                                <item name="default" xsi:type="string">default</item>
                            </item>
                        </argument>
                        <settings>
                            <additionalClasses>
                                <class name="admin__field-wide">true</class>
                                <class name="admin__field-visual-select-container">true</class>
                            </additionalClasses>
                            <dataType>text</dataType>
                            <validation>
                                <rule name="required-entry" xsi:type="boolean">true</rule>
                            </validation>
                            <elementTmpl>Magento_PageBuilder/form/element/visual-select</elementTmpl>
                        </settings>
                        <formElements>
                            <select>
                                <settings>
                                    <options class="MageOS\PageBuilderComponents\Model\Config\AppearanceSource"/>
                                </settings>
                            </select>
                        </formElements>
                    </field>
                </fieldset>
                <fieldset name="general" sortOrder="20">
                    <settings>
                        <label translate="true">Content</label>
                        <collapsible>true</collapsible>
                        <opened>true</opened>
                    </settings>
                    $fields
                </fieldset>
            </form>
            XML;
    }
}
