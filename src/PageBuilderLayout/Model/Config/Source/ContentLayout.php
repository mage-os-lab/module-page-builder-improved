<?php

declare(strict_types=1);

namespace MageOS\PageBuilderLayout\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Phrase;

/**
 * How a row or column lays out the elements in it.
 *
 * view/adminhtml/web/js/converter/style/layout.js turns each value into CSS; a JS test checks they match.
 */
class ContentLayout implements OptionSourceInterface
{
    /**
     * Values besides the default, which leaves the layout to Page Builder (stacked) or, in the Tablet and Mobile
     * views, to the Desktop view. "stack" stacks where Desktop has another layout.
     */
    public const LAYOUTS = ['stack', 'row', 'row-reverse', 'grid'];

    /**
     * Default, then each layout.
     *
     * @return array<int, array{value: string, label: Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => '', 'label' => __('Default')],
            ['value' => 'stack', 'label' => __('Stacked (flex)')],
            ['value' => 'row', 'label' => __('Side by side (flex)')],
            ['value' => 'row-reverse', 'label' => __('Side by side, reversed (flex)')],
            ['value' => 'grid', 'label' => __('Grid')],
        ];
    }
}
