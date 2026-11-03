<?php

declare(strict_types=1);

namespace MageOS\PageBuilderLayout\Test\Unit;

use PHPUnit\Framework\TestCase;

/**
 * The Tablet view on the stage, and the config the browser test (Test/Js/layout.html) runs core's code with.
 */
class ViewportTest extends TestCase
{
    use CoreConfig;

    private const FIXTURE = 'Test/Js/_files/config.json';

    public function testTabletIsOnTheStageBetweenDesktopAndMobile(): void
    {
        $breakpoints = self::breakpoints();
        $stage = array_keys(array_filter(
            $breakpoints,
            static fn (array $breakpoint): bool => isset($breakpoint['settings']['stage'])
        ));

        // Core's Page Builder lists the viewports with a "stage" entry, in this order, on the switcher.
        self::assertSame(['desktop', 'tablet', 'mobile'], $stage);
        self::assertSame('Tablet', $breakpoints['tablet']['settings']['label']);
        self::assertSame('tablet-switcher', $breakpoints['tablet']['settings']['class']);
        // Core's conditions (for the product carousel) are kept.
        self::assertSame(['max-width' => '1024px', 'min-width' => '768px'], $breakpoints['tablet']['conditions']);
        $icon = explode('::', $breakpoints['tablet']['settings']['icon'], 2);
        self::assertSame('MageOS_PageBuilderLayout', $icon[0]);
        self::assertFileExists(self::module('view/adminhtml/web/' . ($icon[1] ?? '')));
    }

    public function testTabletMediaQueryFitsBetweenTheOthers(): void
    {
        $breakpoints = self::breakpoints();

        // Desktop styles apply everywhere; Tablet and Mobile ones, in their media query, must not overlap.
        self::assertArrayNotHasKey('media', $breakpoints['desktop']['settings']);
        self::assertSame('only screen and (max-width: 767px)', $breakpoints['mobile']['settings']['media']);
        self::assertSame(
            'only screen and (min-width: 768px) and (max-width: 1023px)',
            $breakpoints['tablet']['settings']['media']
        );
        self::assertSame('1024px', $breakpoints['desktop']['conditions']['min-width']);
    }

    public function testTheStageHasATabletWidthInsideTheTabletMediaQuery(): void
    {
        $less = (string) file_get_contents(self::module('view/adminhtml/web/css/source/_module.less'));
        $media = (string) self::breakpoints()['tablet']['settings']['media'];

        preg_match('/@mageos-layout__tablet-width: (\d+)px;/', $less, $width);
        preg_match('/min-width: (\d+)px\) and \(max-width: (\d+)px/', $media, $range);

        self::assertStringContainsString('.tablet-viewport {', $less);
        self::assertSame('1023', $width[1] ?? null);
        // The stage shows the Tablet styles at a width the storefront shows them at too.
        self::assertGreaterThanOrEqual((int) ($range[1] ?? PHP_INT_MAX), (int) $width[1]);
        self::assertLessThanOrEqual((int) ($range[2] ?? 0), (int) $width[1]);
    }

    public function testBrowserTestFixtureIsTheConfigPageBuilderGetsFromTheModule(): void
    {
        $viewports = [];
        foreach (self::breakpoints() as $name => $breakpoint) {
            if (isset($breakpoint['settings']['stage'])) {
                $viewports[$name] = ['media' => $breakpoint['settings']['media'] ?? null];
            }
        }
        $columns = self::contentType('column_group.xml')['appearances']['default']['elements']['main']['style'];
        $column = self::contentType('column.xml')['appearances']['full-height']['elements']['main']['style'];
        $row = self::contentType('row.xml')['appearances'];
        $ours = static fn (array $styles): array => array_values(array_filter(
            $styles,
            static fn (array $style): bool => str_starts_with((string) ($style['var'] ?? ''), 'mageos_')
                || ($style['var'] ?? '') === 'display'
        ));

        $expected = json_encode([
            'viewports' => $viewports,
            'styles' => [
                'column-group' => $ours($columns),
                'column' => $ours($column),
                'row-inner' => $ours($row['contained']['elements']['inner']['style']),
                'row-main' => $ours($row['full-bleed']['elements']['main']['style']),
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

        if (getenv('MAGEOS_UPDATE_FIXTURES')) {
            file_put_contents(self::module(self::FIXTURE), $expected);
        }
        self::assertSame(
            $expected,
            is_file(self::module(self::FIXTURE)) ? (string) file_get_contents(self::module(self::FIXTURE)) : '',
            'Run the test with MAGEOS_UPDATE_FIXTURES=1 to update ' . self::FIXTURE
        );
    }
}
