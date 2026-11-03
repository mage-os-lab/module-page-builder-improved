<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Attribute;

use Attribute;

/**
 * Declares a block class as a Page Builder component.
 *
 * Put it on a subclass of {@see \MageOS\PageBuilderComponents\Block\AbstractComponent} and register the class
 * on {@see \MageOS\PageBuilderComponents\Model\ComponentRegistry} in di.xml.
 *
 * @api
 */
#[Attribute(Attribute::TARGET_CLASS)]
class PageBuilderComponent
{
    /**
     * @param string $name Content type name: lowercase letters, digits and `_`, starting with a letter.
     *                     Prefix it with your vendor (`acme_hero`); it must not clash with another content type.
     * @param string $label Label in the Page Builder panel.
     * @param string $template Storefront template, `Vendor_Module::path/to/file.phtml`.
     * @param string $menuSection Page Builder panel section: `layout`, `elements`, `media` or `add_content`.
     * @param string $icon CSS class(es) of the panel icon.
     * @param int $sortOrder Position within the panel section, 0 to 127.
     * @param string[] $stylesheets Stylesheets the stage preview loads after the theme's, as view asset ids:
     *                              `Vendor_Module::css/file.css` (in `view/frontend/web`). Load them on the
     *                              storefront yourself, in layout XML.
     * @phpstan-param list<string> $stylesheets
     */
    public function __construct(
        public readonly string $name,
        public readonly string $label,
        public readonly string $template,
        public readonly string $menuSection = 'add_content',
        public readonly string $icon = 'icon-pagebuilder-block',
        public readonly int $sortOrder = 100,
        public readonly array $stylesheets = [],
    ) {
    }
}
