<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Model;

/**
 * A component as declared on its block class.
 *
 * @api
 */
class ComponentDefinition
{
    public const FORM_PREFIX = 'mageos_pbc_';
    public const FORM_SUFFIX = '_form';

    /**
     * @param string $name
     * @param string $label
     * @param string $template
     * @param string $menuSection
     * @param string $icon
     * @param int $sortOrder
     * @param string $blockClass
     * @param FieldDefinition[] $fields Keyed by name, in sort order.
     * @param string[] $stylesheets View asset ids of the stylesheets the stage preview loads.
     * @phpstan-param class-string<\MageOS\PageBuilderComponents\Block\AbstractComponent> $blockClass
     * @phpstan-param array<string, FieldDefinition> $fields
     * @phpstan-param list<string> $stylesheets
     */
    public function __construct(
        public readonly string $name,
        public readonly string $label,
        public readonly string $template,
        public readonly string $menuSection,
        public readonly string $icon,
        public readonly int $sortOrder,
        public readonly string $blockClass,
        public readonly array $fields,
        public readonly array $stylesheets = [],
    ) {
    }

    /**
     * Name of the UI form that edits the component.
     *
     * @return string
     */
    public function getFormName(): string
    {
        return self::FORM_PREFIX . $this->name . self::FORM_SUFFIX;
    }
}
