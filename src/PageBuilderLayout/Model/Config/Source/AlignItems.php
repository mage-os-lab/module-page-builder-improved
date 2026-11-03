<?php

declare(strict_types=1);

namespace MageOS\PageBuilderLayout\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Phrase;

/**
 * How items line up across a flex line, or within grid cells: CSS align-items values.
 */
class AlignItems implements OptionSourceInterface
{
    /**
     * Default, then each value.
     *
     * @return array<int, array{value: string, label: Phrase}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => '', 'label' => __('Default')],
            ['value' => 'stretch', 'label' => __('Stretch')],
            ['value' => 'flex-start', 'label' => __('Start')],
            ['value' => 'center', 'label' => __('Center')],
            ['value' => 'flex-end', 'label' => __('End')],
            ['value' => 'baseline', 'label' => __('Baseline')],
        ];
    }
}
