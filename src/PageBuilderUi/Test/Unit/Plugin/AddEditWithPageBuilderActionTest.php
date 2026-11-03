<?php

declare(strict_types=1);

namespace MageOS\PageBuilderUi\Test\Unit\Plugin;

use Magento\Framework\UrlInterface;
use Magento\PageBuilder\Model\ConfigInterface;
use Magento\Ui\Component\Listing\Columns\Column;
use MageOS\PageBuilderUi\Plugin\AddEditWithPageBuilderAction;
use PHPUnit\Framework\TestCase;

class AddEditWithPageBuilderActionTest extends TestCase
{
    public function testAddsTheActionRightAfterEdit(): void
    {
        $result = $this->createPlugin(true)->afterPrepareDataSource($this->createColumn(), [
            'data' => [
                'items' => [
                    [
                        'page_id' => 5,
                        'actions' => [
                            'edit' => ['href' => 'edit/5', 'label' => 'Edit'],
                            'delete' => ['href' => 'delete/5', 'label' => 'Delete'],
                            'preview' => ['href' => 'view/5', 'label' => 'View'],
                        ],
                    ],
                ],
            ],
        ]);

        $actions = $result['data']['items'][0]['actions'];
        self::assertSame(['edit', 'edit_with_page_builder', 'delete', 'preview'], array_keys($actions));
        self::assertSame(
            'cms/page/edit?page_id=5#edit-with-page-builder',
            $actions['edit_with_page_builder']['href']
        );
        self::assertSame('Edit with Page Builder', (string) $actions['edit_with_page_builder']['label']);
    }

    public function testLeavesRowsWithoutAnIdOrAnEditActionAlone(): void
    {
        $items = [
            ['actions' => ['edit' => ['href' => 'edit']]],
            ['page_id' => 6, 'actions' => ['preview' => ['href' => 'view/6']]],
        ];

        $result = $this->createPlugin(true)
            ->afterPrepareDataSource($this->createColumn(), ['data' => ['items' => $items]]);

        self::assertSame($items, $result['data']['items']);
    }

    public function testDoesNothingWhilePageBuilderIsDisabled(): void
    {
        $dataSource = ['data' => ['items' => [['page_id' => 5, 'actions' => ['edit' => ['href' => 'edit/5']]]]]];

        self::assertSame(
            $dataSource,
            $this->createPlugin(false)->afterPrepareDataSource($this->createColumn(), $dataSource)
        );
    }

    public function testDoesNothingWithoutItems(): void
    {
        self::assertSame(
            ['data' => ['totalRecords' => 0]],
            $this->createPlugin(true)->afterPrepareDataSource($this->createColumn(), ['data' => ['totalRecords' => 0]])
        );
    }

    /**
     * @param bool $enabled
     * @return AddEditWithPageBuilderAction
     */
    private function createPlugin(bool $enabled): AddEditWithPageBuilderAction
    {
        $urlBuilder = $this->createMock(UrlInterface::class);
        $urlBuilder->method('getUrl')->willReturnCallback(
            static function (string $path, array $params): string {
                $fragment = $params['_fragment'];
                unset($params['_fragment']);

                return $path . '?' . http_build_query($params) . '#' . $fragment;
            }
        );
        $config = $this->createMock(ConfigInterface::class);
        $config->method('isEnabled')->willReturn($enabled);

        return new AddEditWithPageBuilderAction($urlBuilder, $config, 'cms/page/edit', 'page_id');
    }

    /**
     * @return Column
     */
    private function createColumn(): Column
    {
        $column = $this->createMock(Column::class);
        $column->method('getData')->with('name')->willReturn('actions');

        return $column;
    }
}
