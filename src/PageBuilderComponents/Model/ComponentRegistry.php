<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Model;

use Magento\Framework\App\Cache\Type\Config as ConfigCache;
use Magento\Framework\App\State;
use Magento\Framework\Cache\FrontendInterface;
use MageOS\PageBuilderComponents\Exception\InvalidComponentException;
use MageOS\PageBuilderComponents\Field\FieldInterface;
use Psr\Log\LoggerInterface;

/**
 * The registered components, read from their block classes and kept in the config cache.
 *
 * Register a component in di.xml:
 *
 *     <type name="MageOS\PageBuilderComponents\Model\ComponentRegistry">
 *         <arguments>
 *             <argument name="components" xsi:type="array">
 *                 <item name="acme_hero" xsi:type="string">Acme\Theme\Block\Component\Hero</item>
 *             </argument>
 *         </arguments>
 *     </type>
 *
 * A module leaves out a component registered by another, such as one of this module's own, by setting it to null:
 *
 *     <item name="mageos_hero" xsi:type="null"/>
 *
 * An invalid component throws in developer mode. In default and production mode it is logged and left out, so
 * one broken class does not take down every page that renders a widget.
 *
 * The definitions are read with reflection once, then kept in the config cache, like the content types and
 * forms generated from them: after changing a component's attributes, clean the config cache.
 *
 * @api
 */
class ComponentRegistry
{
    private const CACHE_ID_PREFIX = 'mageos_pbc_components_';

    /**
     * @var array<string, ComponentDefinition>|null
     */
    private ?array $definitions = null;

    /**
     * @var array<string, ComponentDefinition>|null Lowercased block class => definition.
     */
    private ?array $byClass = null;

    /**
     * @param DefinitionReader $definitionReader
     * @param State $appState
     * @param LoggerInterface $logger
     * @param FrontendInterface $cache The config cache.
     * @param array $components Component name => block class, or null to leave a component out.
     * @phpstan-param array<string, string|null> $components
     */
    public function __construct(
        private readonly DefinitionReader $definitionReader,
        private readonly State $appState,
        private readonly LoggerInterface $logger,
        private readonly FrontendInterface $cache,
        private readonly array $components = [],
    ) {
    }

    /**
     * All valid components, keyed by name.
     *
     * @return array<string, ComponentDefinition>
     */
    public function getAll(): array
    {
        if ($this->definitions === null) {
            $cacheId = self::CACHE_ID_PREFIX . sha1((string) json_encode($this->components));
            $definitions = $this->loadFromCache($cacheId);
            if ($definitions === null) {
                $definitions = $this->readAll();
                $this->saveToCache($cacheId, $definitions);
            }
            $this->definitions = $definitions;
        }

        return $this->definitions;
    }

    /**
     * The component of that name, or null.
     *
     * @param string $name
     * @return ComponentDefinition|null
     */
    public function get(string $name): ?ComponentDefinition
    {
        return $this->getAll()[$name] ?? null;
    }

    /**
     * The component a UI form name belongs to, or null.
     *
     * @param string $formName
     * @return ComponentDefinition|null
     */
    public function getByFormName(string $formName): ?ComponentDefinition
    {
        // Cheap checks first: Layout\Merge::addHandle() asks this for every layout handle of every request.
        if (!str_starts_with($formName, ComponentDefinition::FORM_PREFIX)
            || !str_ends_with($formName, ComponentDefinition::FORM_SUFFIX)
        ) {
            return null;
        }
        $name = substr(
            $formName,
            \strlen(ComponentDefinition::FORM_PREFIX),
            -\strlen(ComponentDefinition::FORM_SUFFIX)
        );

        return $name !== '' ? $this->get($name) : null;
    }

    /**
     * The component a block is an instance of, or null.
     *
     * Matches the block's own class first, then its parents, so a generated interceptor and a subclass of a
     * component resolve to the nearest registered class.
     *
     * @param object $block
     * @return ComponentDefinition|null
     */
    public function getForBlock(object $block): ?ComponentDefinition
    {
        if ($this->byClass === null) {
            $this->byClass = [];
            foreach ($this->getAll() as $definition) {
                $this->byClass[strtolower($definition->blockClass)] = $definition;
            }
        }
        $byClass = $this->byClass;

        foreach ([\get_class($block), ...array_values(class_parents($block) ?: [])] as $class) {
            if (isset($byClass[strtolower($class)])) {
                return $byClass[strtolower($class)];
            }
        }

        return null;
    }

    /**
     * Read every registered component.
     *
     * @return array<string, ComponentDefinition>
     */
    private function readAll(): array
    {
        $definitions = [];
        foreach ($this->components as $name => $class) {
            if ($class === null || $class === '') {
                continue;
            }
            try {
                $definitions[(string) $name] = $this->definitionReader->read((string) $name, $class);
            } catch (InvalidComponentException $e) {
                if ($this->appState->getMode() === State::MODE_DEVELOPER) {
                    throw $e;
                }
                $this->logger->critical($e->getMessage(), ['exception' => $e]);
            }
        }

        uasort(
            $definitions,
            static fn (ComponentDefinition $a, ComponentDefinition $b): int => $a->sortOrder <=> $b->sortOrder
        );

        return $definitions;
    }

    /**
     * The definitions in the cache, or null when there are none or they cannot be restored.
     *
     * Only the definition classes and the field classes recorded beside them are allowed to be unserialized.
     *
     * @param string $cacheId
     * @return array<string, ComponentDefinition>|null
     */
    private function loadFromCache(string $cacheId): ?array
    {
        $data = $this->cache->load($cacheId);
        $payload = \is_string($data) ? json_decode($data, true) : null;
        if (!\is_array($payload) || !\is_array($payload['classes'] ?? null)
            || !\is_string($payload['definitions'] ?? null)
        ) {
            return null;
        }
        $allowedClasses = [ComponentDefinition::class, FieldDefinition::class];
        foreach ($payload['classes'] as $class) {
            if (!\is_string($class) || !is_a($class, FieldInterface::class, true)) {
                return null;
            }
            $allowedClasses[] = $class;
        }

        $definitions = $this->unserialize($payload['definitions'], $allowedClasses);
        if (!\is_array($definitions)) {
            return null;
        }
        foreach ($definitions as $definition) {
            if (!$definition instanceof ComponentDefinition) {
                return null;
            }
        }

        /** @var array<string, ComponentDefinition> $definitions */
        return $definitions;
    }

    /**
     * Keep the definitions in the cache, if they restore to what they are.
     *
     * A field that holds objects of other classes would not be restored, so such definitions are read again
     * on every request instead.
     *
     * @param string $cacheId
     * @param ComponentDefinition[] $definitions
     * @phpstan-param array<string, ComponentDefinition> $definitions
     * @return void
     */
    private function saveToCache(string $cacheId, array $definitions): void
    {
        $classes = [];
        foreach ($definitions as $definition) {
            foreach ($definition->fields as $fieldDefinition) {
                $classes[\get_class($fieldDefinition->field)] = true;
            }
        }
        $classes = array_keys($classes);
        // phpcs:ignore Magento2.Security.InsecureFunction -- objects, read back with allowed_classes.
        $serialized = serialize($definitions);
        $allowedClasses = [ComponentDefinition::class, FieldDefinition::class, ...$classes];
        // Loose comparison: the objects' properties, recursively.
        if ($this->unserialize($serialized, $allowedClasses) != $definitions) {
            return;
        }

        $this->cache->save(
            (string) json_encode(['classes' => $classes, 'definitions' => $serialized]),
            $cacheId,
            [ConfigCache::CACHE_TAG]
        );
    }

    /**
     * Unserialize a value, instantiating only the allowed classes.
     *
     * @param string $serialized
     * @param string[] $allowedClasses
     * @return mixed
     */
    private function unserialize(string $serialized, array $allowedClasses): mixed
    {
        // phpcs:ignore Magento2.Security.InsecureFunction -- only the allowed classes are instantiated.
        return unserialize($serialized, ['allowed_classes' => $allowedClasses]);
    }
}
