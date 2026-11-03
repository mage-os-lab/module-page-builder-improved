<?php

declare(strict_types=1);

namespace MageOS\PageBuilderLayout\Test\Unit;

use Magento\Framework\Data\OptionSourceInterface;
use PHPUnit\Framework\TestCase;

/**
 * The module's edit forms: the Layout settings, and the Tablet (and Mobile) views' forms.
 */
class FormTest extends TestCase
{
    use CoreConfig;

    private const UI_SCHEMA = 'urn:magento:module:Magento_Ui:etc/ui_configuration.xsd';

    public function testEveryFormIsValid(): void
    {
        $forms = glob(self::module('view/adminhtml/ui_component/*.xml')) ?: [];
        self::assertCount(12, $forms);
        foreach ($forms as $path) {
            self::assertValidAgainstSchema((string) file_get_contents($path), self::UI_SCHEMA);
        }
    }

    /**
     * @return array<string, array{string, array<string, string>}>
     */
    public static function layoutForms(): array
    {
        return [
            'columns' => ['pagebuilder_column_group_form', ContentTypeTest::COLUMNS_LAYOUT],
            'row' => ['pagebuilder_row_form', ContentTypeTest::CONTENT_LAYOUT],
            'column' => ['pagebuilder_column_form', ContentTypeTest::CONTENT_LAYOUT],
        ];
    }

    /**
     * @dataProvider layoutForms
     * @param array<string, string> $settings
     */
    public function testLayoutFieldsAreTheSettingsKeptPerViewport(string $form, array $settings): void
    {
        $xpath = self::form($form);

        $fields = [];
        foreach ($xpath->query('/form/fieldset[@name="mageos_layout_fieldset"]/field') ?: [] as $field) {
            self::assertInstanceOf(\DOMElement::class, $field);
            $name = $field->getAttribute('name');
            $fields[] = $name;
            self::assertSame($name, $xpath->evaluate('string(settings/dataScope)', $field));
            self::assertSame('', $xpath->evaluate('string(argument//item[@name="default"])', $field), $name);
            foreach (['tablet', 'mobile'] as $viewport) {
                self::assertSame(
                    'true',
                    $xpath->evaluate("string(argument//item[@name='breakpoints']/item[@name='$viewport'])", $field),
                    "$name in the $viewport view"
                );
            }

            $source = $xpath->evaluate('string(formElements/select/settings/options/@class)', $field);
            self::assertTrue(is_a($source, OptionSourceInterface::class, true), "$name: $source");
            self::assertSame('', (new $source())->toOptionArray()[0]['value'], "$name starts with the default");
        }

        self::assertSame(array_keys($settings), $fields);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function tabletForms(): array
    {
        $types = [];
        foreach (['row', 'column', 'banner', 'slide', 'slider', 'tabs', 'tab_item'] as $type) {
            $types[$type] = [$type];
        }

        return $types;
    }

    /**
     * @dataProvider tabletForms
     */
    public function testTabletFormsAreCoresMobileOnesForTablets(string $type): void
    {
        $xpath = self::form("pagebuilder_{$type}_tablet_form");
        self::assertViewportForm($xpath, "pagebuilder_{$type}_tablet_form", "pagebuilder_{$type}_form");

        $mobile = new \DOMXPath(self::assertValidAgainstSchema(
            (string) file_get_contents(self::core("view/adminhtml/ui_component/pagebuilder_{$type}_mobile_form.xml")),
            self::UI_SCHEMA
        ));
        self::assertSame(self::viewportFields($mobile, 'mobile'), self::viewportFields($xpath, 'tablet'));
        self::assertSame([], self::viewportFields($xpath, 'mobile'));
    }

    /**
     * Fields a form flags for a viewport.
     *
     * @return array<int, string>
     */
    private static function viewportFields(\DOMXPath $xpath, string $viewport): array
    {
        $fields = [];
        $query = "//field[argument//item[@name='breakpoints']/item[@name='$viewport'] = 'true']";
        foreach ($xpath->query($query) ?: [] as $field) {
            self::assertInstanceOf(\DOMElement::class, $field);
            $fields[] = $field->getAttribute('name');
        }

        return $fields;
    }

    public function testColumnsHaveTabletAndMobileForms(): void
    {
        foreach (['tablet', 'mobile'] as $viewport) {
            $name = "pagebuilder_column_group_{$viewport}_form";
            self::assertViewportForm(self::form($name), $name, 'pagebuilder_column_group_form');
        }
    }

    private static function form(string $name): \DOMXPath
    {
        return new \DOMXPath(self::assertValidAgainstSchema(
            (string) file_get_contents(self::module("view/adminhtml/ui_component/$name.xml")),
            self::UI_SCHEMA
        ));
    }

    private static function assertViewportForm(\DOMXPath $xpath, string $name, string $extends): void
    {
        $source = $name . '_data_source';

        self::assertSame($extends, $xpath->evaluate('string(/form/@extends)'));
        self::assertSame($name, $xpath->evaluate('string(/form/settings/namespace)'));
        self::assertSame("$name.$source", $xpath->evaluate('string(/form/argument//item[@name="provider"])'));
        self::assertSame("$name.$source", $xpath->evaluate('string(/form/settings/deps/dep)'));
        self::assertSame($source, $xpath->evaluate('string(/form/dataSource/@name)'));
        self::assertSame($source, $xpath->evaluate('string(/form/dataSource/dataProvider/@name)'));
    }
}
