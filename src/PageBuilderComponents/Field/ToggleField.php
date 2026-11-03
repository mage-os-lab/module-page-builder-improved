<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Field;

use Attribute;

/**
 * On/off switch. Type the property `bool`; its default value is the switch's default.
 *
 * @api
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class ToggleField extends AbstractField
{
    /**
     * @inheritdoc
     */
    public function getPropertyTypes(): array
    {
        return ['bool'];
    }

    /**
     * @inheritdoc
     */
    public function getFormXml(string $name, string $default, string $formNamespace): string
    {
        return $this->buildFieldXml(
            $name,
            'checkbox',
            'boolean',
            $default,
            <<<XML
                <settings>
                    <valueMap>
                        <map name="false" xsi:type="string">0</map>
                        <map name="true" xsi:type="string">1</map>
                    </valueMap>
                    <prefer>toggle</prefer>
                </settings>
                XML
        );
    }
}
