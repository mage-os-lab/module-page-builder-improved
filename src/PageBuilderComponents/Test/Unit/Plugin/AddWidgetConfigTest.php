<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Test\Unit\Plugin;

use Magento\Framework\Escaper;
use Magento\Framework\Phrase;
use Magento\Framework\Phrase\RendererInterface;
use Magento\Framework\View\Asset\Repository;
use Magento\Framework\View\Asset\Source;
use Magento\Framework\View\Element\BlockInterface as ElementBlockInterface;
use Magento\Framework\View\FileSystem;
use Magento\Framework\View\LayoutInterface;
use Magento\Widget\Block\BlockInterface;
use Magento\Widget\Helper\Conditions;
use Magento\Widget\Model\Config\Data;
use Magento\Widget\Model\Template\Filter;
use Magento\Widget\Model\Widget;
use MageOS\PageBuilderComponents\Model\Config\WidgetConfigBuilder;
use MageOS\PageBuilderComponents\Model\DirectiveValueCodec;
use MageOS\PageBuilderComponents\Plugin\AddWidgetConfig;
use MageOS\PageBuilderComponents\Test\Unit\Fixture\AllFieldsComponent;
use MageOS\PageBuilderComponents\Test\Unit\Fixture\MinimalComponent;
use MageOS\PageBuilderComponents\Test\Unit\RegistryFactory;
use PHPUnit\Framework\TestCase;

/**
 * Runs the plugin on core's own widget model, the way its generated interceptor does.
 */
class AddWidgetConfigTest extends TestCase
{
    private const CORE_WIDGETS = [
        'cms_page_link' => [
            '@' => ['type' => 'Magento\Cms\Block\Widget\Page\Link'],
            'name' => 'CMS Page Link',
            'description' => 'Link to a CMS Page',
            'parameters' => [],
        ],
        // The same label as a component: getWidgetsArray() keys by label.
        'core_minimal' => [
            '@' => ['type' => 'Magento\Cms\Block\Widget\Block'],
            'name' => 'Minimal',
            'description' => 'A core widget',
            'parameters' => [],
        ],
    ];

    public function testCoresLookupByTypeFindsAComponent(): void
    {
        $widget = $this->createWidgetModel()->getWidgetByClassType(MinimalComponent::class);

        self::assertIsArray($widget);
        self::assertSame(['type' => MinimalComponent::class], $widget['@']);
        self::assertSame('Minimal', $widget['name']);
        self::assertSame('Page Builder component "Minimal"', $widget['description']);
        self::assertSame(
            ['heading' => [
                'type' => 'text',
                'label' => 'Heading',
                'visible' => '1',
                'required' => '0',
                'sort_order' => '10',
            ]],
            $widget['parameters']
        );
        self::assertSame(
            AllFieldsComponent::class,
            $this->createWidgetModel()->getWidgetByClassType(AllFieldsComponent::class)['@']['type'] ?? null
        );
        self::assertSame(
            'CMS Page Link',
            $this->createWidgetModel()->getWidgetByClassType('Magento\Cms\Block\Widget\Page\Link')['name'] ?? null
        );
        self::assertNull($this->createWidgetModel()->getWidgetByClassType('Vendor\Module\Block\Unknown'));
    }

    /**
     * The lists feed "Insert Widget" and Content > Widgets, which would store values unencoded.
     */
    public function testListsOfWidgetsLeaveTheComponentsOut(): void
    {
        $model = $this->createWidgetModel();

        self::assertSame(array_keys(self::CORE_WIDGETS), array_keys($model->getWidgets()));
        $types = array_column($model->getWidgetsArray(), 'type', 'code');
        self::assertSame(
            [
                'cms_page_link' => 'Magento\Cms\Block\Widget\Page\Link',
                'core_minimal' => 'Magento\Cms\Block\Widget\Block',
            ],
            $types
        );
    }

    public function testAWidgetFromWidgetXmlForTheSameTypeWins(): void
    {
        $fromXml = ['@' => ['type' => MinimalComponent::class], 'name' => 'From XML', 'parameters' => []];

        $widget = $this->createWidgetModel(['mageos_pbc_test_minimal' => $fromXml])
            ->getWidgetByClassType(MinimalComponent::class);

        self::assertSame('From XML', $widget['name'] ?? null);
    }

    public function testBuildsTheDeclarationsOnce(): void
    {
        $widgets = (new WidgetConfigBuilder(RegistryFactory::create()))->build();
        $builder = $this->createMock(WidgetConfigBuilder::class);
        $builder->expects(self::once())->method('build')->willReturn($widgets);
        $plugin = new AddWidgetConfig($builder);
        $subject = $this->createMock(Widget::class);

        $plugin->afterGetWidgetByClassType($subject, null, MinimalComponent::class);
        $plugin->afterGetWidgetByClassType($subject, null, AllFieldsComponent::class);
        $plugin->afterGetWidgetByClassType($subject, null, 'Vendor\Module\Block\Unknown');
    }

    public function testTheDescriptionIsTranslated(): void
    {
        $renderer = Phrase::getRenderer();
        Phrase::setRenderer(new class () implements RendererInterface {
            /**
             * @param string[] $source
             * @param mixed[] $arguments
             * @return string
             */
            public function render(array $source, array $arguments)
            {
                $text = ['Page Builder component "%1"' => 'Composant Page Builder « %1 »', 'Minimal' => 'Minimal FR'];
                $result = (string) end($source);
                $result = $text[$result] ?? $result;
                foreach ($arguments as $index => $argument) {
                    $result = str_replace('%' . ($index + 1), (string) $argument, $result);
                }

                return $result;
            }
        });
        try {
            $widget = $this->createWidgetModel()->getWidgetByClassType(MinimalComponent::class);
        } finally {
            Phrase::setRenderer($renderer);
        }

        self::assertSame('Composant Page Builder « Minimal FR »', $widget['description'] ?? null);
    }

    /**
     * A directive the stage stored renders through core's widget filter, with its values as they were stored.
     */
    public function testCoresWidgetFilterRendersAStoredDirective(): void
    {
        $heading = (new DirectiveValueCodec())->encode('Say "hi" & {{bye}}');
        $block = new class () implements BlockInterface, ElementBlockInterface {
            /**
             * @inheritdoc
             */
            public function toHtml()
            {
                return '<h2>rendered</h2>';
            }

            /**
             * @param mixed[] $arr
             * @return $this
             */
            public function addData(array $arr)
            {
                return $this;
            }

            /**
             * @param string|mixed[] $key
             * @param mixed $value
             * @return $this
             */
            public function setData($key, $value = null)
            {
                return $this;
            }
        };
        $layout = $this->createMock(LayoutInterface::class);
        $layout->expects(self::once())->method('createBlock')
            ->with(
                MinimalComponent::class,
                null,
                ['data' => ['type' => MinimalComponent::class, 'heading' => $heading]]
            )
            ->willReturn($block);
        $filter = $this->createFilter($layout);

        self::assertSame(
            '<h2>rendered</h2>',
            $filter->generateWidget(['', 'widget', ' type="' . MinimalComponent::class . "\" heading=\"$heading\""])
        );
        self::assertSame('', $filter->generateWidget(['', 'widget', ' type="Vendor\Module\Block\Unknown"']));
    }

    /**
     * Core's widget model, with the plugin applied as its interceptor would.
     *
     * @param array<string, array<string, mixed>> $coreWidgets
     * @return Widget
     */
    private function createWidgetModel(array $coreWidgets = self::CORE_WIDGETS): Widget
    {
        $dataStorage = $this->createMock(Data::class);
        $dataStorage->method('get')->willReturn($coreWidgets);
        $plugin = new AddWidgetConfig(new WidgetConfigBuilder(RegistryFactory::create()));

        return new class (
            $plugin,
            $this->createMock(Escaper::class),
            $dataStorage,
            $this->createMock(Repository::class),
            $this->createMock(Source::class),
            $this->createMock(FileSystem::class),
            $this->createMock(Conditions::class)
        ) extends Widget {
            /**
             * @param AddWidgetConfig $plugin
             * @param Escaper $escaper
             * @param Data $dataStorage
             * @param Repository $assetRepo
             * @param Source $assetSource
             * @param FileSystem $viewFileSystem
             * @param Conditions $conditionsHelper
             */
            public function __construct(
                private readonly AddWidgetConfig $plugin,
                Escaper $escaper,
                Data $dataStorage,
                Repository $assetRepo,
                Source $assetSource,
                FileSystem $viewFileSystem,
                Conditions $conditionsHelper
            ) {
                parent::__construct(
                    $escaper,
                    $dataStorage,
                    $assetRepo,
                    $assetSource,
                    $viewFileSystem,
                    $conditionsHelper
                );
            }

            /**
             * @param string $type
             * @return array<string, mixed>|null
             */
            public function getWidgetByClassType($type)
            {
                return $this->plugin->afterGetWidgetByClassType($this, parent::getWidgetByClassType($type), $type);
            }
        };
    }

    /**
     * Core's widget filter, with only what generateWidget() uses.
     *
     * @param LayoutInterface $layout
     * @return Filter
     */
    private function createFilter(LayoutInterface $layout): Filter
    {
        $filter = (new \ReflectionClass(Filter::class))->newInstanceWithoutConstructor();
        foreach (['_widget' => $this->createWidgetModel(), '_layout' => $layout] as $property => $value) {
            $reflection = new \ReflectionProperty($filter, $property);
            $reflection->setValue($filter, $value);
        }

        return $filter;
    }
}
