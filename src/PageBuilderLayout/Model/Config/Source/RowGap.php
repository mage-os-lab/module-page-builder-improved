<?php

declare(strict_types=1);

namespace MageOS\PageBuilderLayout\Model\Config\Source;

use Magento\Framework\Phrase;

/**
 * Row gaps of "Columns": without one, wrapped columns and column lines are as far apart as the columns of a line.
 */
class RowGap extends Gap
{
    /**
     * @inheritDoc
     */
    protected function getDefaultLabel(): Phrase
    {
        return __('Same as Column Gap');
    }
}
