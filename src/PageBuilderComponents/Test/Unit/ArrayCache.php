<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Test\Unit;

use Magento\Framework\Cache\FrontendInterface;

/**
 * A cache in memory, for tests of the classes that use one. It counts loads and saves.
 */
class ArrayCache implements FrontendInterface
{
    /**
     * @var array<string, array{data: string, tags: string[]}>
     */
    public array $entries = [];

    /**
     * @var int
     */
    public int $saves = 0;

    /**
     * @inheritdoc
     */
    public function test($identifier)
    {
        return isset($this->entries[$identifier]);
    }

    /**
     * @inheritdoc
     */
    public function load($identifier)
    {
        return $this->entries[$identifier]['data'] ?? false;
    }

    /**
     * @param mixed $data
     * @param string $identifier
     * @param string[] $tags
     * @param int|null $lifeTime
     * @return bool
     * @SuppressWarnings("PHPMD.UnusedFormalParameter")
     */
    public function save($data, $identifier, array $tags = [], $lifeTime = null)
    {
        $this->saves++;
        $this->entries[$identifier] = ['data' => (string) $data, 'tags' => $tags];

        return true;
    }

    /**
     * @inheritdoc
     */
    public function remove($identifier)
    {
        unset($this->entries[$identifier]);

        return true;
    }

    /**
     * @param string $mode
     * @param string[] $tags
     * @return bool
     */
    public function clean($mode = \Zend_Cache::CLEANING_MODE_ALL, array $tags = [])
    {
        $this->entries = $mode === \Zend_Cache::CLEANING_MODE_ALL ? [] : array_filter(
            $this->entries,
            static fn (array $entry): bool => array_intersect($tags, $entry['tags']) === []
        );

        return true;
    }

    /**
     * @inheritdoc
     */
    public function getBackend()
    {
        throw new \LogicException('Not implemented.');
    }

    /**
     * @inheritdoc
     */
    public function getLowLevelFrontend()
    {
        throw new \LogicException('Not implemented.');
    }
}
