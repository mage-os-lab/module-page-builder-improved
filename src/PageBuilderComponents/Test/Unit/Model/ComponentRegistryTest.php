<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Test\Unit\Model;

use Magento\Framework\App\Cache\Type\Config as ConfigCache;
use Magento\Framework\App\State;
use MageOS\PageBuilderComponents\Exception\InvalidComponentException;
use MageOS\PageBuilderComponents\Model\ComponentRegistry;
use MageOS\PageBuilderComponents\Model\DefinitionReader;
use MageOS\PageBuilderComponents\Test\Unit\ArrayCache;
use MageOS\PageBuilderComponents\Test\Unit\Fixture\AllFieldsComponent;
use MageOS\PageBuilderComponents\Test\Unit\Fixture\Invalid\BadTemplate;
use MageOS\PageBuilderComponents\Test\Unit\Fixture\MinimalComponent;
use MageOS\PageBuilderComponents\Test\Unit\Fixture\MinimalSubclass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use stdClass;

class ComponentRegistryTest extends TestCase
{
    public function testListsValidComponentsBySortOrder(): void
    {
        $registry = $this->createRegistry([
            'test_minimal' => MinimalComponent::class,
            'test_all_fields' => AllFieldsComponent::class,
        ]);

        self::assertSame(['test_all_fields', 'test_minimal'], array_keys($registry->getAll()));
        self::assertSame(MinimalComponent::class, $registry->get('test_minimal')?->blockClass);
        self::assertNull($registry->get('unknown'));
    }

    public function testFindsAComponentByItsFormName(): void
    {
        $registry = $this->createRegistry(['test_minimal' => MinimalComponent::class]);

        self::assertSame('test_minimal', $registry->getByFormName('mageos_pbc_test_minimal_form')?->name);
        self::assertNull($registry->getByFormName('pagebuilder_test_minimal_form'));
        self::assertNull($registry->getByFormName('mageos_pbc__form'));
        self::assertNull($registry->getByFormName('mageos_pbc_unknown_form'));
    }

    public function testFindsTheComponentOfASubclassOrInterceptor(): void
    {
        $registry = $this->createRegistry(['test_minimal' => MinimalComponent::class]);
        $subclass = (new \ReflectionClass(MinimalSubclass::class))->newInstanceWithoutConstructor();

        self::assertSame('test_minimal', $registry->getForBlock($subclass)?->name);
        self::assertNull($registry->getForBlock(new stdClass()));
    }

    public function testPrefersTheNearestRegisteredClass(): void
    {
        $registry = $this->createRegistry([
            'test_minimal' => MinimalComponent::class,
        ]);
        $all = (new \ReflectionClass(AllFieldsComponent::class))->newInstanceWithoutConstructor();

        self::assertNull($registry->getForBlock($all));
    }

    public function testThrowsForAnInvalidComponentInDeveloperMode(): void
    {
        $registry = $this->createRegistry(['test_invalid' => BadTemplate::class], State::MODE_DEVELOPER);

        $this->expectException(InvalidComponentException::class);
        $registry->getAll();
    }

    public function testLogsAndSkipsAnInvalidComponentInProductionMode(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('critical')->with(self::stringContains('its template must'));
        $registry = $this->createRegistry(
            ['test_invalid' => BadTemplate::class, 'test_minimal' => MinimalComponent::class],
            State::MODE_PRODUCTION,
            $logger
        );

        self::assertSame(['test_minimal'], array_keys($registry->getAll()));
    }

    public function testLeavesOutAComponentSetToNull(): void
    {
        $registry = $this->createRegistry(
            ['test_minimal' => MinimalComponent::class, 'test_all_fields' => null],
            State::MODE_DEVELOPER
        );

        self::assertSame(['test_minimal'], array_keys($registry->getAll()));
    }

    public function testReadsTheClassesOnce(): void
    {
        $reader = $this->createMock(DefinitionReader::class);
        $reader->expects(self::once())->method('read')
            ->willReturn((new DefinitionReader())->read('test_minimal', MinimalComponent::class));
        $registry = new ComponentRegistry(
            $reader,
            $this->createMock(State::class),
            $this->createMock(LoggerInterface::class),
            new ArrayCache(),
            ['test_minimal' => MinimalComponent::class]
        );

        $registry->getAll();
        $registry->get('test_minimal');
        $registry->getByFormName('mageos_pbc_test_minimal_form');
    }

    public function testReadsTheClassesOnceUntilTheConfigCacheIsCleaned(): void
    {
        $cache = new ArrayCache();
        $components = ['test_minimal' => MinimalComponent::class, 'test_all_fields' => AllFieldsComponent::class];
        $reader = $this->createMock(DefinitionReader::class);
        $reader->expects(self::exactly(4))->method('read')
            ->willReturnCallback(static fn (string $name, string $class) => (new DefinitionReader())->read(
                $name,
                $class
            ));
        $expected = $this->createRegistry($components)->getAll();

        // Two requests: the second reads the cache.
        $first = $this->createRegistry($components, State::MODE_PRODUCTION, null, $cache, $reader)->getAll();
        $second = $this->createRegistry($components, State::MODE_PRODUCTION, null, $cache, $reader)->getAll();
        self::assertEquals($expected, $first);
        self::assertEquals($expected, $second);
        self::assertSame(['test_all_fields', 'test_minimal'], array_keys($second));
        self::assertSame(1, $cache->saves);
        $entry = array_values($cache->entries)[0];
        self::assertSame([ConfigCache::CACHE_TAG], $entry['tags']);

        // A config cache clean reads them again.
        $cache->clean(\Zend_Cache::CLEANING_MODE_MATCHING_ANY_TAG, [ConfigCache::CACHE_TAG]);
        self::assertEquals(
            $expected,
            $this->createRegistry($components, State::MODE_PRODUCTION, null, $cache, $reader)->getAll()
        );
        self::assertSame(2, $cache->saves);
    }

    public function testCachesEachSetOfComponentsOnItsOwn(): void
    {
        $cache = new ArrayCache();

        $this->createRegistry(['test_minimal' => MinimalComponent::class], cache: $cache)->getAll();
        $all = $this->createRegistry(
            ['test_minimal' => MinimalComponent::class, 'test_all_fields' => AllFieldsComponent::class],
            cache: $cache
        )->getAll();

        self::assertSame(['test_all_fields', 'test_minimal'], array_keys($all));
        self::assertCount(2, $cache->entries);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unusableCacheEntries(): array
    {
        // phpcs:ignore Magento2.Security.InsecureFunction -- the format the registry caches.
        $definition = serialize(['test_minimal' => new stdClass()]);

        return [
            'not JSON' => ['nonsense'],
            // phpcs:ignore Magento2.Security.InsecureFunction
            'no classes' => [(string) json_encode(['definitions' => serialize([])])],
            'a class that is not a field' => [
                (string) json_encode(['classes' => [stdClass::class], 'definitions' => $definition]),
            ],
            'not a definition' => [(string) json_encode(['classes' => [], 'definitions' => $definition])],
            // phpcs:ignore Magento2.Security.InsecureFunction
            'not an array' => [(string) json_encode(['classes' => [], 'definitions' => serialize('x')])],
        ];
    }

    /**
     * @dataProvider unusableCacheEntries
     */
    public function testReadsTheClassesAgainWhenTheCacheEntryIsUnusable(string $entry): void
    {
        $cache = new ArrayCache();
        $components = ['test_minimal' => MinimalComponent::class];
        $this->createRegistry($components, cache: $cache)->getAll();
        $cacheId = array_key_first($cache->entries);
        self::assertIsString($cacheId);
        $cache->entries[$cacheId]['data'] = $entry;

        $all = $this->createRegistry($components, cache: $cache)->getAll();

        self::assertSame(['test_minimal'], array_keys($all));
        self::assertSame(MinimalComponent::class, $all['test_minimal']->blockClass);
    }

    public function testDoesNotCacheAnInvalidComponentInDeveloperMode(): void
    {
        $cache = new ArrayCache();
        try {
            $this->createRegistry(['test_invalid' => BadTemplate::class], State::MODE_DEVELOPER, cache: $cache)
                ->getAll();
            self::fail('Expected an InvalidComponentException.');
        } catch (InvalidComponentException) {
            self::assertSame([], $cache->entries);
        }
    }

    /**
     * @param array<string, string|null> $components
     * @param string $mode
     * @param LoggerInterface|null $logger
     * @param ArrayCache|null $cache
     * @param DefinitionReader|null $reader
     * @return ComponentRegistry
     */
    private function createRegistry(
        array $components,
        string $mode = State::MODE_PRODUCTION,
        ?LoggerInterface $logger = null,
        ?ArrayCache $cache = null,
        ?DefinitionReader $reader = null
    ): ComponentRegistry {
        $state = $this->createMock(State::class);
        $state->method('getMode')->willReturn($mode);

        return new ComponentRegistry(
            $reader ?? new DefinitionReader(),
            $state,
            $logger ?? $this->createMock(LoggerInterface::class),
            $cache ?? new ArrayCache(),
            $components
        );
    }
}
