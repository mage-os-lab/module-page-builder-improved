<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Model;

/**
 * Encodes field values so any string survives a `{{widget}}` directive parameter, and decodes them.
 *
 * Core's directive tokenizer URL-decodes the whole parameter string before it splits it, and a `"` ends a
 * value. Unencoded, a `"` or a `%22` in a field would end the value and could set other parameters, such as
 * `type`. `{` and `}` would end the directive, and `<`, `>` and `&` would be read as HTML in the stored
 * content. Each of these characters, and the escape character `^` itself, becomes `^` and a digit, so an
 * encoded value contains none of them and decodes to exactly the original.
 *
 * `view/adminhtml/web/js/utils/directive-value-codec.js` is the same codec for the stage. Change both together;
 * `Test/Unit/_files/codec-vectors.json` holds the cases both are tested against.
 *
 * @api
 */
class DirectiveValueCodec
{
    private const ENCODE = [
        '^' => '^0',
        '"' => '^1',
        "'" => '^2',
        '{' => '^3',
        '}' => '^4',
        '\\' => '^5',
        '%' => '^6',
        '<' => '^7',
        '>' => '^8',
        '&' => '^9',
    ];

    /**
     * ENCODE flipped; a test keeps the two in step.
     */
    private const DECODE = [
        '^0' => '^',
        '^1' => '"',
        '^2' => "'",
        '^3' => '{',
        '^4' => '}',
        '^5' => '\\',
        '^6' => '%',
        '^7' => '<',
        '^8' => '>',
        '^9' => '&',
    ];

    /**
     * Encode a value for a directive parameter.
     *
     * @param string $value
     * @return string
     */
    public function encode(string $value): string
    {
        return strtr($value, self::ENCODE);
    }

    /**
     * Decode a directive parameter. A `^` not followed by a digit is kept as it is.
     *
     * @param string $value
     * @return string
     */
    public function decode(string $value): string
    {
        return strtr($value, self::DECODE);
    }
}
