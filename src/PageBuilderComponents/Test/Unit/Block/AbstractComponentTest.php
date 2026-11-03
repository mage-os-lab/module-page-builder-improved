<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Test\Unit\Block;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use MageOS\PageBuilderComponents\Exception\InvalidComponentException;
use MageOS\PageBuilderComponents\Model\ComponentRegistry;
use MageOS\PageBuilderComponents\Model\ImageSize;
use MageOS\PageBuilderComponents\Model\ValueHydrator;
use MageOS\PageBuilderComponents\Test\Unit\Fixture\AllFieldsComponent;
use MageOS\PageBuilderComponents\Test\Unit\Fixture\MinimalComponent;
use MageOS\PageBuilderComponents\Test\Unit\RegistryFactory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AbstractComponentTest extends TestCase
{
    /**
     * @var ValueHydrator&MockObject
     */
    private ValueHydrator $hydrator;

    /**
     * @var ImageSize&MockObject
     */
    private ImageSize $imageSize;

    protected function setUp(): void
    {
        $this->hydrator = $this->createMock(ValueHydrator::class);
        $this->imageSize = $this->createMock(ImageSize::class);
    }

    public function testTemplateIsTheDeclaredOneWhateverTheDirectiveSays(): void
    {
        $block = $this->createBlock(
            MinimalComponent::class,
            ['template' => 'Magento_Customer::account/dashboard.phtml']
        );

        self::assertSame('Vendor_Module::minimal.phtml', $block->getTemplate());
    }

    public function testHydrateSetsThePropertiesOnceFromTheBlockData(): void
    {
        $block = $this->createBlock(AllFieldsComponent::class, ['title' => 'Encoded']);
        $this->hydrator->expects(self::once())
            ->method('hydrate')
            ->with(
                self::callback(static fn ($definition): bool => $definition->name === 'test_all_fields'),
                self::callback(static fn (array $data): bool => $data['title'] === 'Encoded')
            )
            ->willReturn(['title' => 'Decoded', 'overlay' => false]);

        $block->hydrate();
        $block->hydrate();

        self::assertSame('Decoded', $block->title);
        self::assertFalse($block->overlay);
        self::assertSame(3, $block->count);
        self::assertSame('untouched', $block->notAField);
    }

    public function testHydratesAgainAfterTheDataChanges(): void
    {
        $block = $this->createBlock(AllFieldsComponent::class, ['title' => 'First']);
        $this->hydrator->expects(self::exactly(4))
            ->method('hydrate')
            ->willReturnCallback(static fn ($definition, array $data): array => isset($data['title'])
                ? ['title' => 'Decoded ' . $data['title']]
                : []);

        $block->hydrate();
        self::assertSame('Decoded First', $block->title);

        $block->setData('title', 'Second');
        $block->hydrate();
        $block->hydrate();
        self::assertSame('Decoded Second', $block->title);

        $block->setTitle('Third');
        $block->hydrate();
        self::assertSame('Decoded Third', $block->title);

        $block->unsetData('title');
        $block->hydrate();
        // A field no longer in the data keeps what it had: hydration only sets the fields that have a value.
        self::assertSame('Decoded Third', $block->title);
    }

    public function testGetImageSizeAsksTheImageSizeService(): void
    {
        $block = $this->createBlock(MinimalComponent::class, []);
        $this->imageSize->method('get')->willReturnMap([
            ['https://shop.test/media/a.jpg', ['width' => 800, 'height' => 600]],
            ['https://cdn.test/b.jpg', null],
        ]);

        self::assertSame(['width' => 800, 'height' => 600], $block->getImageSize('https://shop.test/media/a.jpg'));
        self::assertNull($block->getImageSize('https://cdn.test/b.jpg'));
    }

    public function testAnUnregisteredClassSaysHowToRegisterIt(): void
    {
        $block = $this->createBlock(MinimalComponent::class, [], $this->createMock(ComponentRegistry::class));

        $this->expectException(InvalidComponentException::class);
        $this->expectExceptionMessage('is not a registered Page Builder component');
        $block->getTemplate();
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @param array<string, mixed> $data
     * @param ComponentRegistry|null $registry
     * @return T
     */
    private function createBlock(string $class, array $data, ?ComponentRegistry $registry = null): object
    {
        return (new ObjectManager($this))->getObject($class, [
            'componentRegistry' => $registry ?? RegistryFactory::create(),
            'valueHydrator' => $this->hydrator,
            'imageSize' => $this->imageSize,
            'data' => $data,
        ]);
    }
}
