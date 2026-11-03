<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Widget\Block\BlockInterface;
use MageOS\PageBuilderComponents\Exception\InvalidComponentException;
use MageOS\PageBuilderComponents\Model\ComponentDefinition;
use MageOS\PageBuilderComponents\Model\ComponentRegistry;
use MageOS\PageBuilderComponents\Model\ImageSize;
use MageOS\PageBuilderComponents\Model\ValueHydrator;

/**
 * Base class of a Page Builder component's block.
 *
 * Declare the component with {@see \MageOS\PageBuilderComponents\Attribute\PageBuilderComponent} on the class
 * and one field attribute per public property. Before the template renders, each property holds its field's
 * value, decoded and resolved (see the field classes), or its default when the field has none.
 *
 * The template is always the one the attribute declares: a `template` parameter in the widget directive is
 * ignored. Themes override it the usual way, at the same path.
 *
 * This class is the module's extension point (Magento's standard does not allow `@api` on an abstract class).
 */
abstract class AbstractComponent extends Template implements BlockInterface
{
    /**
     * @var bool
     */
    private bool $hydrated = false;

    /**
     * @param Context $context
     * @param ComponentRegistry $componentRegistry
     * @param ValueHydrator $valueHydrator
     * @param ImageSize $imageSize
     * @param array $data
     * @phpstan-param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly ComponentRegistry $componentRegistry,
        private readonly ValueHydrator $valueHydrator,
        private readonly ImageSize $imageSize,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * The component's definition.
     *
     * @throws InvalidComponentException When the class is not registered.
     * @return ComponentDefinition
     */
    public function getComponentDefinition(): ComponentDefinition
    {
        $definition = $this->componentRegistry->getForBlock($this);
        if ($definition === null) {
            throw new InvalidComponentException(\sprintf(
                '%s is not a registered Page Builder component. Register it on %s in di.xml.',
                static::class,
                ComponentRegistry::class
            ));
        }

        return $definition;
    }

    /**
     * Set the field properties from the block's data. Runs before rendering; call it to read them earlier.
     *
     * It runs once until the data changes: after setData() or unsetData(), the next call reads the data again.
     *
     * @return void
     */
    public function hydrate(): void
    {
        if ($this->hydrated) {
            return;
        }
        $this->hydrated = true;

        foreach ($this->valueHydrator->hydrate($this->getComponentDefinition(), $this->getData()) as $name => $value) {
            $this->{$name} = $value;
        }
    }

    /**
     * Width and height of an image in the media directory, such as an ImageField's value.
     *
     * For the `width` and `height` attributes of an `<img>`. Null for an SVG, a remote URL or a missing file.
     *
     * @param string $url
     * @return array{width: int, height: int}|null
     */
    public function getImageSize(string $url): ?array
    {
        return $this->imageSize->get($url);
    }

    /**
     * Set data, and have the next hydrate() read it again.
     *
     * @param string|array $key
     * @param mixed $value
     * @phpstan-param string|array<string, mixed> $key
     * @return $this
     */
    public function setData($key, $value = null)
    {
        $this->hydrated = false;

        return parent::setData($key, $value);
    }

    /**
     * Unset data, and have the next hydrate() read it again.
     *
     * @param string|array|null $key
     * @phpstan-param string|array<int, string>|null $key
     * @return $this
     */
    public function unsetData($key = null)
    {
        $this->hydrated = false;

        return parent::unsetData($key);
    }

    /**
     * @inheritdoc
     */
    public function getTemplate()
    {
        return $this->getComponentDefinition()->template;
    }

    /**
     * @inheritdoc
     */
    protected function _beforeToHtml()
    {
        $this->hydrate();

        return parent::_beforeToHtml();
    }
}
