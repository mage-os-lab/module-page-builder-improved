<?php

declare(strict_types=1);

namespace MageOS\PageBuilderUi\Plugin\AddEditWithPageBuilderAction;

use Magento\Framework\UrlInterface;
use Magento\PageBuilder\Model\ConfigInterface;
use MageOS\PageBuilderUi\Plugin\AddEditWithPageBuilderAction;

/**
 * "Edit with Page Builder" in the CMS pages' grid.
 *
 * A class rather than a virtual type of the plugin: Mage-OS's compiled interceptors resolve plugin types with an
 * object manager config that, in some contexts (integration tests), knows no virtual types.
 */
class CmsPage extends AddEditWithPageBuilderAction
{
    /**
     * @param UrlInterface $urlBuilder
     * @param ConfigInterface $pageBuilderConfig
     */
    public function __construct(UrlInterface $urlBuilder, ConfigInterface $pageBuilderConfig)
    {
        parent::__construct($urlBuilder, $pageBuilderConfig, 'cms/page/edit', 'page_id');
    }
}
