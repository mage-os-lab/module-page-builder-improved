<?php

declare(strict_types=1);

namespace MageOS\PageBuilderUi\Plugin;

use Magento\Framework\UrlInterface;
use Magento\PageBuilder\Model\ConfigInterface;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Adds "Edit with Page Builder" to a grid's row actions, right after "Edit". It opens the edit page with Page
 * Builder in full screen (js/edit-with-page-builder.js reads the fragment).
 *
 * Configured per grid in etc/adminhtml/di.xml: the CMS pages' and blocks' action columns.
 */
class AddEditWithPageBuilderAction
{
    public const ACTION = 'edit_with_page_builder';
    public const FRAGMENT = 'edit-with-page-builder';

    /**
     * @param UrlInterface $urlBuilder
     * @param ConfigInterface $pageBuilderConfig
     * @param string $editUrlPath The edit page's route, e.g. cms/page/edit.
     * @param string $idField The row's id, also the edit URL's parameter, e.g. page_id.
     */
    public function __construct(
        private readonly UrlInterface $urlBuilder,
        private readonly ConfigInterface $pageBuilderConfig,
        private readonly string $editUrlPath,
        private readonly string $idField,
    ) {
    }

    /**
     * Add the action to each row that has an id and an "Edit" action.
     *
     * @param Column $subject
     * @param mixed[] $result
     * @return mixed[]
     */
    public function afterPrepareDataSource(Column $subject, array $result): array
    {
        if (!isset($result['data']['items']) || !\is_array($result['data']['items'])
            || !$this->pageBuilderConfig->isEnabled()
        ) {
            return $result;
        }

        $name = (string) $subject->getData('name');
        foreach ($result['data']['items'] as &$item) {
            if (!isset($item[$this->idField], $item[$name]['edit']) || !\is_array($item[$name])) {
                continue;
            }
            $item[$name] = $this->insertAfter($item[$name], 'edit', self::ACTION, [
                'href' => $this->urlBuilder->getUrl($this->editUrlPath, [
                    $this->idField => $item[$this->idField],
                    '_fragment' => self::FRAGMENT,
                ]),
                'label' => __('Edit with Page Builder'),
            ]);
        }
        unset($item);

        return $result;
    }

    /**
     * The dropdown lists the actions in the array's order.
     *
     * @param mixed[] $actions
     * @param string $after
     * @param string $key
     * @param mixed[] $action
     * @return mixed[]
     */
    private function insertAfter(array $actions, string $after, string $key, array $action): array
    {
        $result = [];
        foreach ($actions as $actionKey => $value) {
            $result[$actionKey] = $value;
            if ($actionKey === $after) {
                $result[$key] = $action;
            }
        }

        return $result;
    }
}
