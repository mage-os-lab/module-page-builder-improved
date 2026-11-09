<?php

declare(strict_types=1);

namespace MageOS\PageBuilderImproved\Test\Integration\PageBuilderLayout;

use Magento\PageBuilder\Model\ConfigInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * The module's content type XML merges into core's: the layout content types get a Tablet form.
 *
 * Page Builder reads view/adminhtml/pagebuilder files through the admin theme, so this runs in the admin area.
 *
 * @magentoAppArea adminhtml
 */
class TabletBreakpointTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function contentTypes(): array
    {
        return [
            'row' => ['row'],
            'column' => ['column'],
            'column group' => ['column-group'],
            'tabs' => ['tabs'],
        ];
    }

    /**
     * @dataProvider contentTypes
     * @param string $contentType
     */
    public function testTheContentTypeHasATabletForm(string $contentType): void
    {
        $contentTypes = Bootstrap::getObjectManager()->get(ConfigInterface::class)->getContentTypes();

        self::assertArrayHasKey($contentType, $contentTypes);
        $form = $contentTypes[$contentType]['breakpoints']['tablet']['form'] ?? null;
        self::assertSame('pagebuilder_' . str_replace('-', '_', $contentType) . '_tablet_form', $form);
    }
}
