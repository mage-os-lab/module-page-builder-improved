<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Model\Value;

/**
 * A resolved {@see \MageOS\PageBuilderComponents\Field\UrlField} value.
 *
 * @api
 */
class Link
{
    /**
     * @param string $url
     * @param bool $openInNewTab
     */
    public function __construct(
        public readonly string $url,
        public readonly bool $openInNewTab = false,
    ) {
    }

    /**
     * `target` and `rel` attributes for the anchor, or an empty string.
     *
     * @return string
     */
    public function getTargetAttributes(): string
    {
        return $this->openInNewTab ? 'target="_blank" rel="noopener noreferrer"' : '';
    }
}
