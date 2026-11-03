<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Field;

/**
 * A field of a Page Builder component, declared as an attribute on a public property of the block.
 *
 * @api
 */
interface FieldInterface
{
    /**
     * The form value is a string and is stored as it is.
     */
    public const VALUE_TEXT = 'text';

    /**
     * The form value is an image uploader list; the path under the media directory is stored.
     */
    public const VALUE_IMAGE = 'image';

    /**
     * The form value is a URL input object; it is stored as JSON.
     */
    public const VALUE_LINK = 'link';

    /**
     * Label of the field in the component's form.
     *
     * @return string
     */
    public function getLabel(): string;

    /**
     * Position of the field in the component's form.
     *
     * @return int
     */
    public function getSortOrder(): int;

    /**
     * How the stage converts the form value to and from the string stored in the widget directive.
     *
     * @return string One of the VALUE_* constants.
     */
    public function getValueType(): string;

    /**
     * Property types the field can be hydrated into.
     *
     * @return string[] Builtin type names (`string`, `int`, `bool`) or class names.
     */
    public function getPropertyTypes(): array;

    /**
     * UI component XML of the field, for the component's form.
     *
     * @param string $name Property name, also the field's data scope.
     * @param string $default Property default value as a stored string.
     * @param string $formNamespace Name of the form the field is rendered in.
     * @return string
     */
    public function getFormXml(string $name, string $default, string $formNamespace): string;
}
