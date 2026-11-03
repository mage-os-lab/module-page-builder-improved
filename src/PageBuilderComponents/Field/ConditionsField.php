<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Field;

use Attribute;
use Magento\PageBuilder\Block\Adminhtml\Form\Element\ProductConditions;

/**
 * Catalog product conditions, as in core's Products content type.
 *
 * The block receives the conditions as a JSON string, the form `Magento\CatalogWidget\Block\Product\ProductsList`
 * takes as `conditions_encoded` once decoded.
 *
 * @api
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class ConditionsField extends AbstractField
{
    /**
     * @inheritdoc
     */
    public function getFormXml(string $name, string $default, string $formNamespace): string
    {
        $name = $this->escape($name);
        $label = $this->escape($this->getLabel());
        $formNamespace = $this->escape($formNamespace);
        $template = 'Magento_PageBuilder/form/element/widget-conditions';
        $component = 'Magento_PageBuilder/js/form/element/html';
        $block = ProductConditions::class;

        return <<<XML
            <htmlContent name="$name" sortOrder="{$this->getSortOrder()}" template="$template" component="$component">
                <settings>
                    <visible>true</visible>
                    <additionalClasses>
                        <class name="admin__field">true</class>
                    </additionalClasses>
                </settings>
                <block name="$name" class="$block">
                    <arguments>
                        <argument name="formNamespace" xsi:type="string">$formNamespace</argument>
                        <argument name="attribute" xsi:type="string">$name</argument>
                        <argument name="label" xsi:type="string" translate="true">$label</argument>
                    </arguments>
                </block>
            </htmlContent>
            XML;
    }
}
