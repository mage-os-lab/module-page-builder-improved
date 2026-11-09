<?php

declare(strict_types=1);

namespace MageOS\PageBuilderImproved\Test\Integration\PageBuilderUi;

use Magento\Cms\Ui\Component\Listing\Column\BlockActions;
use Magento\Cms\Ui\Component\Listing\Column\PageActions;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * The plugins in etc/adminhtml/di.xml add "Edit with Page Builder" to core's CMS page and block grids.
 *
 * @magentoAppArea adminhtml
 */
class EditWithPageBuilderActionTest extends TestCase
{
    public function testThePagesGridGetsTheActionRightAfterEdit(): void
    {
        $column = Bootstrap::getObjectManager()->create(PageActions::class, ['data' => ['name' => 'actions']]);

        $result = $column->prepareDataSource([
            'data' => ['items' => [['page_id' => 7, 'title' => 'About us', 'identifier' => 'about-us']]],
        ]);

        $actions = $result['data']['items'][0]['actions'];
        self::assertSame(['edit', 'edit_with_page_builder', 'delete', 'preview'], array_keys($actions));
        $href = $actions['edit_with_page_builder']['href'];
        self::assertStringContainsString('cms/page/edit/page_id/7/', $href);
        self::assertStringEndsWith('#edit-with-page-builder', $href);
        self::assertSame('Edit with Page Builder', (string) $actions['edit_with_page_builder']['label']);
    }

    public function testTheBlocksGridGetsTheActionToo(): void
    {
        $column = Bootstrap::getObjectManager()->create(BlockActions::class, ['data' => ['name' => 'actions']]);

        $result = $column->prepareDataSource([
            'data' => ['items' => [['block_id' => 3, 'title' => 'Footer links']]],
        ]);

        $actions = $result['data']['items'][0]['actions'];
        self::assertArrayHasKey('edit_with_page_builder', $actions);
        self::assertStringContainsString('cms/block/edit/block_id/3/', $actions['edit_with_page_builder']['href']);
    }
}
