<?php

declare(strict_types=1);

namespace MageOS\PageBuilderLayout\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Phrase;

/**
 * Column counts: columns per line of "Columns", and the columns of a row's or column's Grid layout.
 */
class ColumnsPerLine implements OptionSourceInterface
{
    /**
     * Column counts, smallest first.
     */
    public const COUNTS = [1, 2, 3, 4, 5, 6];

    /**
     * Default, then each count.
     *
     * @return array<int, array{value: string, label: Phrase|string}>
     */
    public function toOptionArray(): array
    {
        $options = [['value' => '', 'label' => __('Default')]];
        foreach (self::COUNTS as $count) {
            $options[] = ['value' => (string) $count, 'label' => (string) $count];
        }

        return $options;
    }
}
