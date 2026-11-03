<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Model\Config;

use MageOS\PageBuilderComponents\Model\ComponentDefinition;

/**
 * Builds a component's Page Builder content type, as the XML core reads from `pagebuilder/content_type/*.xml`.
 *
 * The XML goes through core's reader like any module's file: it is validated, merged, converted and cached the
 * same way, so a module can still extend or override the content type with an XML file of its own.
 */
class ContentTypeXmlGenerator
{
    private const MODULE = 'MageOS_PageBuilderComponents';

    /**
     * @param Xml $xml
     */
    public function __construct(
        private readonly Xml $xml,
    ) {
    }

    /**
     * Content type XML of a component.
     *
     * @param ComponentDefinition $definition
     * @return string
     */
    public function generate(ComponentDefinition $definition): string
    {
        $module = self::MODULE;
        $storeIdConverter = 'Magento_PageBuilder/js/converter/attribute/preview/store-id';
        $name = $this->escape($definition->name);
        $label = $this->escape($definition->label);
        $icon = $this->escape($definition->icon);
        $menuSection = $this->escape($definition->menuSection);
        $form = $this->escape($definition->getFormName());
        $blockClass = $this->escape($definition->blockClass);
        $fields = '';
        foreach ($definition->fields as $fieldName => $fieldDefinition) {
            $fields .= \sprintf(
                '<item name="%s" xsi:type="string">%s</item>',
                $this->escape($fieldName),
                $this->escape($fieldDefinition->field->getValueType())
            );
        }
        $fields = $fields !== '' ? "<item name=\"fields\" xsi:type=\"array\">$fields</item>" : '';

        return <<<XML
            <?xml version="1.0"?>
            <config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                    xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_PageBuilder:etc/content_type.xsd">
                <type name="$name"
                      label="$label"
                      component="Magento_PageBuilder/js/content-type"
                      preview_component="$module/js/content-type/preview"
                      master_component="Magento_PageBuilder/js/content-type/master"
                      form="$form"
                      menu_section="$menuSection"
                      icon="$icon"
                      sortOrder="{$definition->sortOrder}"
                      translate="label">
                    <children default_policy="deny"/>
                    <appearances>
                        <appearance default="true"
                                    name="default"
                                    preview_template="$module/content-type/component/preview"
                                    master_template="$module/content-type/component/master"
                                    reader="Magento_PageBuilder/js/master-format/read/configurable">
                            <elements>
                                <element name="main">
                                    <style name="display" source="display"
                                           converter="Magento_PageBuilder/js/converter/style/display"
                                           preview_converter="Magento_PageBuilder/js/converter/style/preview/display"/>
                                    <style name="margins" storage_key="margins_and_padding"
                                           reader="Magento_PageBuilder/js/property/margins"
                                           converter="Magento_PageBuilder/js/converter/style/margins"/>
                                    <style name="padding" storage_key="margins_and_padding"
                                           reader="Magento_PageBuilder/js/property/paddings"
                                           converter="Magento_PageBuilder/js/converter/style/paddings"/>
                                    <attribute name="name" source="data-content-type"/>
                                    <attribute name="appearance" source="data-appearance"/>
                                    <html name="html" preview_converter="$storeIdConverter"/>
                                    <css name="css_classes"/>
                                </element>
                            </elements>
                            <converters>
                                <converter name="widget_directive"
                                           component="$module/js/mass-converter/widget-directive">
                                    <config>
                                        <item name="html_variable" value="html"/>
                                        <item name="content_type" value="$name"/>
                                    </config>
                                </converter>
                            </converters>
                        </appearance>
                    </appearances>
                    <additional_data>
                        <item name="mageos_component" xsi:type="array">
                            <item name="name" xsi:type="string">$name</item>
                            <item name="block_class" xsi:type="string">$blockClass</item>
                            $fields
                        </item>
                    </additional_data>
                </type>
            </config>
            XML;
    }

    /**
     * Escape a value for an XML attribute or text node.
     *
     * @param string $value
     * @return string
     */
    private function escape(string $value): string
    {
        return $this->xml->escape($value);
    }
}
