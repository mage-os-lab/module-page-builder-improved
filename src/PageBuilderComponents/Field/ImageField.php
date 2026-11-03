<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Field;

use Attribute;

/**
 * Image uploaded to, or picked from, the media gallery.
 *
 * The path under the media directory is stored, so content survives a change of domain or media URL. The block
 * receives the image's full URL for the current store, or an empty string.
 *
 * The extensions are those core's image upload controller (`pagebuilder/contenttype/image_upload`) accepts:
 * jpg, jpeg, png and gif. Never SVG, which can carry scripts.
 *
 * @api
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
class ImageField extends AbstractField
{
    /**
     * @inheritdoc
     */
    public function getValueType(): string
    {
        return FieldInterface::VALUE_IMAGE;
    }

    /**
     * @inheritdoc
     */
    public function getFormXml(string $name, string $default, string $formNamespace): string
    {
        $name = $this->escape($name);
        $label = $this->escape($this->getLabel());

        return <<<XML
            <field name="$name" formElement="imageUploader" sortOrder="{$this->getSortOrder()}">
                <settings>
                    {$this->buildValidationXml()}
                    <dataType>text</dataType>
                    <label translate="true">$label</label>
                    <dataScope>$name</dataScope>
                </settings>
                <formElements>
                    <imageUploader>
                        <settings>
                            <allowedExtensions>jpg jpeg png gif</allowedExtensions>
                            <maxFileSize>4194304</maxFileSize>
                            <uploaderConfig>
                                <param xsi:type="string" name="url">pagebuilder/contenttype/image_upload</param>
                            </uploaderConfig>
                            <previewTmpl>Magento_PageBuilder/form/element/uploader/preview</previewTmpl>
                        </settings>
                    </imageUploader>
                </formElements>
            </field>
            XML;
    }
}
