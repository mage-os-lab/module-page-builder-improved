<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponentsExamples\Test\Unit\Block\Component;

use MageOS\PageBuilderComponents\Model\Value\Link;
use MageOS\PageBuilderComponentsExamples\Block\Component\CallToAction;
use MageOS\PageBuilderComponentsExamples\Block\Component\ImageText;
use MageOS\PageBuilderComponentsExamples\Test\Unit\TemplateRendering;
use PHPUnit\Framework\TestCase;

class ImageTextAndCallToActionTest extends TestCase
{
    use TemplateRendering;

    public function testImageTextRendersTheImageOnTheChosenSide(): void
    {
        $values = [
            'image' => 'https://shop.test/media/a.jpg',
            'imageAlt' => 'Workshop',
            'title' => 'Our story',
            'content' => '<p>Since 1950</p>',
            'primaryLink' => new Link('https://shop.test/about'),
            'primaryText' => 'About us',
        ];

        $left = $this->renderComponent(ImageText::class, $values);
        self::assertStringContainsString('alt="Workshop"', $left);
        self::assertStringContainsString('<p>Since 1950</p>', $left);
        self::assertMatchesRegularExpression('#href="https://shop.test/about"[^>]*>\s*About us#', $left);
        self::assertStringNotContainsString('mageos-pbc-image-text--image-right', $left);

        $right = $this->renderComponent(ImageText::class, ['imagePosition' => 'right'] + $values);
        self::assertStringContainsString('mageos-pbc-image-text--image-right', $right);
    }

    public function testImageTextLoadsTheImageLazilyWithItsSize(): void
    {
        $this->imageSizes['https://shop.test/media/a.jpg'] = ['width' => 800, 'height' => 600];

        $html = $this->renderComponent(ImageText::class, ['image' => 'https://shop.test/media/a.jpg']);
        self::assertMatchesRegularExpression(
            '#<img [^>]*width="800" height="600"\s+loading="lazy"\s+decoding="async">#',
            $html
        );

        $html = $this->renderComponent(ImageText::class, ['image' => 'https://shop.test/media/unknown.jpg']);
        self::assertStringNotContainsString('width=', $html);
        self::assertStringContainsString('loading="lazy"', $html);
    }

    public function testImageTextUsesTheFullWidthWithoutAnImage(): void
    {
        $html = $this->renderComponent(ImageText::class, ['title' => 'Only text']);

        self::assertStringContainsString('mageos-pbc-image-text--no-image', $html);
        self::assertStringNotContainsString('<img', $html);
    }

    public function testCallToActionAppliesItsStyleAndAlignment(): void
    {
        $html = $this->renderComponent(CallToAction::class, [
            'title' => 'Visit our showroom',
            'primaryLink' => new Link('https://shop.test/showroom'),
            'primaryText' => 'Plan a visit',
            'style' => 'dark',
            'alignment' => 'split',
        ]);

        self::assertStringContainsString('mageos-pbc-call-to-action--dark', $html);
        self::assertStringContainsString('mageos-pbc-call-to-action--split', $html);
        self::assertMatchesRegularExpression(
            '#class="mageos-pbc__button mageos-pbc__button--primary"\s*>\s*Plan a visit#',
            $html
        );

        $centered = $this->renderComponent(CallToAction::class, ['title' => 'Hi']);
        self::assertStringContainsString('mageos-pbc-call-to-action--light', $centered);
        self::assertStringContainsString('mageos-pbc-call-to-action--center', $centered);
    }

    public function testCallToActionOnThePrimaryColorKeepsItsButtonsVisible(): void
    {
        $html = $this->renderComponent(CallToAction::class, ['title' => 'Join', 'style' => 'primary']);
        $css = (string) file_get_contents(\dirname(__DIR__, 4) . '/view/frontend/web/css/components.css');

        self::assertStringContainsString('mageos-pbc-call-to-action--primary', $html);
        // The primary button turns white on the primary color, and the secondary one outlined white.
        self::assertMatchesRegularExpression(
            '#\.mageos-pbc-call-to-action--primary \.mageos-pbc__button--primary \{[^}]*'
            . 'background: var\(--mageos-pbc-on-primary#',
            $css
        );
        self::assertMatchesRegularExpression(
            '#\.mageos-pbc-call-to-action--primary \.mageos-pbc__button--secondary,[^{]*\{[^}]*border-color: \#fff#',
            $css
        );
    }
}
