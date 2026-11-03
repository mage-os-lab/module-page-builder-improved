<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Test\Unit\Model;

use Magento\Framework\Filter\Template\Tokenizer\Parameter;
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
        self::assertDoesNotMatchRegularExpression('/["\'{}\\\\%<>&]/', $this->codec->encode($decoded));
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
