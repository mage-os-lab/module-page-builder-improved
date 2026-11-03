<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Test\Unit\Model;

use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;
use MageOS\PageBuilderComponents\Model\LinkResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class LinkResolverTest extends TestCase
{
    /**
     * @var UrlFinderInterface&MockObject
     */
    private UrlFinderInterface $urlFinder;

    /**
     * @var LinkResolver
     */
    private LinkResolver $resolver;

    protected function setUp(): void
    {
        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn(2);
        $store->method('getUrl')->willReturnCallback(
            static fn (string $route, array $params): string => isset($params['_direct'])
                ? 'https://shop.test/' . $params['_direct']
                : 'https://shop.test/' . $route . '/' . http_build_query($params)
        );
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);
        $this->urlFinder = $this->createMock(UrlFinderInterface::class);
        $this->resolver = new LinkResolver($this->urlFinder, $storeManager);
    }

    public function testResolvesAUrlAndTheNewTabSetting(): void
    {
        $link = $this->resolver->resolve('{"type":"default","default":"/sale.html","setting":true}');

        self::assertNotNull($link);
        self::assertSame('/sale.html', $link->url);
        self::assertTrue($link->openInNewTab);
        self::assertSame('target="_blank" rel="noopener noreferrer"', $link->getTargetAttributes());
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function entities(): array
    {
        return [
            'product' => ['product', 'product', 'catalog/product/view/id=42'],
            'category' => ['category', 'category', 'catalog/category/view/id=42'],
            'cms page' => ['page', 'cms-page', 'cms/page/view/page_id=42'],
        ];
    }

    /**
     * @dataProvider entities
     */
    public function testResolvesAnEntityThroughItsUrlRewrite(string $type, string $entityType, string $fallback): void
    {
        $this->urlFinder->expects(self::once())->method('findAllByData')->with([
            UrlRewrite::ENTITY_ID => 42,
            UrlRewrite::ENTITY_TYPE => $entityType,
            UrlRewrite::STORE_ID => 2,
            UrlRewrite::REDIRECT_TYPE => 0,
        ])->willReturn([$this->rewrite('the-entity.html')]);

        $link = $this->resolver->resolve(json_encode(['type' => $type, $type => '42', 'setting' => false]) ?: '');

        self::assertNotNull($link);
        self::assertSame('https://shop.test/the-entity.html', $link->url);
        self::assertFalse($link->openInNewTab);
    }

    public function testTakesTheCanonicalRewriteNotARedirectOrACategoryPath(): void
    {
        $this->urlFinder->method('findAllByData')->willReturn([
            $this->rewrite('old-key.html', 301),
            $this->rewrite('shoes/the-product.html', 0, ['category_id' => '5']),
            $this->rewrite('the-product.html'),
        ]);

        self::assertSame(
            'https://shop.test/the-product.html',
            $this->resolver->resolve('{"type":"product","product":"42"}')?->url
        );
    }

    /**
     * @dataProvider entities
     */
    public function testFallsBackToTheEntityRouteWithoutARewrite(
        string $type,
        string $entityType,
        string $fallback
    ): void {
        $this->urlFinder->method('findAllByData')->willReturn([$this->rewrite('redirect.html', 302)]);

        self::assertSame(
            'https://shop.test/' . $fallback,
            $this->resolver->resolve(json_encode(['type' => $type, $type => '42']) ?: '')?->url
        );
    }

    public function testLooksUpEachEntityOncePerRequest(): void
    {
        $this->urlFinder->expects(self::exactly(2))
            ->method('findAllByData')
            ->willReturnCallback(fn (array $data): array => [
                $this->rewrite($data[UrlRewrite::ENTITY_TYPE] . '-' . $data[UrlRewrite::ENTITY_ID] . '.html'),
            ]);

        $urls = array_map(
            fn (string $stored): ?string => $this->resolver->resolve($stored)?->url,
            [
                '{"type":"product","product":"42"}',
                '{"type":"product","product":"42","setting":true}',
                '{"type":"category","category":"42"}',
                '{"type":"product","product":"42"}',
            ]
        );

        self::assertSame([
            'https://shop.test/product-42.html',
            'https://shop.test/product-42.html',
            'https://shop.test/category-42.html',
            'https://shop.test/product-42.html',
        ], $urls);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function noLink(): array
    {
        return [
            'empty' => [''],
            'blank url' => ['{"type":"default","default":"  "}'],
            'type without value' => ['{"type":"product"}'],
            'non-numeric id' => ['{"type":"product","product":"1 OR 1=1"}'],
            'unknown type' => ['{"type":"javascript","javascript":"1"}'],
            'javascript' => ['javascript:alert(1)'],
            'javascript, mixed case and leading whitespace' => ["  JaVaScRiPt:alert(1)"],
            'javascript with a tab inside' => ["java\tscript:alert(1)"],
            'javascript with a line break inside' => ["java\nscript:alert(1)"],
            'javascript after control characters' => ["\x01\x1fjavascript:alert(1)"],
            'data' => ['data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg=='],
            'vbscript' => ['VBScript:msgbox(1)'],
            'file' => ['file:///etc/passwd'],
            'protocol-relative' => ['//evil.test/'],
            'protocol-relative with backslashes' => ['\\\\evil.test/'],
            'protocol-relative, slash and backslash' => ['/\\evil.test/'],
            'default type, javascript' => ['{"type":"default","default":" JavaScript:alert(1)"}'],
            'default type, data' => ['{"type":"default","default":"data:text/html,<script>alert(1)</script>"}'],
            'default type, protocol-relative' => ['{"type":"default","default":"//evil.test/"}'],
            'no type, javascript' => ['{"default":"javascript:alert(1)"}'],
        ];
    }

    /**
     * @dataProvider noLink
     */
    public function testReturnsNullWhenThereIsNoLink(string $stored): void
    {
        $this->urlFinder->expects(self::never())->method('findAllByData');

        self::assertNull($this->resolver->resolve($stored));
    }

    public function testTakesAPlainUrlTypedIntoTheWidgetDialog(): void
    {
        self::assertSame('https://example.com/', $this->resolver->resolve(' https://example.com/ ')?->url);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function safeUrls(): array
    {
        return [
            'https' => ['https://example.com/a?b=c#d'],
            'http, upper case' => ['HTTP://example.com/'],
            'mailto' => ['mailto:info@example.com'],
            'tel' => ['tel:+3212345678'],
            'absolute path' => ['/sale.html'],
            'relative path' => ['sale.html'],
            'relative path with a colon later on' => ['sale/a:b.html'],
            'query' => ['?page=2'],
            'fragment' => ['#contact'],
        ];
    }

    /**
     * @dataProvider safeUrls
     */
    public function testKeepsAUrlWithASafeSchemeOrNone(string $url): void
    {
        self::assertSame($url, $this->resolver->resolve($url)?->url);
        self::assertSame(
            $url,
            $this->resolver->resolve(json_encode(['type' => 'default', 'default' => $url]) ?: '')?->url
        );
    }

    /**
     * @param string $requestPath
     * @param int $redirectType
     * @param array<string, string> $metadata
     * @return UrlRewrite&MockObject
     */
    private function rewrite(string $requestPath, int $redirectType = 0, array $metadata = []): UrlRewrite
    {
        $rewrite = $this->createMock(UrlRewrite::class);
        $rewrite->method('getRequestPath')->willReturn($requestPath);
        $rewrite->method('getRedirectType')->willReturn($redirectType);
        $rewrite->method('getMetadata')->willReturn($metadata);

        return $rewrite;
    }
}
