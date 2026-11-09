<?php

declare(strict_types=1);

namespace MageOS\PageBuilderImproved\Test\Integration\PageBuilderUi;

use Magento\Cms\Ui\Component\Listing\Column\BlockActions;
use Magento\Cms\Ui\Component\Listing\Column\PageActions;
use Magento\Framework\ObjectManager\Config\Reader\Dom as DiConfigReader;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Ui\Component\Listing\Columns\Column;
use MageOS\PageBuilderUi\Plugin\AddEditWithPageBuilderAction\CmsBlock;
use MageOS\PageBuilderUi\Plugin\AddEditWithPageBuilderAction\CmsPage;
use PHPUnit\Framework\TestCase;

/**
 * The plugins in etc/adminhtml/di.xml add "Edit with Page Builder" to core's CMS page and block grids: the
 * columns are created in the admin, through their Interceptors, as the grids create them.
 */
#[AppArea('adminhtml')]
class EditWithPageBuilderActionTest extends TestCase
{
    private const PLUGIN = 'mageos_page_builder_ui_edit_with_page_builder';

    public function testThePluginsAreRegisteredForTheAdminOnly(): void
    {
        $reader = Bootstrap::getObjectManager()->create(DiConfigReader::class);
        $admin = $reader->read('adminhtml');
        $global = $reader->read('global');

        foreach ([PageActions::class => CmsPage::class, BlockActions::class => CmsBlock::class] as $column => $plugin) {
            self::assertSame(
                $plugin,
                ltrim((string) ($admin[$column]['plugins'][self::PLUGIN]['instance'] ?? ''), '\\'),
                "$column has no Edit with Page Builder plugin in the admin."
            );
            self::assertArrayNotHasKey(
                self::PLUGIN,
                $global[$column]['plugins'] ?? [],
                "$column: the plugin is not limited to the admin."
            );
        }
    }

    public function testThePagesGridGetsTheActionRightAfterEdit(): void
    {
        $actions = $this->prepareDataSource(PageActions::class, [
            'page_id' => 7,
            'title' => 'About us',
            'identifier' => 'about-us',
        ]);

        self::assertSame(['edit', 'edit_with_page_builder', 'delete', 'preview'], array_keys($actions));
        $href = (string) $actions['edit_with_page_builder']['href'];
        self::assertStringContainsString('cms/page/edit/page_id/7/', $href);
        self::assertStringEndsWith('#edit-with-page-builder', $href);
        self::assertSame('Edit with Page Builder', (string) $actions['edit_with_page_builder']['label']);
    }

    public function testTheBlocksGridGetsTheActionToo(): void
    {
        $actions = $this->prepareDataSource(BlockActions::class, ['block_id' => 3, 'title' => 'Footer']);

        self::assertSame(['edit', 'edit_with_page_builder', 'delete'], array_keys($actions));
        $href = (string) $actions['edit_with_page_builder']['href'];
        self::assertStringContainsString('cms/block/edit/block_id/3/', $href);
        self::assertStringEndsWith('#edit-with-page-builder', $href);
    }

    /**
     * The actions core's column, with the plugin, gives one grid row.
     *
     * @param class-string<Column> $columnClass
     * @param array<string, mixed> $row
     * @return array<string, array<string, mixed>>
     */
    private function prepareDataSource(string $columnClass, array $row): array
    {
        $column = Bootstrap::getObjectManager()->create($columnClass, ['data' => ['name' => 'actions']]);
        $result = $column->prepareDataSource(['data' => ['items' => [$row]]]);

        return $result['data']['items'][0]['actions'];
    }
}
