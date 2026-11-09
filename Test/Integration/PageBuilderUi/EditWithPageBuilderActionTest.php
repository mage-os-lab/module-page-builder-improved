<?php

declare(strict_types=1);

namespace MageOS\PageBuilderImproved\Test\Integration\PageBuilderUi;

use Magento\Cms\Ui\Component\Listing\Column\BlockActions;
use Magento\Cms\Ui\Component\Listing\Column\PageActions;
use Magento\Framework\ObjectManager\Config\Reader\Dom as DiConfigReader;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\Ui\Component\Listing\Columns\Column;
use MageOS\PageBuilderUi\Plugin\AddEditWithPageBuilderAction;
use PHPUnit\Framework\TestCase;

/**
 * The plugins in etc/adminhtml/di.xml add "Edit with Page Builder" to core's CMS page and block grids.
 *
 * Runs in the global area, where the grids' columns have no plugins: in the admin area the object manager would
 * create them through an Interceptor generated at runtime, which the test sandbox does not reliably do (the
 * check-extension compile job covers the Interceptors). So each test checks the registration in the admin's DI
 * config, then runs core's column and the plugin, with its virtual type's arguments, one after the other.
 */
class EditWithPageBuilderActionTest extends TestCase
{
    private const PLUGIN = 'mageos_page_builder_ui_edit_with_page_builder';

    public function testThePluginsAreRegisteredForTheAdminOnly(): void
    {
        $reader = Bootstrap::getObjectManager()->create(DiConfigReader::class);
        $admin = $reader->read('adminhtml');
        $global = $reader->read('global');

        foreach ([PageActions::class => 'CmsPage', BlockActions::class => 'CmsBlock'] as $column => $virtualType) {
            self::assertSame(
                AddEditWithPageBuilderAction::class . '\\' . $virtualType,
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
        $actions = $this->prepareDataSource(PageActions::class, 'CmsPage', [
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
        $actions = $this->prepareDataSource(BlockActions::class, 'CmsBlock', ['block_id' => 3, 'title' => 'Footer']);

        self::assertSame(['edit', 'edit_with_page_builder', 'delete'], array_keys($actions));
        $href = (string) $actions['edit_with_page_builder']['href'];
        self::assertStringContainsString('cms/block/edit/block_id/3/', $href);
    }

    /**
     * Core's column, then the plugin, on one grid row; the row's actions.
     *
     * @param class-string<Column> $columnClass
     * @param string $virtualType
     * @param array<string, mixed> $row
     * @return array<string, array<string, mixed>>
     */
    private function prepareDataSource(string $columnClass, string $virtualType, array $row): array
    {
        $objectManager = Bootstrap::getObjectManager();
        $column = $objectManager->create($columnClass, ['data' => ['name' => 'actions']]);
        /** @var AddEditWithPageBuilderAction $plugin */
        $plugin = $objectManager->get(AddEditWithPageBuilderAction::class . '\\' . $virtualType);

        $result = $plugin->afterPrepareDataSource(
            $column,
            $column->prepareDataSource(['data' => ['items' => [$row]]])
        );

        return $result['data']['items'][0]['actions'];
    }
}
