<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Plugin;

use Magento\Widget\Model\Widget;
use MageOS\PageBuilderComponents\Model\Config\WidgetConfigBuilder;

/**
 * Declares each component as a widget to the lookup by type, and to nothing else.
 *
 * Core's widget filter renders a `{{widget type="..."}}` directive only for a type that
 * `Widget::getWidgetByClassType()` knows. The lists of widgets (`getWidgets()`, `getWidgetsArray()`) do not
 * get the components: they feed the editor's "Insert Widget" dialog and Content > Widgets, which would store
 * values the way core does, not encoded the way a component reads them, and `getWidgetsArray()` keys its
 * entries by label, so a component could hide a core widget of the same name.
 */
class AddWidgetConfig
{
    /**
     * @var array<string, array<string, mixed>>|null Block class => widget declaration.
     */
    private ?array $widgetsByType = null;

    /**
     * @param WidgetConfigBuilder $widgetConfigBuilder
     */
    public function __construct(
        private readonly WidgetConfigBuilder $widgetConfigBuilder,
    ) {
    }

    /**
     * Answer a component's declaration for its block class. A widget declared in widget.xml wins.
     *
     * @param Widget $subject
     * @param array|null $result
     * @param string $type
     * @phpstan-param array<string, mixed>|null $result
     * @return array|null
     * @phpstan-return array<string, mixed>|null
     * @SuppressWarnings("PHPMD.UnusedFormalParameter")
     */
    public function afterGetWidgetByClassType(Widget $subject, $result, $type)
    {
        if ($result !== null || !\is_string($type)) {
            return $result;
        }

        return $this->getWidgetsByType()[$type] ?? null;
    }

    /**
     * The components' widget declarations by block class, built once.
     *
     * @return array<string, array<string, mixed>>
     */
    private function getWidgetsByType(): array
    {
        if ($this->widgetsByType === null) {
            $this->widgetsByType = [];
            foreach ($this->widgetConfigBuilder->build() as $widget) {
                $this->widgetsByType[$widget['@']['type']] = $widget;
            }
        }

        return $this->widgetsByType;
    }
}
