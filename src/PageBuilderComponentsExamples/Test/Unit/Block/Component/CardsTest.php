<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponentsExamples\Test\Unit\Block\Component;

use MageOS\PageBuilderComponents\Model\Value\Link;
use MageOS\PageBuilderComponentsExamples\Block\Component\Cards;
use MageOS\PageBuilderComponentsExamples\Test\Unit\TemplateRendering;
use PHPUnit\Framework\TestCase;

class CardsTest extends TestCase
{
    use TemplateRendering;

    public function testKeepsTheCardsWithContentInOrder(): void
    {
        $cards = $this->createComponent(Cards::class, [
            'card1Title' => 'First',
            'card2LinkText' => 'Only a link text',
            'card2Link' => new Link('https://shop.test/'),
            'card3Image' => 'https://shop.test/media/c.jpg',
            'card4Text' => " Fourth\n",
        ]);

        self::assertSame(
            [['First', ''], ['', ''], ['', 'Fourth']],
            array_map(static fn (array $card): array => [$card['title'], $card['text']], $cards->getCards())
        );
    }

    public function testDropsALinkWithNothingToReadItBy(): void
    {
        $cards = $this->createComponent(Cards::class, [
            'card1Image' => 'https://shop.test/media/a.jpg',
            'card1Link' => new Link('https://shop.test/a.html'),
            'card2Title' => 'Titled',
            'card2Link' => new Link('https://shop.test/b.html'),
            'card3Title' => 'Empty link',
            'card3Link' => new Link(''),
            'card3LinkText' => 'Read more',
        ]);

        self::assertSame(
            [null, 'https://shop.test/b.html', null],
            array_map(static fn (array $card): ?string => $card['link']?->url, $cards->getCards())
        );
        self::assertSame('', $cards->getCards()[2]['linkText']);
    }

    public function testUsesNoMoreColumnsThanCards(): void
    {
        $cards = $this->createComponent(Cards::class, ['columns' => 4, 'card1Title' => 'A', 'card2Title' => 'B']);
        self::assertSame(2, $cards->getColumnCount());

        $cards = $this->createComponent(Cards::class, [
            'columns' => 2,
            'card1Title' => 'A',
            'card2Title' => 'B',
            'card3Title' => 'C',
        ]);
        self::assertSame(2, $cards->getColumnCount());
    }

    public function testWorksOutTheCardsOncePerHydration(): void
    {
        $cards = $this->createComponent(Cards::class, ['card1Title' => 'A']);
        self::assertCount(1, $cards->getCards());

        $cards->card2Title = 'B';
        self::assertCount(1, $cards->getCards(), 'Kept until the next hydrate()');
        self::assertSame(1, $cards->getColumnCount());

        $cards->hydrate();
        self::assertCount(2, $cards->getCards());
        self::assertSame(2, $cards->getColumnCount());
    }

    public function testGivesEachCardImageItsAltText(): void
    {
        $html = $this->renderComponent(Cards::class, [
            'card1Image' => 'https://shop.test/media/a.jpg',
            'card1ImageAlt' => ' Oak "table" ',
            'card1Title' => 'Tables',
            'card2Image' => 'https://shop.test/media/b.jpg',
            'card2Title' => 'Chairs',
            'card3Title' => 'No image',
            'card3ImageAlt' => 'Unused',
        ]);

        self::assertStringContainsString('alt="Oak&#x20;&quot;table&quot;"', $html);
        // Without an alt text, the image is decorative: the card's title says what it is.
        self::assertStringContainsString('alt=""', $html);
        self::assertSame(2, substr_count($html, ' alt="'));
    }

    public function testKeepsTheFieldsInFormOrder(): void
    {
        $sortOrders = [];
        foreach ((new \ReflectionClass(Cards::class))->getProperties() as $property) {
            foreach ($property->getAttributes() as $attribute) {
                $sortOrders[$property->getName()] = $attribute->getArguments()['sortOrder'];
            }
        }
        asort($sortOrders);

        self::assertSame(
            ['card1Image', 'card1ImageAlt', 'card1Title', 'card1Text', 'card1Link', 'card1LinkText', 'card2Image'],
            \array_slice(array_keys($sortOrders), 3, 7)
        );
    }

    public function testRendersTheCards(): void
    {
        $html = $this->renderComponent(Cards::class, [
            'title' => 'Our <range>',
            'intro' => "Line 1\nLine 2",
            'card1Image' => 'https://shop.test/media/a.jpg',
            'card1Title' => 'Whole card',
            'card1Link' => new Link('https://shop.test/a.html', true),
            'card2Title' => 'With a link text',
            'card2Text' => 'Text & more',
            'card2Link' => new Link('https://shop.test/b.html'),
            'card2LinkText' => 'Read more',
        ]);

        self::assertStringContainsString('Our &lt;range&gt;', $html);
        self::assertStringContainsString("Line 1<br />\nLine 2", $html);
        self::assertStringContainsString('mageos-pbc-cards__list--2"', $html);
        self::assertSame(2, substr_count($html, '<li '));
        self::assertMatchesRegularExpression(
            '#<a href="https://shop.test/a.html"\s+class="mageos-pbc-cards__cover-link"\s+target="_blank"#',
            $html
        );
        self::assertMatchesRegularExpression('#<a href="https://shop.test/b.html"[^>]*>\s*Read more\s*</a>#', $html);
        self::assertStringContainsString('Text &amp; more', $html);
        self::assertStringContainsString('loading="lazy"', $html);
    }

    public function testLoadsTheImagesLazilyWithTheirSize(): void
    {
        $this->imageSizes['https://shop.test/media/a.jpg'] = ['width' => 640, 'height' => 480];

        $html = $this->renderComponent(Cards::class, [
            'card1Image' => 'https://shop.test/media/a.jpg',
            'card2Image' => 'https://cdn.test/b.jpg',
        ]);

        self::assertSame(2, substr_count($html, '<img '));
        self::assertSame(2, substr_count($html, 'loading="lazy"'));
        self::assertSame(2, substr_count($html, 'decoding="async"'));
        self::assertSame(1, substr_count($html, 'width='));
        self::assertMatchesRegularExpression('#<img [^>]*width="640"\s+height="480"#', $html);
    }

    public function testRendersNothingWhenEmpty(): void
    {
        self::assertSame('', trim($this->renderComponent(Cards::class)));
    }
}
