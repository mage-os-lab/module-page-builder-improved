<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Test\Unit\Model;

use Magento\Cms\Model\Template\Filter;
use Magento\Cms\Model\Template\FilterProvider;
use Magento\Framework\Filter\Template;
use Magento\Framework\Filter\Template\Tokenizer\Parameter;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use MageOS\PageBuilderComponents\Model\ComponentDefinition;
use MageOS\PageBuilderComponents\Model\DefinitionReader;
use MageOS\PageBuilderComponents\Model\DirectiveValueCodec;
use MageOS\PageBuilderComponents\Model\LinkResolver;
use MageOS\PageBuilderComponents\Model\Value\Link;
use MageOS\PageBuilderComponents\Model\ValueHydrator;
use MageOS\PageBuilderComponents\Test\Unit\Fixture\AllFieldsComponent;
use PHPUnit\Framework\TestCase;

class ValueHydratorTest extends TestCase
{
    /**
     * @var ValueHydrator
     */
    private ValueHydrator $hydrator;

    /**
     * @var ComponentDefinition
     */
    private ComponentDefinition $definition;

    /**
     * @var DirectiveValueCodec
     */
    private DirectiveValueCodec $codec;

    protected function setUp(): void
    {
        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn(3);
        $store->method('getBaseUrl')->with(UrlInterface::URL_TYPE_MEDIA)->willReturn('https://shop.test/media/');
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $filter = $this->createMock(Filter::class);
        $filter->method('setStoreId')->with(3)->willReturnSelf();
        $filter->method('filter')->willReturnCallback(static fn (string $html): string => "[filtered]$html");
        $filterProvider = $this->createMock(FilterProvider::class);
        $filterProvider->method('getBlockFilter')->willReturn($filter);

        $linkResolver = $this->createMock(LinkResolver::class);
        $linkResolver->method('resolve')->willReturnCallback(
            static fn (string $stored): ?Link => $stored === '' ? null : new Link('/resolved/' . $stored, true)
        );

        $this->codec = new DirectiveValueCodec();
        $this->hydrator = new ValueHydrator($this->codec, $linkResolver, $storeManager, $filterProvider);
        $this->definition = (new DefinitionReader())->read('test_all_fields', AllFieldsComponent::class);
    }

    public function testDecodesAndConvertsEachField(): void
    {
        $values = $this->hydrate([
            'title' => 'Tom & "Jerry"',
            'count' => '12',
            'intro' => "Line\n<two>",
            'content' => '<p>{{media url="a.jpg"}}</p>',
            'image' => 'wysiwyg/hero.jpg',
            'link' => 'L',
            'plainLink' => 'P',
            'layout' => 'right',
            'overlay' => '0',
            'products' => '{"1":{"type":"Combine"}}',
        ]);

        self::assertSame('Tom & "Jerry"', $values['title']);
        self::assertSame(12, $values['count']);
        self::assertSame("Line\n<two>", $values['intro']);
        self::assertSame('[filtered]<p>{{media url="a.jpg"}}</p>', $values['content']);
        self::assertSame('https://shop.test/media/wysiwyg/hero.jpg', $values['image']);
        self::assertEquals(new Link('/resolved/L', true), $values['link']);
        self::assertSame('/resolved/P', $values['plainLink']);
        self::assertSame('right', $values['layout']);
        self::assertFalse($values['overlay']);
        self::assertSame('{"1":{"type":"Combine"}}', $values['products']);
    }

    /**
     * The directive in the fixture is what the stage's mass converter writes (Test/Js/widget-directive.test.js
     * checks that). Parsed the way core's widget filter parses it, it must hydrate to what was typed.
     */
    public function testHydratesTheDirectiveTheStageWrites(): void
    {
        $directive = trim((string) file_get_contents(__DIR__ . '/../_files/stage-directive.txt'));
        self::assertSame(1, preg_match(Template::CONSTRUCTION_PATTERN, $directive, $construction));
        self::assertSame('widget', $construction[1]);
        $tokenizer = new Parameter();
        $tokenizer->setString($construction[2]);
        $parameters = $tokenizer->tokenize();

        self::assertSame(AllFieldsComponent::class, $parameters['type']);

        $values = $this->hydrator->hydrate($this->definition, $parameters);

        self::assertSame('Tom & "Jerry" {{x}} 100%', $values['title']);
        self::assertSame('https://shop.test/media/wysiwyg/hero.jpg', $values['image']);
        self::assertEquals(
            new Link('/resolved/{"type":"product","setting":true,"product":"42"}', true),
            $values['link']
        );
        self::assertFalse($values['overlay']);
        self::assertSame('[filtered]<p class="intro">{{media url="wysiwyg/a.jpg"}}</p>', $values['content']);
    }

    public function testLeavesMissingFieldsAndPropertiesWithoutAFieldOut(): void
    {
        self::assertSame(['title' => 'Only'], $this->hydrate(['title' => 'Only', 'notAField' => 'x', 'type' => 'y']));
    }

    public function testValuesThatDoNotFitTheirPropertyKeepTheDefault(): void
    {
        $values = $this->hydrate(['count' => 'many', 'overlay' => 'maybe', 'layout' => 'centre', 'title' => ['x']]);

        self::assertSame([], $values);
    }

    public function testEmptyValuesStayEmptyAndAnEmptyLinkIsNull(): void
    {
        $values = $this->hydrate(['title' => '', 'content' => '', 'image' => '', 'link' => '', 'plainLink' => '']);

        self::assertSame(['title' => '', 'content' => '', 'image' => '', 'link' => null, 'plainLink' => ''], $values);
    }

    public function testKeepsAFullImageUrlOrAbsolutePath(): void
    {
        self::assertSame('https://cdn.test/a.png', $this->hydrate(['image' => 'https://cdn.test/a.png'])['image']);
        self::assertSame('/media/a.png', $this->hydrate(['image' => '/media/a.png'])['image']);
        self::assertSame('HTTP://cdn.test/a.png', $this->hydrate(['image' => 'HTTP://cdn.test/a.png'])['image']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsafeImages(): array
    {
        return [
            'javascript' => ['javascript:alert(1)'],
            'javascript, mixed case and leading whitespace' => ['  JaVaScRiPt:alert(1)'],
            'data' => ['data:image/svg+xml;base64,PHN2Zz48L3N2Zz4='],
            'vbscript' => ['vbscript:msgbox(1)'],
            'protocol-relative' => ['//evil.test/a.png'],
            'protocol-relative with backslashes' => ['\\\\evil.test/a.png'],
            'slash and backslash' => ['/\\evil.test/a.png'],
            'http without a host' => ['http:///a.png'],
            'control character' => ["wysiwyg/a\x01.png"],
        ];
    }

    /**
     * @dataProvider unsafeImages
     */
    public function testRefusesAnImageUrlWithAnotherSchemeOrHost(string $stored): void
    {
        self::assertSame('', $this->hydrate(['image' => $stored])['image']);
    }

    /**
     * @param array<string, mixed> $decoded Values as typed; they are encoded as in a stored directive.
     * @return array<string, mixed>
     */
    private function hydrate(array $decoded): array
    {
        $data = array_map(
            fn ($value) => \is_string($value) ? $this->codec->encode($value) : $value,
            $decoded
        );

        return $this->hydrator->hydrate($this->definition, $data);
    }
}
