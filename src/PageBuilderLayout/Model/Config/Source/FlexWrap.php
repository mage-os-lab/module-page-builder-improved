<?php

declare(strict_types=1);

namespace MageOS\PageBuilderLayout\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Phrase;

/**
 * Whether side by side elements wrap onto new lines: CSS flex-wrap values.
 */
class FlexWrap implements OptionSourceInterface
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
            ['value' => 'nowrap', 'label' => __('Keep on one line')],
            ['value' => 'wrap', 'label' => __('Wrap')],
        ];
    }
}
