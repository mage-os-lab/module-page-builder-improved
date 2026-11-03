<?php

declare(strict_types=1);

namespace MageOS\PageBuilderLayout\Test\Unit;

use Magento\Framework\Data\OptionSourceInterface;
use MageOS\PageBuilderLayout\Model\Config\Source\AlignItems;
use MageOS\PageBuilderLayout\Model\Config\Source\ColumnsPerLine;
use MageOS\PageBuilderLayout\Model\Config\Source\ContentLayout;
use MageOS\PageBuilderLayout\Model\Config\Source\FlexWrap;
use MageOS\PageBuilderLayout\Model\Config\Source\Gap;
use MageOS\PageBuilderLayout\Model\Config\Source\JustifyContent;
use MageOS\PageBuilderLayout\Model\Config\Source\RowGap;
use PHPUnit\Framework\TestCase;

/**
 * The settings' options: their values are written into CSS as they are.
 */
class SourceTest extends TestCase
{
    /**
     * @return array<int, string>
     */
    private static function values(OptionSourceInterface $source): array
    {
        return array_column($source->toOptionArray(), 'value');
    }

    public function testGapsAreLengthsInPixels(): void
    {
        $expected = ['', '0px', '8px', '16px', '24px', '32px', '48px', '64px'];

        self::assertSame($expected, self::values(new Gap()));
        self::assertSame($expected, self::values(new RowGap()));
        self::assertSame('Same as Column Gap', (string) (new RowGap())->toOptionArray()[0]['label']);
    }

    public function testColumnCounts(): void
    {
        self::assertSame(['', '1', '2', '3', '4', '5', '6'], self::values(new ColumnsPerLine()));
    }

    public function testFlexValuesAreCssKeywords(): void
    {
        self::assertSame(
            ['', 'flex-start', 'center', 'flex-end', 'space-between', 'space-around', 'space-evenly'],
            self::values(new JustifyContent())
        );
        self::assertSame(
            ['', 'stretch', 'flex-start', 'center', 'flex-end', 'baseline'],
            self::values(new AlignItems())
        );
        self::assertSame(['', 'nowrap', 'wrap'], self::values(new FlexWrap()));
    }

    public function testLayoutsAreTheDefaultThenTheConvertersOnes(): void
    {
        self::assertSame(['', ...ContentLayout::LAYOUTS], self::values(new ContentLayout()));
    }
}
