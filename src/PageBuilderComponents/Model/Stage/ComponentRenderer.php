<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Model\Stage;

use Magento\Framework\App\State;
use Magento\Framework\Escaper;
use Magento\Framework\View\Design\ThemeInterface;
use Magento\Framework\View\DesignInterface;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\LayoutInterface;
use Magento\PageBuilder\Model\Stage\RendererInterface;
use MageOS\PageBuilderComponents\Model\ComponentRegistry;
use MageOS\PageBuilderComponents\Model\DirectiveValueCodec;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Renders a component for the Page Builder stage, with the values in the form.
 *
 * Core's preview controller calls it with role `mageos_component`, in the storefront area of the store being
 * edited. The request names a registered component, never a class or a template, and only that component's
 * fields are passed on. They are encoded as in a stored widget directive, so the block renders exactly what
 * the storefront will.
 *
 * The HTML is shown in an iframe with the store theme's stylesheets, then the component's own (`stylesheets` on
 * its attribute), so it looks as it does on the storefront and the admin's styles do not apply to it. A Hyvä
 * theme gets `css/styles.css`; any other theme gets Luma's `css/styles-m.css` and `css/styles-l.css`, the
 * latter from 768px wide as on the storefront (the iframe is as wide as the stage, so this follows the stage's
 * viewport switcher). A theme is Hyvä when it, or a theme it inherits from, has a code starting with `Hyva/` or
 * is one of `hyvaThemes` in di.xml.
 *
 * The iframe is sandboxed without `allow-scripts`: scripts in the component's HTML do not run on the stage.
 * `allow-same-origin` keeps its document readable from the stage, which sizes it to its content.
 */
class ComponentRenderer implements RendererInterface
{
    /**
     * Rules for the preview document, after the theme's stylesheet:
     * - transparent, so the row's or column's background shows through, as it does on the storefront (a theme
     *   colours the page body, which in the iframe sits right behind the component);
     * - no body margin, and a flow root so children's margins stay inside the body the stage measures;
     * - no scrolling: the iframe is as tall as its content, and a theme's `overflow-y: scroll` on the body
     *   (Hyvä's) would otherwise show a scrollbar in every preview and narrow it.
     */
    private const FRAME_STYLE = 'html,body{background:transparent!important;overflow:hidden!important}'
        . 'body{margin:0!important;display:flow-root!important}';

    private const HYVA_STYLESHEETS = ['css/styles.css' => ''];

    private const LUMA_STYLESHEETS = [
        'css/styles-m.css' => '',
        'css/styles-l.css' => 'screen and (min-width: 768px)',
    ];

    /**
     * @param ComponentRegistry $componentRegistry
     * @param DirectiveValueCodec $codec
     * @param LayoutInterface $layout
     * @param DesignInterface $design
     * @param Escaper $escaper
     * @param LoggerInterface $logger
     * @param State $appState
     * @param array $hyvaThemes Theme code => whether it is a Hyvä theme, for a Hyvä theme that does not inherit
     *                          from one under `Hyva/` (one added to Hyvä's own `hyvaBaseThemes`).
     * @phpstan-param array<string, bool> $hyvaThemes
     */
    public function __construct(
        private readonly ComponentRegistry $componentRegistry,
        private readonly DirectiveValueCodec $codec,
        private readonly LayoutInterface $layout,
        private readonly DesignInterface $design,
        private readonly Escaper $escaper,
        private readonly LoggerInterface $logger,
        private readonly State $appState,
        private readonly array $hyvaThemes = [],
    ) {
    }

    /**
     * Render the component named in `component`, with the field values in `values`.
     *
     * @param array $params
     * @phpstan-param array<string, mixed> $params
     * @return array{content: string, error?: string}
     */
    public function render(array $params): array
    {
        $name = \is_string($params['component'] ?? null) ? $params['component'] : '';
        $definition = $this->componentRegistry->get($name);
        if ($definition === null) {
            return ['content' => '', 'error' => (string) __('This component is not available.')];
        }

        $values = \is_array($params['values'] ?? null) ? $params['values'] : [];
        $data = [];
        foreach (array_keys($definition->fields) as $field) {
            if (isset($values[$field]) && \is_scalar($values[$field])) {
                $data[$field] = $this->codec->encode((string) $values[$field]);
            }
        }

        try {
            /** @var AbstractBlock $block */
            $block = $this->layout->createBlock($definition->blockClass, '', ['data' => $data]);
            $html = $block->toHtml();
        } catch (Throwable $e) {
            $this->logger->error($e->getMessage(), ['exception' => $e, 'component' => $name]);

            // The message can tell more about the server than an editor should see; it is in the log.
            return ['content' => '', 'error' => $this->appState->getMode() === State::MODE_DEVELOPER
                ? (string) __('The preview could not be rendered: %1', $e->getMessage())
                : (string) __('The preview could not be rendered. The error has been logged.')];
        }

        $stylesheets = $this->isHyvaTheme($this->design->getDesignTheme())
            ? self::HYVA_STYLESHEETS
            : self::LUMA_STYLESHEETS;
        foreach ($definition->stylesheets as $stylesheet) {
            $stylesheets[$stylesheet] = '';
        }
        $links = [];
        foreach ($stylesheets as $fileId => $media) {
            $links[] = [$block->getViewFileUrl($fileId), $media];
        }

        return ['content' => $this->wrapInFrame($html, $links)];
    }

    /**
     * Whether the theme, or one it inherits from, is a Hyvä theme.
     *
     * @param ThemeInterface|null $theme
     * @return bool
     */
    private function isHyvaTheme(?ThemeInterface $theme): bool
    {
        for ($depth = 0; $theme !== null && $depth < 20; $depth++) {
            $code = (string) $theme->getCode();
            if (str_starts_with($code, 'Hyva/') || !empty($this->hyvaThemes[$code])) {
                return true;
            }
            $theme = $theme->getParentTheme();
        }

        return false;
    }

    /**
     * Wrap HTML in a sandboxed iframe with stylesheets. The stage sizes it to its content.
     *
     * @param string $html
     * @param array $stylesheets [URL, media query or ''] pairs, in order.
     * @phpstan-param list<array{string, string}> $stylesheets
     * @return string
     */
    private function wrapInFrame(string $html, array $stylesheets): string
    {
        $links = '';
        foreach ($stylesheets as [$url, $media]) {
            $links .= \sprintf(
                '<link rel="stylesheet" href="%s"%s/>',
                $this->escaper->escapeUrl($url),
                // A media query from this class's constants.
                $media !== '' ? \sprintf(' media="%s"', $media) : ''
            );
        }
        $document = \sprintf(
            '<!doctype html><html><head>%s<style>%s</style></head><body>%s</body></html>',
            $links,
            self::FRAME_STYLE,
            $html
        );

        // pointer-events: none, so the mouse stays the stage's: an iframe takes every mouse event over it, which
        // stops Page Builder's dragging, hover toolbar and selection there. The preview is for looking at.
        return \sprintf(
            '<iframe data-mageos-pbc-preview="" sandbox="allow-same-origin" title="%s" srcdoc="%s"'
            . ' style="display:block;width:100%%;border:0;pointer-events:none"></iframe>',
            $this->escaper->escapeHtmlAttr((string) __('Preview')),
            $this->escaper->escapeHtmlAttr($document)
        );
    }
}
