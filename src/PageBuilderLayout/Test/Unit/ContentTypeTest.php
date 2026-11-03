<?php

declare(strict_types=1);

namespace MageOS\PageBuilderLayout\Test\Unit;

use PHPUnit\Framework\TestCase;

/**
 * The module's additions to core's content types, merged into core's files and converted as core does.
 */
class ContentTypeTest extends TestCase
{
    use CoreConfig;

    private const READER = 'MageOS_PageBuilderLayout/js/property/custom-property';

    /**
     * Elements holding a row's or column's content, by appearance.
     */
    public const CONTENT_ELEMENTS = [
        'column.xml' => [
            'full-height' => 'main',
            'align-top' => 'main',
            'align-center' => 'main',
            'align-bottom' => 'main',
        ],
        'row.xml' => ['contained' => 'inner', 'full-width' => 'inner', 'full-bleed' => 'main'],
    ];

    /**
     * Content layout settings and their converters.
     */
    public const CONTENT_LAYOUT = [
        'mageos_layout' => 'layout',
        'mageos_grid_columns' => 'grid-columns',
        'mageos_justify_content' => 'mirrored',
        'mageos_align_items' => 'mirrored',
        'mageos_flex_wrap' => 'mirrored',
        'mageos_gap' => 'mirrored',
    ];

    /**
     * Layout settings of "Columns" and their converters.
     */
    public const COLUMNS_LAYOUT = [
        'mageos_column_gap' => 'custom-property',
        'mageos_row_gap' => 'custom-property',
        'mageos_columns_per_line' => 'columns-per-line',
        'mageos_columns_justify' => 'custom-property',
    ];

    public function testColumnsGetTheLayoutCustomPropertiesAndAMarker(): void
    {
        $type = self::contentType('column_group.xml');
        $main = $type['appearances']['default']['elements']['main'];

        self::assertSame(self::COLUMNS_LAYOUT, self::ourStyles($main['style']));
        self::assertIsArray($main['attributes']);
        self::assertContains(
            ['name' => 'data-mageos-layout', 'value' => 'columns', 'static' => true],
            $main['attributes']
        );
        // Core's own styles and attributes are still there.
        self::assertContains('background_color', array_column($main['style'], 'var'));
        self::assertContains('data-grid-size', array_column($main['attributes'], 'name'));
        self::assertSame('Columns', $type['label']);
    }

    public function testColumnsGetTabletAndMobileForms(): void
    {
        $breakpoints = self::contentType('column_group.xml')['breakpoints'];

        self::assertSame('pagebuilder_column_group_tablet_form', $breakpoints['tablet']['form'] ?? null);
        self::assertSame('pagebuilder_column_group_mobile_form', $breakpoints['mobile']['form'] ?? null);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function rowAndColumn(): array
    {
        return ['column' => ['column.xml'], 'row' => ['row.xml']];
    }

    /**
     * @dataProvider rowAndColumn
     */
    public function testRowAndColumnGetTheContentLayoutWhereTheirContentIs(string $file): void
    {
        $type = self::contentType($file);
        $core = self::contentType($file, false);

        self::assertSame(array_keys(self::CONTENT_ELEMENTS[$file]), array_keys($type['appearances']));
        foreach (self::CONTENT_ELEMENTS[$file] as $appearance => $element) {
            $elements = $type['appearances'][$appearance]['elements'];
            self::assertSame(self::CONTENT_LAYOUT, self::ourStyles($elements[$element]['style']), $appearance);
            foreach ($elements as $name => $config) {
                if ($name !== $element) {
                    self::assertSame([], self::ourStyles($config['style'] ?? []), "$appearance $name");
                }
                // Core's own styles are all still there.
                self::assertSame(
                    $core['appearances'][$appearance]['elements'][$name]['style'] ?? [],
                    array_values(array_filter(
                        $config['style'] ?? [],
                        static fn (array $style): bool => !str_starts_with((string) ($style['var'] ?? ''), 'mageos_')
                    )),
                    "$appearance $name"
                );
            }
        }
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function typesWithATabletForm(): array
    {
        $types = [];
        foreach (['row', 'column', 'banner', 'slide', 'slider', 'tabs', 'tab_item'] as $type) {
            $types[$type] = [$type . '.xml', $type];
        }

        return $types;
    }

    /**
     * @dataProvider typesWithATabletForm
     */
    public function testCoreTypesWithAMobileFormGetATabletOne(string $file, string $name): void
    {
        $breakpoints = self::contentType($file)['breakpoints'];

        self::assertSame("pagebuilder_{$name}_tablet_form", $breakpoints['tablet']['form'] ?? null);
        self::assertSame("pagebuilder_{$name}_mobile_form", $breakpoints['mobile']['form'] ?? null);
    }

    public function testEveryStyleReadsTheCustomPropertyItsConverterWrites(): void
    {
        foreach (glob(self::module('view/adminhtml/pagebuilder/content_type/*.xml')) ?: [] as $path) {
            $xml = simplexml_load_file($path);
            self::assertNotFalse($xml);
            foreach ($xml->xpath('//style') ?: [] as $style) {
                $var = (string) $style['name'];
                // js/converter/style/custom-property and mirrored name the property after the setting.
                self::assertSame('--' . str_replace('_', '-', $var), (string) $style['source'], $var);
                self::assertSame(self::READER, (string) $style['reader'], $var);
            }
        }
    }

    public function testEveryReferencedComponentExists(): void
    {
        foreach (glob(self::module('view/adminhtml/pagebuilder/content_type/*.xml')) ?: [] as $path) {
            preg_match_all('#"MageOS_PageBuilderLayout/(js/[a-z/-]+)"#', (string) file_get_contents($path), $matches);
            foreach ($matches[1] as $component) {
                self::assertFileExists(self::module("view/adminhtml/web/$component.js"));
            }
            preg_match_all('#<form>([a-z_]+)</form>#', (string) file_get_contents($path), $matches);
            foreach ($matches[1] as $form) {
                self::assertFileExists(self::module("view/adminhtml/ui_component/$form.xml"));
            }
        }
    }

    /**
     * The module's styles of an element: setting => converter.
     *
     * @param array<int, array<string, mixed>> $styles
     * @return array<string, string>
     */
    private static function ourStyles(array $styles): array
    {
        $ours = [];
        foreach ($styles as $style) {
            if (str_starts_with((string) ($style['var'] ?? ''), 'mageos_')) {
                self::assertSame(self::READER, $style['reader']);
                $ours[(string) $style['var']] = str_replace(
                    'MageOS_PageBuilderLayout/js/converter/style/',
                    '',
                    (string) $style['converter']
                );
            }
        }

        return $ours;
    }
}
