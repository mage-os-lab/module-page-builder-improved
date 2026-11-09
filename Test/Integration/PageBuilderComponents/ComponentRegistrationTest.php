<?php

declare(strict_types=1);

namespace MageOS\PageBuilderImproved\Test\Integration\PageBuilderComponents;

use Magento\PageBuilder\Model\ConfigInterface;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Widget\Model\Widget;
use MageOS\PageBuilderComponents\Model\ComponentRegistry;
use MageOS\PageBuilderComponentsExamples\Block\Component\CallToAction;
use MageOS\PageBuilderComponentsExamples\Block\Component\Cards;
use MageOS\PageBuilderComponentsExamples\Block\Component\Hero;
use MageOS\PageBuilderComponentsExamples\Block\Component\ImageText;
use MageOS\PageBuilderComponentsExamples\Block\Component\UspList;
use PHPUnit\Framework\TestCase;

/**
 * The example components, registered in di.xml, reach Page Builder and the widget filter through the plugins.
 *
 * Page Builder reads core's content types (view/adminhtml/pagebuilder) through the admin theme, so this runs in
 * the admin area, where the editor reads them.
 */
#[AppArea('adminhtml')]
class ComponentRegistrationTest extends TestCase
{
    private const COMPONENTS = [
        'mageos_hero' => Hero::class,
        'mageos_cards' => Cards::class,
        'mageos_usp_list' => UspList::class,
        'mageos_image_text' => ImageText::class,
        'mageos_call_to_action' => CallToAction::class,
    ];

    public function testTheRegistryHasTheExampleComponentsFromDiXml(): void
    {
        $registry = Bootstrap::getObjectManager()->get(ComponentRegistry::class);

        foreach (self::COMPONENTS as $name => $blockClass) {
            $definition = $registry->get($name);
            self::assertNotNull($definition, "Component $name is not registered.");
            self::assertSame($blockClass, $definition->blockClass);
        }
    }

    public function testPageBuilderOffersEachComponentAsAContentType(): void
    {
        $contentTypes = Bootstrap::getObjectManager()->get(ConfigInterface::class)->getContentTypes();

        foreach (array_keys(self::COMPONENTS) as $name) {
            self::assertArrayHasKey($name, $contentTypes, "Page Builder has no content type $name.");
        }
        self::assertSame('Hero', (string) $contentTypes['mageos_hero']['label']);
        // Core's content types are still there next to them.
        self::assertArrayHasKey('row', $contentTypes);
    }

    public function testTheWidgetFilterFindsAComponentByItsBlockClass(): void
    {
        $widget = Bootstrap::getObjectManager()->create(Widget::class)->getWidgetByClassType(Hero::class);

        self::assertIsArray($widget, 'The widget filter does not know the Hero component.');
        self::assertSame(Hero::class, $widget['@']['type']);
        self::assertArrayHasKey('title', $widget['parameters']);
    }

    public function testTheWidgetFilterStillReturnsNullForAnUnknownClass(): void
    {
        $widget = Bootstrap::getObjectManager()->create(Widget::class)
            ->getWidgetByClassType('MageOS\\PageBuilderImproved\\NoSuchBlock');

        self::assertNull($widget);
    }
}
