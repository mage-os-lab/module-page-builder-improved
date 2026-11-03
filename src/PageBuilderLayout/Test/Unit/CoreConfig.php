<?php

declare(strict_types=1);

namespace MageOS\PageBuilderLayout\Test\Unit;

use DOMDocument;
use Magento\Framework\Config\Dom;
use Magento\Framework\Config\ValidationState\Configurable;
use Magento\Framework\ObjectManager\Config\Mapper\ArgumentParser;
use Magento\PageBuilder\Model\Config\ContentType\Converter;
use Magento\PageBuilder\Model\Config\ContentType\Reader;

/**
 * Core Page Builder's files with the module's merged into them, the way core's readers merge module files.
 */
trait CoreConfig
{
    private static function module(string $path = ''): string
    {
        return \dirname(__DIR__, 2) . ($path === '' ? '' : '/' . $path);
    }

    private static function core(string $path): string
    {
        return \dirname((string) (new \ReflectionClass(Converter::class))->getFileName(), 4) . '/' . $path;
    }

    /**
     * @param string $xml
     * @param string $schemaUrn
     * @return DOMDocument
     */
    private static function assertValidAgainstSchema(string $xml, string $schemaUrn): DOMDocument
    {
        $dom = new DOMDocument();
        self::assertTrue($dom->loadXML($xml), 'The XML is not well-formed.');
        $errors = Dom::validateDomDocument($dom, $schemaUrn);
        self::assertSame([], $errors, "The XML is invalid against $schemaUrn:\n" . implode("\n", $errors));

        return $dom;
    }

    /**
     * A content type, from core's file and the module's, as Page Builder's stage config has it.
     *
     * @param string $file e.g. column_group.xml
     * @param bool $withModule false for core's alone
     * @return array<string, mixed>
     */
    private static function contentType(string $file, bool $withModule = true): array
    {
        $extension = (string) file_get_contents(self::module('view/adminhtml/pagebuilder/content_type/' . $file));
        self::assertValidAgainstSchema($extension, 'urn:magento:module:Magento_PageBuilder:etc/content_type.xsd');

        $idAttributes = new \ReflectionProperty(Reader::class, '_idAttributes');
        $merged = new Dom(
            (string) file_get_contents(self::core('view/adminhtml/pagebuilder/content_type/' . $file)),
            new Configurable(false),
            $idAttributes->getValue((new \ReflectionClass(Reader::class))->newInstanceWithoutConstructor())
        );
        if ($withModule) {
            $merged->merge($extension);
        }
        self::assertValidAgainstSchema(
            (string) $merged->getDom()->saveXML(),
            'urn:magento:module:Magento_PageBuilder:etc/content_type_merged.xsd'
        );

        $types = (new Converter(new ArgumentParser()))->convert($merged->getDom())['types'];
        self::assertCount(1, $types);

        return (array) reset($types);
    }

    /**
     * Page Builder's breakpoints, from core's view.xml and the module's: their text settings, and conditions.
     *
     * @return array<string, array{conditions: array<string, string>, settings: array<string, string>}>
     */
    private static function breakpoints(): array
    {
        $extension = (string) file_get_contents(self::module('etc/view.xml'));
        self::assertValidAgainstSchema($extension, 'urn:magento:framework:Config/etc/view.xsd');

        // \Magento\Framework\Config\View's id attributes.
        $merged = new Dom(
            (string) file_get_contents(self::core('etc/view.xml')),
            new Configurable(false),
            ['/view/vars' => 'module', '/view/vars/(var/)+var' => 'name', '/view/exclude/item' => ['type', 'item']]
        );
        $merged->merge($extension);

        $query = '/view/vars[@module="Magento_PageBuilder"]/var[@name="breakpoints"]/var';
        $breakpoints = [];
        foreach ((new \DOMXPath($merged->getDom()))->query($query) ?: [] as $node) {
            self::assertInstanceOf(\DOMElement::class, $node);
            $breakpoint = ['conditions' => [], 'settings' => []];
            foreach (self::vars($node) as $name => $var) {
                if (self::vars($var) === []) {
                    $breakpoint['settings'][$name] = $var->textContent;
                }
            }
            foreach (self::vars(self::vars($node)['conditions'] ?? null) as $name => $var) {
                $breakpoint['conditions'][$name] = $var->textContent;
            }
            $breakpoints[$node->getAttribute('name')] = $breakpoint;
        }

        return $breakpoints;
    }

    /**
     * The <var> children of a view.xml <var>, by name.
     *
     * @param \DOMElement|null $node
     * @return array<string, \DOMElement>
     */
    private static function vars(?\DOMElement $node): array
    {
        $vars = [];
        foreach ($node === null ? [] : $node->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->tagName === 'var') {
                $vars[$child->getAttribute('name')] = $child;
            }
        }

        return $vars;
    }
}
