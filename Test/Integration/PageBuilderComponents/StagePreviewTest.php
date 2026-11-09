<?php

declare(strict_types=1);

namespace MageOS\PageBuilderImproved\Test\Integration\PageBuilderComponents;

use Magento\PageBuilder\Model\Stage\RendererPool;
use Magento\TestFramework\Helper\Bootstrap;
use MageOS\PageBuilderComponents\Model\Stage\ComponentRenderer;
use PHPUnit\Framework\TestCase;

/**
 * Core's stage preview controller gets the components' renderer from its pool, and it renders the real templates.
 *
 * @magentoAppArea frontend
 */
class StagePreviewTest extends TestCase
{
    public function testCoreRendererPoolHasTheComponentRenderer(): void
    {
        $renderer = Bootstrap::getObjectManager()->get(RendererPool::class)->getRenderer('mageos_component');

        self::assertInstanceOf(ComponentRenderer::class, $renderer);
    }

    public function testItRendersARegisteredComponentWithTheFormValues(): void
    {
        $result = $this->createRenderer()->render([
            'component' => 'mageos_hero',
            'values' => ['title' => 'Integration hero title', 'eyebrow' => 'Integration eyebrow'],
        ]);

        self::assertArrayNotHasKey('error', $result);
        self::assertStringStartsWith('<iframe data-mageos-pbc-preview=""', $result['content']);
        self::assertStringContainsString('mageos-pbc-hero', $result['content']);
        self::assertStringContainsString('Integration hero title', $result['content']);
        self::assertStringContainsString('Integration eyebrow', $result['content']);
    }

    public function testItRefusesAComponentThatIsNotRegistered(): void
    {
        $result = $this->createRenderer()->render([
            'component' => 'Magento\\Cms\\Block\\Block',
            'values' => ['block_id' => '1'],
        ]);

        self::assertSame('', $result['content']);
        self::assertArrayHasKey('error', $result);
    }

    /**
     * @return ComponentRenderer
     */
    private function createRenderer(): ComponentRenderer
    {
        return Bootstrap::getObjectManager()->create(ComponentRenderer::class);
    }
}
