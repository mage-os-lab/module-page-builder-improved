<?php

declare(strict_types=1);

namespace MageOS\PageBuilderUi\ViewModel;

use Magento\Framework\Serialize\Serializer\JsonHexTag;
use Magento\Framework\View\Element\Block\ArgumentInterface;

/**
 * The x-magento-init config of "Edit with Page Builder" on an edit page (edit-with-page-builder.phtml): the
 * fieldset js/edit-with-page-builder.js opens before Page Builder starts.
 */
class EditWithPageBuilder implements ArgumentInterface
{
    public const COMPONENT = 'MageOS_PageBuilderUi/js/edit-with-page-builder';

    /**
     * @param JsonHexTag $serializer
     */
    public function __construct(
        private readonly JsonHexTag $serializer,
    ) {
    }

    /**
     * The component's config, as JSON for a text/x-magento-init script.
     *
     * @param string $fieldset Full name of the fieldset holding Page Builder, e.g. cms_page_form.cms_page_form.content.
     * @return string JSON, safe in a script element.
     */
    public function getInitConfig(string $fieldset): string
    {
        return $this->serializer->serialize([
            '*' => [
                self::COMPONENT => ['fieldset' => $fieldset],
            ],
        ]);
    }
}
