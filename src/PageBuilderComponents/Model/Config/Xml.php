<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Model\Config;

/**
 * Escaping for the generated config XML.
 */
class Xml
{
    /**
     * Characters XML 1.0 does not allow at all, even as a character reference.
     */
    private const INVALID_CHARACTERS = '/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u';

    /**
     * Escape a value for an XML attribute or text node, so the parser reads back exactly the value.
     *
     * Tabs and line breaks become character references, which a parser keeps in an attribute (a literal one
     * is read as a space). Invalid UTF-8 becomes U+FFFD, and characters XML cannot hold are dropped.
     *
     * @param string $value
     * @return string
     */
    public function escape(string $value): string
    {
        // phpcs:ignore Magento2.Functions.DiscouragedFunction -- XML, not HTML: the Escaper's rules are for HTML.
        $escaped = htmlspecialchars($value, ENT_QUOTES | ENT_XML1 | ENT_SUBSTITUTE, 'UTF-8');

        return strtr(
            (string) preg_replace(self::INVALID_CHARACTERS, '', $escaped),
            ["\t" => '&#9;', "\n" => '&#10;', "\r" => '&#13;']
        );
    }
}
