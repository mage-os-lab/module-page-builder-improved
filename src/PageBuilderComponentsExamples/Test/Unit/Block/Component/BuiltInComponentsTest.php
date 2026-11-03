<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponentsExamples\Test\Unit\Block\Component;

use MageOS\PageBuilderComponents\Block\AbstractComponent;
use MageOS\PageBuilderComponents\Model\Config\ContentTypeXmlGenerator;
use MageOS\PageBuilderComponents\Model\Config\FormXmlGenerator;
use MageOS\PageBuilderComponents\Model\Config\Xml;
use MageOS\PageBuilderComponents\Model\DefinitionReader;
use MageOS\PageBuilderComponentsExamples\Test\Unit\TemplateRendering;
use MageOS\PageBuilderComponentsExamples\Test\Unit\XmlSchemaAssertions;
use PHPUnit\Framework\TestCase;

/**
 * The components registered in this module's etc/di.xml.
 */
class BuiltInComponentsTest extends TestCase
{
    use TemplateRendering;
    use XmlSchemaAssertions;

    /**
     * @return array<string, array{string, string}>
     */
    public static function registeredComponents(): array
    {
        $di = simplexml_load_file(\dirname(__DIR__, 4) . '/etc/di.xml');
        self::assertNotFalse($di);
        $items = $di->xpath(
            '//type[@name="MageOS\PageBuilderComponents\Model\ComponentRegistry"]'
            . '/arguments/argument[@name="components"]/item'
        ) ?: [];

        $components = [];
        foreach ($items as $item) {
            $components[(string) $item['name']] = [(string) $item['name'], (string) $item];
        }

        return $components;
    }

    public function testRegistersTheExampleComponents(): void
    {
        self::assertSame(
            ['mageos_hero', 'mageos_cards', 'mageos_usp_list', 'mageos_image_text', 'mageos_call_to_action'],
            array_keys(self::registeredComponents())
        );
    }

    /**
     * @dataProvider registeredComponents
     */
    public function testIsValidForCore(string $name, string $class): void
    {
        $definition = (new DefinitionReader())->read($name, $class);
        $xml = new Xml();

        self::assertValidAgainstSchema(
            (new ContentTypeXmlGenerator($xml))->generate($definition),
            'urn:magento:module:Magento_PageBuilder:etc/content_type.xsd'
        );
        self::assertValidAgainstSchema(
            (new FormXmlGenerator($xml))->generate($definition),
            'urn:magento:module:Magento_Ui:etc/ui_configuration.xsd'
        );
        self::assertFileExists(
            \dirname(__DIR__, 4) . '/view/frontend/templates/' . explode('::', $definition->template)[1]
        );
    }

    /**
     * Every template renders with the defaults alone: a component just dropped on the stage.
     *
     * @dataProvider registeredComponents
     */
    public function testRendersWithItsDefaults(string $name, string $class): void
    {
        self::assertTrue(is_a($class, AbstractComponent::class, true));
        $html = $this->renderComponent($class);

        self::assertStringNotContainsString('<a ', $html, $name);
        self::assertStringNotContainsString('<img', $html, $name);
    }
}
