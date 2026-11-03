<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Test\Unit\Model\Config;

use DOMDocument;
use DOMXPath;
use Magento\Framework\Config\Dom;
use MageOS\PageBuilderComponents\Model\Config\FormXmlGenerator;
use MageOS\PageBuilderComponents\Model\Config\Xml;
use MageOS\PageBuilderComponents\Model\DefinitionReader;
use MageOS\PageBuilderComponents\Test\Unit\Fixture\AllFieldsComponent;
use MageOS\PageBuilderComponents\Test\Unit\Fixture\MinimalComponent;
use MageOS\PageBuilderComponents\Test\Unit\XmlSchemaAssertions;
use PHPUnit\Framework\TestCase;

class FormXmlGeneratorTest extends TestCase
{
    use XmlSchemaAssertions;

    private const SCHEMA = 'urn:magento:module:Magento_Ui:etc/ui_configuration.xsd';

    /**
     * @var DOMXPath
     */
    private DOMXPath $xpath;

    /**
     * @var string
     */
    private string $xml;

    protected function setUp(): void
    {
        $definition = (new DefinitionReader())->read('test_all_fields', AllFieldsComponent::class);
        $this->xml = (new FormXmlGenerator(new Xml()))->generate($definition);
        $dom = new DOMDocument();
        $dom->loadXML($this->xml);
        $this->xpath = new DOMXPath($dom);
    }

    public function testFormWithEveryFieldTypeIsValidAgainstTheUiSchema(): void
    {
        self::assertValidAgainstSchema($this->xml, self::SCHEMA);
    }

    public function testFormWithoutOptionalSettingsIsValidAgainstTheUiSchema(): void
    {
        $definition = (new DefinitionReader())->read('test_minimal', MinimalComponent::class);

        self::assertValidAgainstSchema((new FormXmlGenerator(new Xml()))->generate($definition), self::SCHEMA);
    }

    public function testSchemaValidationIsLive(): void
    {
        $dom = new DOMDocument();
        $dom->loadXML(str_replace('<dataType>text</dataType>', '<dataType>text</dataType><nonsense/>', $this->xml));

        self::assertNotSame([], Dom::validateDomDocument($dom, self::SCHEMA));
    }

    public function testExtendsCoreBaseFormUnderTheComponentsNamespace(): void
    {
        self::assertSame('pagebuilder_base_form', $this->value('/form/@extends'));
        self::assertSame('mageos_pbc_test_all_fields_form', $this->value('/form/settings/namespace'));
        self::assertSame(
            'MageOS_PageBuilderComponents/js/form/provider',
            $this->value('/form/dataSource/argument/item[@name="js_config"]/item[@name="component"]')
        );
    }

    public function testListsTheFieldsInSortOrderWithTheirFormElements(): void
    {
        $names = [];
        foreach ($this->xpath->query('/form/fieldset[@name="general"]/*[@name]') ?: [] as $node) {
            /** @var \DOMElement $node */
            $names[] = $node->tagName . ':' . $node->getAttribute('name') . ':' . $node->getAttribute('formElement');
        }

        self::assertSame(
            [
                'field:title:input',
                'field:count:input',
                'field:intro:textarea',
                'field:content:wysiwyg',
                'field:image:imageUploader',
                'urlInput:link:',
                'urlInput:plainLink:',
                'field:layout:select',
                'field:overlay:checkbox',
                'htmlContent:products:',
            ],
            $names
        );
    }

    public function testUsesPropertyDefaultsAndTheRequiredRule(): void
    {
        $default = 'argument/item[@name="config"]/item[@name="default"]';

        self::assertSame('Default title', $this->value("//field[@name='title']/$default"));
        self::assertSame('3', $this->value("//field[@name='count']/$default"));
        self::assertSame('left', $this->value("//field[@name='layout']/$default"));
        self::assertSame('1', $this->value("//field[@name='overlay']/$default"));
        self::assertSame(
            'true',
            $this->value("//field[@name='title']/settings/validation/rule[@name='required-entry']")
        );
        self::assertSame(0, $this->countNodes("//field[@name='intro']/settings/validation"));
    }

    public function testSelectListsItsOptionsAndToggleMapsToOneAndZero(): void
    {
        $options = '//field[@name="layout"]/formElements/select/settings/options/option';
        self::assertSame('left', $this->value("$options" . '[1]/item[@name="value"]'));
        self::assertSame('Image right', $this->value("$options" . '[2]/item[@name="label"]'));

        $valueMap = '//field[@name="overlay"]/formElements/checkbox/settings/valueMap';
        self::assertSame('1', $this->value("$valueMap/map[@name='true']"));
        self::assertSame('0', $this->value("$valueMap/map[@name='false']"));
    }

    public function testConditionsFieldTargetsItsOwnForm(): void
    {
        self::assertSame(
            'mageos_pbc_test_all_fields_form',
            $this->value('//htmlContent[@name="products"]/block/arguments/argument[@name="formNamespace"]')
        );
    }

    public function testListsTheConditionsFieldsForTheFormProvider(): void
    {
        $items = '/form/dataSource/dataProvider/argument[@name="data"]/item[@name="config"]'
            . '/item[@name="conditionsFields"]/item';

        self::assertSame('products', $this->value($items . '[@name="products"]'));
        self::assertSame(1, $this->countNodes($items));
    }

    public function testListsNoConditionsFieldsWhenTheComponentHasNone(): void
    {
        $definition = (new DefinitionReader())->read('test_minimal', MinimalComponent::class);
        $dom = new DOMDocument();
        $dom->loadXML((new FormXmlGenerator(new Xml()))->generate($definition));
        $items = (new DOMXPath($dom))->query('//item[@name="conditionsFields"]/item');

        self::assertNotFalse($items);
        self::assertSame(0, $items->length);
    }

    public function testEscapesTheLabel(): void
    {
        self::assertSame(
            'All <Fields> & "More"',
            $this->value('/form/argument[@name="data"]/item[@name="label"]')
        );
    }

    /**
     * @param string $expression
     * @return string
     */
    private function value(string $expression): string
    {
        $nodes = $this->xpath->query($expression);
        self::assertNotFalse($nodes);
        self::assertSame(1, $nodes->length, "Expected one node at $expression");

        return trim((string) $nodes->item(0)?->nodeValue);
    }

    /**
     * @param string $expression
     * @return int
     */
    private function countNodes(string $expression): int
    {
        $nodes = $this->xpath->query($expression);
        self::assertNotFalse($nodes);

        return $nodes->length;
    }
}
