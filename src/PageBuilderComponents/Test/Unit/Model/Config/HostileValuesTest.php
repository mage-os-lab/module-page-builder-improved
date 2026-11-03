<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Test\Unit\Model\Config;

use DOMDocument;
use DOMXPath;
use MageOS\PageBuilderComponents\Field\ConditionsField;
use MageOS\PageBuilderComponents\Field\FieldInterface;
use MageOS\PageBuilderComponents\Field\ImageField;
use MageOS\PageBuilderComponents\Field\InputField;
use MageOS\PageBuilderComponents\Field\SelectField;
use MageOS\PageBuilderComponents\Field\TextAreaField;
use MageOS\PageBuilderComponents\Field\ToggleField;
use MageOS\PageBuilderComponents\Field\UrlField;
use MageOS\PageBuilderComponents\Field\WysiwygField;
use MageOS\PageBuilderComponents\Model\ComponentDefinition;
use MageOS\PageBuilderComponents\Model\Config\ContentTypeXmlGenerator;
use MageOS\PageBuilderComponents\Model\Config\FormXmlGenerator;
use MageOS\PageBuilderComponents\Model\Config\Xml;
use MageOS\PageBuilderComponents\Model\FieldDefinition;
use PHPUnit\Framework\TestCase;

/**
 * Labels, defaults and options are free text in a component's attributes, and names are only safe because the
 * definition reader validates them. Every one of them must come out of the generators as well-formed XML that
 * reads back exactly the text, even when it bypasses that validation.
 */
class HostileValuesTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function hostileValues(): array
    {
        return [
            'markup characters' => ['"<&\'>'],
            'end of a CDATA section' => [']]>'],
            'closing tags' => ['</item></argument></form><evil/>'],
            'entities as text' => ['&amp; &#x41;'],
            'line breaks' => ["a\nb\tc"],
        ];
    }

    /**
     * @dataProvider hostileValues
     */
    public function testFormReadsBackHostileLabelsDefaultsOptionsAndNames(string $hostile): void
    {
        $definition = $this->createDefinition($hostile);
        $xpath = $this->load((new FormXmlGenerator(new Xml()))->generate($definition));

        self::assertSame($hostile, $this->value($xpath, '/form/argument[@name="data"]/item[@name="label"]'));
        self::assertSame(
            $definition->getFormName(),
            $this->value($xpath, '/form/settings/namespace')
        );
        self::assertSame(
            $definition->getFormName() . '_data_source',
            $this->value($xpath, '/form/dataSource/@name')
        );

        $fieldset = '/form/fieldset[@name="general"]';
        foreach (array_keys($definition->fields) as $name) {
            $node = "$fieldset/*[@name=" . $this->literal($name) . ']';
            self::assertSame(1, $this->countNodes($xpath, $node), "Field $name");
            if ($name === 'products') {
                self::assertSame(
                    $hostile,
                    $this->value($xpath, "$node/block/arguments/argument[@name='label']")
                );
                self::assertSame(
                    $definition->getFormName(),
                    $this->value($xpath, "$node/block/arguments/argument[@name='formNamespace']")
                );
                continue;
            }
            self::assertSame($hostile, $this->value($xpath, "$node/settings/label"), "Label of $name");
            self::assertSame($name, $this->value($xpath, "$node/settings/dataScope"), "Data scope of $name");
        }

        $default = 'argument/item[@name="config"]/item[@name="default"]';
        self::assertSame($hostile, $this->value($xpath, "$fieldset/field[@name='title']/$default"));
        self::assertSame($hostile, $this->value($xpath, "$fieldset/field[@name='layout']/$default"));
        $option = "$fieldset/field[@name='layout']/formElements/select/settings/options/option[1]";
        self::assertSame($hostile, $this->value($xpath, "$option/item[@name='value']"));
        self::assertSame($hostile, $this->value($xpath, "$option/item[@name='label']"));
    }

    /**
     * @dataProvider hostileValues
     */
    public function testContentTypeReadsBackHostileValues(string $hostile): void
    {
        $definition = $this->createDefinition($hostile);
        $xpath = $this->load((new ContentTypeXmlGenerator(new Xml()))->generate($definition));

        self::assertSame($hostile, $this->value($xpath, '/config/type/@name'));
        self::assertSame($hostile, $this->value($xpath, '/config/type/@label'));
        self::assertSame($hostile, $this->value($xpath, '/config/type/@menu_section'));
        self::assertSame($hostile, $this->value($xpath, '/config/type/@icon'));
        self::assertSame($definition->getFormName(), $this->value($xpath, '/config/type/@form'));
        $data = '/config/type/additional_data/item[@name="mageos_component"]';
        self::assertSame($hostile, $this->value($xpath, "$data/item[@name='name']"));
        self::assertSame($hostile, $this->value($xpath, "$data/item[@name='block_class']"));
        self::assertSame('text', $this->value($xpath, "$data/item[@name='fields']/item[@name='title']"));
        self::assertSame(
            'text',
            $this->value($xpath, "$data/item[@name='fields']/item[@name=" . $this->literal("$hostile x") . ']')
        );
    }

    /**
     * A component with one field of each type, every value of it hostile.
     *
     * @param string $hostile
     * @return ComponentDefinition
     */
    private function createDefinition(string $hostile): ComponentDefinition
    {
        $fields = [
            'title' => new InputField($hostile),
            'intro' => new TextAreaField($hostile),
            'content' => new WysiwygField($hostile),
            'image' => new ImageField($hostile),
            'link' => new UrlField($hostile),
            'layout' => new SelectField($hostile, [$hostile => $hostile, 'b' => 'B']),
            'overlay' => new ToggleField($hostile),
            'products' => new ConditionsField($hostile),
            // A name the definition reader would refuse.
            "$hostile x" => new InputField($hostile),
        ];

        return new ComponentDefinition(
            $hostile,
            $hostile,
            'MageOS_PageBuilderComponents::test.phtml',
            $hostile,
            $hostile,
            10,
            // @phpstan-ignore argument.type (not a class: the generator must escape it all the same)
            $hostile,
            array_map(
                static fn (FieldInterface $field): FieldDefinition => new FieldDefinition(
                    'unused',
                    $field,
                    'string',
                    false,
                    $hostile
                ),
                $fields
            )
        );
    }

    /**
     * @param string $xml
     * @return DOMXPath
     */
    private function load(string $xml): DOMXPath
    {
        $dom = new DOMDocument();
        self::assertTrue($dom->loadXML($xml), 'The generated XML is not well-formed.');

        return new DOMXPath($dom);
    }

    /**
     * @param DOMXPath $xpath
     * @param string $expression
     * @return string
     */
    private function value(DOMXPath $xpath, string $expression): string
    {
        $nodes = $xpath->query($expression);
        self::assertNotFalse($nodes);
        self::assertSame(1, $nodes->length, "Expected one node at $expression");

        return (string) $nodes->item(0)?->nodeValue;
    }

    /**
     * @param DOMXPath $xpath
     * @param string $expression
     * @return int
     */
    private function countNodes(DOMXPath $xpath, string $expression): int
    {
        $nodes = $xpath->query($expression);
        self::assertNotFalse($nodes);

        return $nodes->length;
    }

    /**
     * An XPath string literal of any value: XPath 1.0 has no escaping, so quotes are joined with concat().
     *
     * @param string $value
     * @return string
     */
    private function literal(string $value): string
    {
        if (!str_contains($value, "'")) {
            return "'$value'";
        }
        if (!str_contains($value, '"')) {
            return "\"$value\"";
        }

        return "concat('" . str_replace("'", "', \"'\", '", $value) . "')";
    }
}
