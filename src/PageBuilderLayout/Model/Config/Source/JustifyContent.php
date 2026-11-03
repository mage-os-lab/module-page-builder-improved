<?php

declare(strict_types=1);

namespace MageOS\PageBuilderLayout\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Phrase;

/**
 * How a line shares the space its items leave: CSS justify-content values.
 */
class JustifyContent implements OptionSourceInterface
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
            ['value' => 'flex-start', 'label' => __('Start')],
            ['value' => 'center', 'label' => __('Center')],
            ['value' => 'flex-end', 'label' => __('End')],
            ['value' => 'space-between', 'label' => __('Space between')],
            ['value' => 'space-around', 'label' => __('Space around')],
            ['value' => 'space-evenly', 'label' => __('Space evenly')],
        ];
    }
}
