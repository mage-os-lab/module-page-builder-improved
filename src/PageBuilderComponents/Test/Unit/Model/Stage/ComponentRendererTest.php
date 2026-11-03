<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Test\Unit\Model\Stage;

use Magento\Framework\App\State;
use Magento\Framework\Escaper;
use Magento\Framework\Translate\InlineInterface;
use Magento\Framework\View\Design\ThemeInterface;
use Magento\Framework\View\DesignInterface;
use Magento\Framework\View\Element\AbstractBlock;
use Magento\Framework\View\LayoutInterface;
use Magento\Framework\ZendEscaper;
use MageOS\PageBuilderComponents\Model\DirectiveValueCodec;
use MageOS\PageBuilderComponents\Model\Stage\ComponentRenderer;
use MageOS\PageBuilderComponents\Test\Unit\Fixture\MinimalComponent;
use MageOS\PageBuilderComponents\Test\Unit\RegistryFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

class ComponentRendererTest extends TestCase
{
    /**
     * @var LayoutInterface&MockObject
     */
    private LayoutInterface $layout;

    /**
     * @var DesignInterface&MockObject
     */
    private DesignInterface $design;

    /**
     * @var State&MockObject
     */
    private State $appState;

    /**
     * @var AbstractBlock&MockObject
     */
    private AbstractBlock $block;

    /**
     * @var Escaper
     */
    private Escaper $escaper;

    /**
     * @var ComponentRenderer
     */
    private ComponentRenderer $renderer;

    protected function setUp(): void
    {
        $this->layout = $this->createMock(LayoutInterface::class);
        $this->design = $this->createMock(DesignInterface::class);
        $this->design->method('getDesignTheme')
            ->willReturn($this->theme('Magento/luma', $this->theme('Magento/blank')));
        $this->appState = $this->createMock(State::class);
        $this->block = $this->createMock(AbstractBlock::class);
        $this->block->method('getViewFileUrl')->willReturnCallback(
            static fn (string $fileId): string => 'https://shop.test/static/' . str_replace('::', '/', $fileId)
        );
        // Escaper fetches these from the object manager on first use.
        $escaper = new Escaper();
        $lazyDependencies = [
            'translateInline' => $this->createMock(InlineInterface::class),
            'escaper' => new ZendEscaper(),
        ];
        foreach ($lazyDependencies as $property => $value) {
            $reflection = new \ReflectionProperty(Escaper::class, $property);
            $reflection->setValue($escaper, $value);
        }
        $this->escaper = $escaper;
        $this->renderer = $this->createRenderer();
    }

    public function testRendersTheNamedComponentWithItsOwnFieldsEncodedAsStored(): void
    {
        $this->layout->expects(self::once())
            ->method('createBlock')
            ->with(MinimalComponent::class, '', ['data' => ['heading' => 'A ^1quote^1']])
            ->willReturn($this->block);
        $this->block->method('toHtml')->willReturn('<h2>A "quote"</h2>');

        $result = $this->renderer->render([
            'role' => 'mageos_component',
            'component' => 'test_minimal',
            'values' => ['heading' => 'A "quote"', 'template' => 'Magento_Customer::evil.phtml', 'other' => 'x'],
            // What the previous module trusted: neither may choose what is rendered.
            'block_class' => 'Magento\\Framework\\View\\Element\\Template',
            'template' => 'Magento_Customer::evil.phtml',
        ]);

        self::assertSame(['content'], array_keys($result));
        self::assertStringContainsString('<body><h2>A "quote"</h2></body>', $this->frameDocument($result['content']));
    }

    public function testRefusesAnUnknownComponent(): void
    {
        $this->layout->expects(self::never())->method('createBlock');

        $result = $this->renderer->render(['component' => 'Magento\\Framework\\View\\Element\\Template']);

        self::assertSame('', $result['content']);
        self::assertArrayHasKey('error', $result);
    }

    public function testReportsARenderingErrorInDeveloperMode(): void
    {
        $this->appState->method('getMode')->willReturn(State::MODE_DEVELOPER);
        $this->layout->method('createBlock')->willReturn($this->block);
        $this->block->method('toHtml')->willThrowException(new RuntimeException('Template missing'));

        $result = $this->renderer->render(['component' => 'test_minimal']);

        self::assertSame('', $result['content']);
        self::assertStringContainsString('Template missing', $result['error'] ?? '');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function nonDeveloperModes(): array
    {
        return ['default' => [State::MODE_DEFAULT], 'production' => [State::MODE_PRODUCTION]];
    }

    /**
     * @dataProvider nonDeveloperModes
     */
    public function testHidesTheErrorMessageOutsideDeveloperModeAndLogsIt(string $mode): void
    {
        $this->appState->method('getMode')->willReturn($mode);
        $this->layout->method('createBlock')->willReturn($this->block);
        $exception = new RuntimeException('SQLSTATE[42S02] secret_table');
        $this->block->method('toHtml')->willThrowException($exception);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with('SQLSTATE[42S02] secret_table', ['exception' => $exception, 'component' => 'test_minimal']);

        $result = $this->createRenderer(logger: $logger)->render(['component' => 'test_minimal']);

        self::assertSame('', $result['content']);
        self::assertStringNotContainsString('secret_table', $result['error'] ?? '');
        self::assertStringContainsString('could not be rendered', $result['error'] ?? '');
    }

    public function testWrapsTheHtmlInASandboxedFrameWithoutScripts(): void
    {
        $this->layout->method('createBlock')->willReturn($this->block);
        $this->block->method('toHtml')->willReturn('<p class="x">Hi & bye</p><script>alert(1)</script>');

        $content = $this->renderer->render(['component' => 'test_minimal'])['content'];

        self::assertStringStartsWith('<iframe data-mageos-pbc-preview=""', $content);
        // No allow-scripts: a script in the component's HTML must not run on the stage.
        self::assertMatchesRegularExpression('/^<iframe [^>]*sandbox="allow-same-origin"/', $content);
        // The mouse must stay the stage's, or dragging, the hover toolbar and selection stop over a preview.
        self::assertMatchesRegularExpression('/<iframe [^>]*style="[^"]*pointer-events:none/', $content);
        // The component's HTML is only in the srcdoc attribute, escaped.
        self::assertStringNotContainsString('<script', $content);
        self::assertSame(1, substr_count($content, '<iframe'));
        self::assertStringContainsString(
            '<body><p class="x">Hi & bye</p><script>alert(1)</script></body>',
            $this->frameDocument($content)
        );
    }

    public function testLoadsLumasStylesheetsForAThemeThatIsNotHyva(): void
    {
        $this->layout->method('createBlock')->willReturn($this->block);
        $this->block->method('toHtml')->willReturn('<p>Hi</p>');

        $document = $this->frameDocument($this->renderer->render(['component' => 'test_minimal'])['content']);

        self::assertSame([
            '<link rel="stylesheet" href="https://shop.test/static/css/styles-m.css"/>',
            '<link rel="stylesheet" href="https://shop.test/static/css/styles-l.css"'
                . ' media="screen and (min-width: 768px)"/>',
        ], $this->links($document));
        $this->assertFrameStyleAfterTheStylesheets($document);
    }

    /**
     * @return array<string, array{ThemeInterface, array<string, bool>}>
     */
    public function hyvaThemes(): array
    {
        return [
            'Hyvä default' => [$this->theme('Hyva/default'), []],
            'child of a Hyvä theme' => [$this->theme('Acme/shop', $this->theme('Hyva/default-csp')), []],
            'Hyvä theme added in di.xml' => [$this->theme('belfurn/hyva-default'), ['belfurn/hyva-default' => true]],
        ];
    }

    /**
     * @dataProvider hyvaThemes
     * @param ThemeInterface $theme
     * @param array<string, bool> $hyvaThemes
     */
    public function testLoadsTheHyvaStylesheetThenTheComponentsOwnForAHyvaTheme(
        ThemeInterface $theme,
        array $hyvaThemes
    ): void {
        $design = $this->createMock(DesignInterface::class);
        $design->method('getDesignTheme')->willReturn($theme);
        $this->layout->method('createBlock')->willReturn($this->block);
        $this->block->method('toHtml')->willReturn('<p>Hi</p>');

        $content = $this->createRenderer($design, hyvaThemes: $hyvaThemes)
            ->render(['component' => 'test_all_fields'])['content'];
        $document = $this->frameDocument($content);

        self::assertSame([
            '<link rel="stylesheet" href="https://shop.test/static/css/styles.css"/>',
            '<link rel="stylesheet"'
                . ' href="https://shop.test/static/MageOS_PageBuilderComponents/css/test/all-fields.css"/>',
            '<link rel="stylesheet" href="https://shop.test/static/Vendor_Module/css/other.min.css"/>',
        ], $this->links($document));
        $this->assertFrameStyleAfterTheStylesheets($document);
    }

    public function testAThemeNamedLikeHyvaWithoutItsPrefixIsNotHyva(): void
    {
        $design = $this->createMock(DesignInterface::class);
        $design->method('getDesignTheme')->willReturn($this->theme('Acme/hyva', $this->theme('Magento/luma')));
        $this->layout->method('createBlock')->willReturn($this->block);
        $this->block->method('toHtml')->willReturn('<p>Hi</p>');

        $document = $this->frameDocument(
            $this->createRenderer($design, hyvaThemes: ['Acme/hyva' => false])
                ->render(['component' => 'test_minimal'])['content']
        );

        self::assertStringContainsString('css/styles-m.css', $document);
        self::assertStringNotContainsString('css/styles.css', $document);
    }

    /**
     * After the theme's stylesheets, so its rules win: a transparent document, so the row's background shows
     * through; no body margin and a flow root, as the stage measures the body.
     *
     * @param string $document
     * @return void
     */
    private function assertFrameStyleAfterTheStylesheets(string $document): void
    {
        $style = substr($document, (int) strpos($document, '<style>'));
        self::assertGreaterThan(strrpos($document, '<link'), strpos($document, '<style>'));
        self::assertStringContainsString(
            'html,body{background:transparent!important;overflow:hidden!important}',
            $style
        );
        self::assertStringContainsString('body{margin:0!important;display:flow-root!important}', $style);
    }

    /**
     * The document in the iframe's srcdoc.
     *
     * @param string $content
     * @return string
     */
    private function frameDocument(string $content): string
    {
        self::assertSame(1, preg_match('/srcdoc="([^"]*)"/', $content, $match));

        return html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5);
    }

    /**
     * @param string $document
     * @return list<string>
     */
    private function links(string $document): array
    {
        preg_match_all('/<link [^>]*>/', $document, $matches);

        return $matches[0];
    }

    /**
     * @param string $code
     * @param ThemeInterface|null $parent
     * @return ThemeInterface
     */
    private function theme(string $code, ?ThemeInterface $parent = null): ThemeInterface
    {
        $theme = $this->createMock(ThemeInterface::class);
        $theme->method('getCode')->willReturn($code);
        $theme->method('getParentTheme')->willReturn($parent);

        return $theme;
    }

    /**
     * @param DesignInterface|null $design
     * @param LoggerInterface|null $logger
     * @param array<string, bool> $hyvaThemes
     * @return ComponentRenderer
     */
    private function createRenderer(
        ?DesignInterface $design = null,
        ?LoggerInterface $logger = null,
        array $hyvaThemes = []
    ): ComponentRenderer {
        return new ComponentRenderer(
            RegistryFactory::create(),
            new DirectiveValueCodec(),
            $this->layout,
            $design ?? $this->design,
            $this->escaper,
            $logger ?? $this->createMock(LoggerInterface::class),
            $this->appState,
            $hyvaThemes
        );
    }
}
