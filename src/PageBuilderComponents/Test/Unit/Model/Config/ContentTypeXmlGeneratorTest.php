<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Test\Unit\Model\Config;

use DOMDocument;
use Magento\Framework\Data\Argument\Interpreter\ArrayType;
use Magento\Framework\Data\Argument\Interpreter\BaseStringUtils;
use Magento\Framework\Data\Argument\Interpreter\Composite;
use Magento\Framework\ObjectManager\Config\Mapper\ArgumentParser;
use Magento\Framework\Stdlib\BooleanUtils;
use Magento\PageBuilder\Model\Config\ContentType\AdditionalData\Parser;
use Magento\PageBuilder\Model\Config\ContentType\Converter;
use MageOS\PageBuilderComponents\Model\Config\ContentTypeXmlGenerator;
use MageOS\PageBuilderComponents\Model\Config\Xml;
use MageOS\PageBuilderComponents\Model\DefinitionReader;
use MageOS\PageBuilderComponents\Test\Unit\Fixture\AllFieldsComponent;
use MageOS\PageBuilderComponents\Test\Unit\XmlSchemaAssertions;
use PHPUnit\Framework\TestCase;

/**
 * The XML is checked the way core's content type reader takes it: against both of its schemas, then through
 * its converter and its additional data parser.
 */
class ContentTypeXmlGeneratorTest extends TestCase
{
    use XmlSchemaAssertions;

    private const SCHEMA = 'urn:magento:module:Magento_PageBuilder:etc/content_type.xsd';
    private const MERGED_SCHEMA = 'urn:magento:module:Magento_PageBuilder:etc/content_type_merged.xsd';

    /**
     * @var string
     */
    private string $xml;

    protected function setUp(): void
    {
        $definition = (new DefinitionReader())->read('test_all_fields', AllFieldsComponent::class);
        $this->xml = (new ContentTypeXmlGenerator(new Xml()))->generate($definition);
    }

    public function testIsValidAgainstBothContentTypeSchemas(): void
    {
        self::assertValidAgainstSchema($this->xml, self::SCHEMA);
        self::assertValidAgainstSchema($this->xml, self::MERGED_SCHEMA);
    }

    /**
     * Guards the test above: core's validator returns no errors at all when libxml cannot load schemas.
     */
    public function testSchemaValidationIsLive(): void
    {
        $dom = new DOMDocument();
        $dom->loadXML(str_replace('sortOrder="5"', 'sortOrder="500"', $this->xml));

        self::assertNotSame([], \Magento\Framework\Config\Dom::validateDomDocument($dom, self::SCHEMA));
    }

    public function testConvertsToTheContentTypeTheStageExpects(): void
    {
        $type = $this->convert()['types']['test_all_fields'];

        self::assertSame('All <Fields> & "More"', $type['label']);
        self::assertSame('mageos_pbc_test_all_fields_form', $type['form']);
        self::assertSame('elements', $type['menu_section']);
        self::assertSame('icon-pagebuilder-heading', $type['icon']);
        self::assertSame('5', $type['sortOrder']);
        self::assertSame('MageOS_PageBuilderComponents/js/content-type/preview', $type['preview_component']);

        $appearance = $type['appearances']['default'];
        self::assertSame('true', $appearance['default']);
        self::assertSame('MageOS_PageBuilderComponents/content-type/component/master', $appearance['master_template']);
        self::assertSame(
            [
                'name' => 'widget_directive',
                'component' => 'MageOS_PageBuilderComponents/js/mass-converter/widget-directive',
                'config' => ['html_variable' => 'html', 'content_type' => 'test_all_fields'],
            ],
            $appearance['converters'][0]
        );
    }

    /**
     * The main element must read and write what core's own directive-based content type (Products) does for
     * the same properties, so the Advanced fieldset and the stage behave the same.
     */
    public function testMainElementMatchesCoreProductsContentType(): void
    {
        $main = $this->convert()['types']['test_all_fields']['appearances']['default']['elements']['main'];
        $core = $this->convertCoreProducts()['appearances']['grid']['elements']['main'];

        $coreStyles = array_values(array_filter(
            $core['style'],
            static fn (array $style): bool => \in_array($style['var'], ['display', 'margins_and_padding'], true)
        ));
        self::assertSame($coreStyles, $main['style']);
        self::assertSame($core['attributes'], $main['attributes']);
        self::assertSame($core['html'], $main['html']);
        self::assertSame($core['css'], $main['css']);
    }

    public function testAdditionalDataTellsTheStageTheBlockClassAndEachFieldsValueType(): void
    {
        $interpreter = new Composite([], 'xsi:type');
        $interpreter->addInterpreter('string', new BaseStringUtils(new BooleanUtils()));
        $interpreter->addInterpreter('array', new ArrayType($interpreter));
        $additionalData = (new Parser($interpreter))->toArray(
            $this->convert()['types']['test_all_fields']['additional_data']
        );

        self::assertSame(
            [
                'mageos_component' => [
                    'name' => 'test_all_fields',
                    'block_class' => AllFieldsComponent::class,
                    'fields' => [
                        'title' => 'text',
                        'count' => 'text',
                        'intro' => 'text',
                        'content' => 'text',
                        'image' => 'image',
                        'link' => 'link',
                        'plainLink' => 'link',
                        'layout' => 'text',
                        'overlay' => 'text',
                        'products' => 'text',
                    ],
                ],
            ],
            $additionalData
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function convert(): array
    {
        $dom = new DOMDocument();
        $dom->loadXML($this->xml);

        return (new Converter(new ArgumentParser()))->convert($dom);
    }

    /**
     * @return array<string, mixed>
     */
    private function convertCoreProducts(): array
    {
        $file = \dirname((string) (new \ReflectionClass(Converter::class))->getFileName(), 4)
            . '/view/adminhtml/pagebuilder/content_type/products.xml';
        $dom = new DOMDocument();
        self::assertTrue($dom->load($file));

        return (new Converter(new ArgumentParser()))->convert($dom)['types']['products'];
    }
}
