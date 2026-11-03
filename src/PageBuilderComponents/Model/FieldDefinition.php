<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Model;

use MageOS\PageBuilderComponents\Field\FieldInterface;

/**
 * A field of a component: its property and its field attribute.
 */
class FieldDefinition
{
    /**
     * @param string $name Property name, also the key in the widget directive and the form.
     * @param FieldInterface $field
     * @param string $propertyType Builtin type name or class name.
     * @param bool $nullable
     * @param string $default Property default as a stored string.
     */
    public function __construct(
        public readonly string $name,
        public readonly FieldInterface $field,
        public readonly string $propertyType,
        public readonly bool $nullable,
        public readonly string $default,
    ) {
    }
}
