<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Model\Config;

use MageOS\PageBuilderComponents\Model\ComponentDefinition;
use MageOS\PageBuilderComponents\Model\ComponentRegistry;

/**
 * Builds a widget declaration per component, in the form core's widget config reader produces from widget.xml.
 *
 * Core's widget filter renders a `{{widget type="..."}}` directive only for a type declared as a widget, so this
 * is what lets a component render on the storefront. {@see \MageOS\PageBuilderComponents\Plugin\AddWidgetConfig}
 * answers them to core's lookup by type only.
 */
class WidgetConfigBuilder
{
    private const ID_PREFIX = 'mageos_pbc_';

    /**
     * @param ComponentRegistry $componentRegistry
     */
    public function __construct(
        private readonly ComponentRegistry $componentRegistry,
    ) {
    }

    /**
     * Widget declarations of all components, keyed by widget id.
     *
     * @return array<string, array<string, mixed>>
     * @phpstan-return array<string, array{'@': array{type: class-string}, name: string, description: string,
     *     is_email_compatible: string, parameters: array<string, array<string, string>>}>
     */
    public function build(): array
    {
        $widgets = [];
        foreach ($this->componentRegistry->getAll() as $definition) {
            $widgets[self::ID_PREFIX . $definition->name] = $this->buildWidget($definition);
        }

        return $widgets;
    }

    /**
     * Widget declaration of one component.
     *
     * @param ComponentDefinition $definition
     * @return array<string, mixed>
     * @phpstan-return array{'@': array{type: class-string}, name: string, description: string,
     *     is_email_compatible: string, parameters: array<string, array<string, string>>}
     */
    private function buildWidget(ComponentDefinition $definition): array
    {
        $parameters = [];
        $sortOrder = 10;
        foreach ($definition->fields as $name => $fieldDefinition) {
            $parameters[$name] = [
                'type' => 'text',
                'label' => $fieldDefinition->field->getLabel(),
                'visible' => '1',
                'required' => '0',
                'sort_order' => (string) $sortOrder,
            ];
            $sortOrder += 10;
        }

        return [
            '@' => ['type' => $definition->blockClass],
            'name' => $definition->label,
            'description' => (string) __('Page Builder component "%1"', __($definition->label)),
            'is_email_compatible' => '0',
            'parameters' => $parameters,
        ];
    }
}
