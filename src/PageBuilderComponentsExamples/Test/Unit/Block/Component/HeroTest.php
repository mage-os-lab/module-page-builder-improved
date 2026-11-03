<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponentsExamples\Test\Unit\Block\Component;

use MageOS\PageBuilderComponents\Model\Value\Link;
use MageOS\PageBuilderComponentsExamples\Block\Component\Hero;
use MageOS\PageBuilderComponentsExamples\Test\Unit\TemplateRendering;
use PHPUnit\Framework\TestCase;

class HeroTest extends TestCase
{
    use TemplateRendering;

    public function testRendersEverythingThatIsSet(): void
    {
        $html = $this->renderComponent(Hero::class, [
            'image' => 'https://shop.test/media/wysiwyg/hero.jpg',
            'imageAlt' => 'A "sofa"',
            'eyebrow' => 'New',
            'title' => 'Sofas & <chairs>',
            'content' => '<p>Made in Belgium</p>',
            'primaryLink' => new Link('https://shop.test/sofas.html'),
            'primaryText' => 'Shop sofas',
            'secondaryLink' => new Link('https://example.com/', true),
            'secondaryText' => 'Visit us',
        ]);

        self::assertStringContainsString('src="https://shop.test/media/wysiwyg/hero.jpg"', $html);
        self::assertStringContainsString('alt="A&#x20;&quot;sofa&quot;"', $html);
        self::assertStringContainsString('Sofas &amp; &lt;chairs&gt;', $html);
        self::assertStringContainsString('<p>Made in Belgium</p>', $html);
        self::assertMatchesRegularExpression(
            '#<a href="https://shop.test/sofas.html"\s+class="mageos-pbc__button mageos-pbc__button--primary"\s*>'
            . '\s*Shop sofas#',
            $html
        );
        self::assertMatchesRegularExpression(
            '#class="mageos-pbc__button mageos-pbc__button--secondary"\s+target="_blank" rel="noopener noreferrer">'
            . '\s*Visit us#',
            $html
        );
        // Default: text left, dark, with a shade behind it.
        self::assertStringContainsString(
            'mageos-pbc mageos-pbc-hero mageos-pbc-hero--height-medium mageos-pbc-hero--position-left'
            . ' mageos-pbc-hero--dark mageos-pbc-hero--has-image"',
            $html
        );
        self::assertStringContainsString('class="mageos-pbc-hero__overlay"', $html);
    }

    public function testAppliesTheLayoutOptions(): void
    {
        $html = $this->renderComponent(Hero::class, [
            'image' => 'https://shop.test/media/hero.jpg',
            'position' => 'center',
            'height' => 'screen',
            'textColor' => 'light',
        ]);

        self::assertStringContainsString('mageos-pbc-hero--height-screen', $html);
        self::assertStringContainsString('mageos-pbc-hero--position-center', $html);
        self::assertStringContainsString('mageos-pbc-hero--light', $html);
    }

    public function testLeavesOutTheShadeWhenTurnedOffOrWithoutAnImage(): void
    {
        self::assertStringNotContainsString('aria-hidden', $this->renderComponent(Hero::class, ['title' => 'Hi']));
        self::assertStringNotContainsString('aria-hidden', $this->renderComponent(Hero::class, [
            'image' => 'https://shop.test/media/hero.jpg',
            'overlay' => false,
        ]));
    }

    public function testSizesTheImageWhenItsSizeIsKnown(): void
    {
        $this->imageSizes['https://shop.test/media/hero.jpg'] = ['width' => 1920, 'height' => 800];

        $html = $this->renderComponent(Hero::class, ['image' => 'https://shop.test/media/hero.jpg']);
        self::assertMatchesRegularExpression('#<img [^>]*width="1920" height="800"#', $html);

        $html = $this->renderComponent(Hero::class, ['image' => 'https://cdn.test/hero.svg']);
        self::assertStringContainsString('<img ', $html);
        self::assertStringNotContainsString('width=', $html);
        self::assertStringNotContainsString('height=', $html);
    }

    public function testLoadsTheImageWithHighPriorityUnlessTurnedOff(): void
    {
        $html = $this->renderComponent(Hero::class, ['image' => 'https://shop.test/media/hero.jpg']);
        self::assertStringContainsString('fetchpriority="high"', $html);
        self::assertStringNotContainsString('loading=', $html);

        $html = $this->renderComponent(Hero::class, [
            'image' => 'https://shop.test/media/hero.jpg',
            'highPriority' => false,
        ]);
        self::assertStringNotContainsString('fetchpriority', $html);
        self::assertStringNotContainsString('loading=', $html);
        self::assertStringContainsString('decoding="async"', $html);
    }

    public function testKeepsOnlyButtonsWithALinkAndAText(): void
    {
        $hero = $this->createComponent(Hero::class, [
            'primaryText' => 'No link',
            'secondaryLink' => new Link('https://example.com/'),
            'secondaryText' => '  ',
        ]);
        self::assertSame([], $hero->getButtons());

        $hero->secondaryText = ' Go ';
        self::assertSame(['secondary'], array_keys($hero->getButtons()));
        self::assertSame('Go', $hero->getButtons()['secondary']['text']);
    }
}
