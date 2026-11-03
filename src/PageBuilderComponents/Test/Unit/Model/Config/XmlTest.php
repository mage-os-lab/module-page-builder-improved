<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Test\Unit\Model\Config;

use DOMDocument;
use MageOS\PageBuilderComponents\Model\Config\Xml;
use PHPUnit\Framework\TestCase;

class XmlTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function hostileValues(): array
    {
        return [
            'markup characters' => ['"<&\'>'],
            'end of a CDATA section' => [']]>'],
            'CDATA section' => ['a<![CDATA[b]]>c'],
            'entities as text' => ['&amp; &#x41; &lt;'],
            'tab and line breaks' => ["a\tb\nc\r\nd"],
            'non-ASCII' => ['Café – 日本 😀'],
        ];
    }

    /**
     * @dataProvider hostileValues
     */
    public function testAttributesAndTextReadBackExactlyTheValue(string $value): void
    {
        $escaped = (new Xml())->escape($value);
        $dom = new DOMDocument();

        self::assertTrue($dom->loadXML("<root value=\"$escaped\" other='$escaped'>$escaped</root>"));
        self::assertSame($value, $dom->documentElement?->getAttribute('value'));
        self::assertSame($value, $dom->documentElement->getAttribute('other'));
        self::assertSame($value, $dom->documentElement->textContent);
    }

    public function testDropsCharactersXmlCannotHoldAndReplacesInvalidUtf8(): void
    {
        $escaped = (new Xml())->escape("a\x00b\x01c\x1Fd\xEF\xBF\xBEe\xFFf");
        $dom = new DOMDocument();

        self::assertTrue($dom->loadXML("<root>$escaped</root>"));
        self::assertSame("abcde\u{FFFD}f", $dom->documentElement?->textContent);
    }
}
