<?php

declare(strict_types=1);

namespace MageOS\PageBuilderLayout\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Phrase;

/**
 * Gaps a layout setting can take: column and row gap of "Columns", gap between a row's or column's elements.
 *
 * Values are CSS lengths, written as they are. "Default" leaves the gap out, so the Tablet and Mobile views keep
 * the Desktop one; "0 px" removes a gap set for Desktop.
 */
class Gap implements OptionSourceInterface
{
    /**
     * Gaps in pixels, smallest first.
     */
    public const GAPS = [0, 8, 16, 24, 32, 48, 64];

    /**
     * Default, then each gap.
     *
     * @return array<int, array{value: string, label: Phrase}>
     */
    public function toOptionArray(): array
    {
        $options = [['value' => '', 'label' => $this->getDefaultLabel()]];
        foreach (self::GAPS as $gap) {
            $options[] = ['value' => $gap . 'px', 'label' => __('%1 px', $gap)];
        }

        return $options;
    }

    /**
     * Label of the empty value.
     *
     * @return Phrase
     */
    protected function getDefaultLabel(): Phrase
    {
        return __('Default');
    }
}
