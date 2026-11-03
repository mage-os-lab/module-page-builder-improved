<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponentsExamples\Test\Unit;

use DOMDocument;
use Magento\Framework\Config\Dom;

/**
 * Validates generated XML against core's schemas, as core's config readers do.
 */
trait XmlSchemaAssertions
{
    /**
     * @param string $xml
     * @param string $schemaUrn
     * @return DOMDocument
     */
    private static function assertValidAgainstSchema(string $xml, string $schemaUrn): DOMDocument
    {
        $dom = new DOMDocument();
        self::assertTrue($dom->loadXML($xml), 'The generated XML is not well-formed.');
        $errors = Dom::validateDomDocument($dom, $schemaUrn);
        self::assertSame([], $errors, "The generated XML is invalid against $schemaUrn:\n" . implode("\n", $errors));

        return $dom;
    }
}
