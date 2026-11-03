<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponentsExamples\Test\Unit;

use Magento\Framework\Escaper;
use Magento\Framework\Translate\InlineInterface;
use Magento\Framework\ZendEscaper;
use MageOS\PageBuilderComponents\Attribute\PageBuilderComponent;
use MageOS\PageBuilderComponents\Block\AbstractComponent;
use MageOS\PageBuilderComponents\Model\ImageSize;
use PHPUnit\Framework\TestCase;

/**
 * Renders an example component's template as Magento would, with the given property values.
 *
 * @mixin TestCase
 */
trait TemplateRendering
{
    /**
     * Image URL => size, what getImageSize() gives the components; null for any other URL.
     *
     * @var array<string, array{width: int, height: int}>
     */
    private array $imageSizes = [];

    /**
     * @param class-string<AbstractComponent> $class
     * @param array<string, mixed> $values Property => hydrated value.
     * @return string
     */
    private function renderComponent(string $class, array $values = []): string
    {
        $block = $this->createComponent($class, $values);
        $escaper = $this->createEscaper();
        $template = \dirname(__DIR__, 2) . '/view/frontend/templates/'
            . explode('::', $this->getTemplateId($class))[1];

        ob_start();
        try {
            (static function () use ($block, $escaper, $template): void {
                include $template;
            })();
        } finally {
            $html = (string) ob_get_clean();
        }

        return $html;
    }

    /**
     * A component block with the given property values, without its dependencies but the image sizes.
     *
     * @template T of AbstractComponent
     * @param class-string<T> $class
     * @param array<string, mixed> $values
     * @return T
     */
    private function createComponent(string $class, array $values = []): AbstractComponent
    {
        $block = (new \ReflectionClass($class))->newInstanceWithoutConstructor();
        $imageSize = $this->createMock(ImageSize::class);
        $imageSize->method('get')->willReturnCallback(fn (string $url): ?array => $this->imageSizes[$url] ?? null);
        // A readonly property is only initialized from its own class: the closure is bound to it.
        \Closure::bind(static function (AbstractComponent $block) use ($imageSize): void {
            $block->imageSize = $imageSize; // @phpstan-ignore property.readOnlyAssignOutOfClass
            // The values are the hydrated ones: hydrate() keeps them.
            $block->hydrated = true;
        }, null, AbstractComponent::class)($block);
        foreach ($values as $name => $value) {
            $block->{$name} = $value;
        }

        return $block;
    }

    /**
     * @param class-string<AbstractComponent> $class
     * @return string
     */
    private function getTemplateId(string $class): string
    {
        $attributes = (new \ReflectionClass($class))->getAttributes(PageBuilderComponent::class);

        return $attributes[0]->newInstance()->template;
    }

    /**
     * @return Escaper
     */
    private function createEscaper(): Escaper
    {
        $escaper = new Escaper();
        $lazyDependencies = [
            'translateInline' => $this->createMock(InlineInterface::class),
            'escaper' => new ZendEscaper(),
        ];
        foreach ($lazyDependencies as $property => $value) {
            $reflection = new \ReflectionProperty(Escaper::class, $property);
            $reflection->setValue($escaper, $value);
        }

        return $escaper;
    }
}
