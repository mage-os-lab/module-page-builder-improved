<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Test\Unit\Model;

use Magento\Framework\Filter\Template;
use Magento\Framework\Filter\Template\DeferredDirectiveRegistry;
use Magento\Framework\Filter\Template\DirectiveOutputNeutralizer;
use Magento\Framework\Filter\Template\FilteringDepthMeter;
use Magento\Framework\Filter\Template\SignatureProvider;
use Magento\Framework\Filter\Template\Tokenizer\Parameter;
use Magento\Framework\Filter\VariableResolverInterface;
use Magento\Framework\Stdlib\StringUtils;
use MageOS\PageBuilderComponents\Model\DirectiveValueCodec;
use PHPUnit\Framework\TestCase;

class DirectiveValueCodecTest extends TestCase
{
    /**
     * @var DirectiveValueCodec
     */
    private DirectiveValueCodec $codec;

    protected function setUp(): void
    {
        $this->codec = new DirectiveValueCodec();
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function vectors(): array
    {
        $json = file_get_contents(__DIR__ . '/../_files/codec-vectors.json');
        self::assertIsString($json);
        $cases = [];
        foreach (json_decode($json, true, 512, JSON_THROW_ON_ERROR) as $i => $vector) {
            $cases['vector ' . $i] = [$vector['decoded'], $vector['encoded']];
        }

        return $cases;
    }

    /**
     * @dataProvider vectors
     */
    public function testEncodesAndDecodesTheSharedVectors(string $decoded, string $encoded): void
    {
        self::assertSame($encoded, $this->codec->encode($decoded));
        self::assertSame($decoded, $this->codec->decode($encoded));
    }

    /**
     * @dataProvider vectors
     */
    public function testEncodedValueHasNoCharacterTheDirectiveOrTheHtmlWouldRead(string $decoded): void
    {
        self::assertDoesNotMatchRegularExpression('/["\'{}\\\\%<>&$]/', $this->codec->encode($decoded));
    }

    /**
     * Core's tokenizer URL-decodes the parameter string, then splits it. Each encoded value must come back as
     * one parameter, unchanged, whatever it holds.
     *
     * @dataProvider vectors
     */
    public function testValueSurvivesCoreDirectiveTokenizer(string $decoded): void
    {
        $directive = \sprintf(
            ' type="Vendor\\Module\\Block\\Component" title="%s" other="kept"',
            $this->codec->encode($decoded)
        );
        $tokenizer = new Parameter();
        $tokenizer->setString($directive);
        $parameters = $tokenizer->tokenize();

        self::assertSame(['type', 'title', 'other'], array_keys($parameters));
        self::assertSame('Vendor\\Module\\Block\\Component', $parameters['type']);
        self::assertSame('kept', $parameters['other']);
        self::assertSame($decoded, $this->codec->decode($parameters['title']));
    }

    /**
     * Core's template filter reads a parameter that starts with `$` as a template variable. An encoded value
     * must reach the block as it is, whatever it holds.
     *
     * @dataProvider vectors
     */
    public function testValueSurvivesCoreTemplateFilterParameters(string $decoded): void
    {
        $resolver = $this->createMock(VariableResolverInterface::class);
        $resolver->method('resolve')->willReturn(null);
        $filter = new class (
            new StringUtils(),
            [],
            [],
            $resolver,
            $this->createMock(SignatureProvider::class),
            $this->createMock(FilteringDepthMeter::class),
            $this->createMock(DirectiveOutputNeutralizer::class),
            $this->createMock(DeferredDirectiveRegistry::class)
        ) extends Template {
            /**
             * @param string $value
             * @return array<string, mixed>
             */
            public function parameters(string $value): array
            {
                return $this->getParameters($value);
            }
        };

        $parameters = $filter->parameters(
            \sprintf(' type="Vendor\\Module\\Block\\Component" title="%s"', $this->codec->encode($decoded))
        );

        self::assertIsString($parameters['title']);
        self::assertSame($decoded, $this->codec->decode($parameters['title']));
    }

    public function testUrlEncodedQuoteCannotSetAnotherParameter(): void
    {
        $tokenizer = new Parameter();
        $tokenizer->setString(
            ' type="Vendor\\Module\\Block\\Component" title="' . $this->codec->encode('%22 type=%22Evil') . '"'
        );
        $parameters = $tokenizer->tokenize();

        self::assertSame('Vendor\\Module\\Block\\Component', $parameters['type']);
        self::assertSame('%22 type=%22Evil', $this->codec->decode($parameters['title']));
    }

    public function testDecodeKeepsALoneCaret(): void
    {
        self::assertSame('a^b^', $this->codec->decode('a^b^'));
    }

    public function testDecodeTableIsTheEncodeTableFlipped(): void
    {
        $constants = (new \ReflectionClass(DirectiveValueCodec::class))->getConstants();

        self::assertSame(array_flip($constants['ENCODE']), $constants['DECODE']);
    }
}
