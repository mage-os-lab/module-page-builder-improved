<?php

declare(strict_types=1);

namespace MageOS\PageBuilderImproved\Test\Integration;

use Magento\Framework\Module\ModuleListInterface;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * The package's four modules register and load in a real install, after the modules they depend on.
 */
class ModulesEnabledTest extends TestCase
{
    private const MODULES = [
        'MageOS_PageBuilderComponents',
        'MageOS_PageBuilderComponentsExamples',
        'MageOS_PageBuilderLayout',
        'MageOS_PageBuilderUi',
    ];

    public function testAllModulesAreEnabled(): void
    {
        $moduleList = Bootstrap::getObjectManager()->get(ModuleListInterface::class);

        foreach (self::MODULES as $module) {
            self::assertTrue($moduleList->has($module), "$module is not enabled.");
        }
    }

    public function testModulesLoadAfterPageBuilder(): void
    {
        $names = Bootstrap::getObjectManager()->get(ModuleListInterface::class)->getNames();
        $pageBuilder = array_search('Magento_PageBuilder', $names, true);
        self::assertNotFalse($pageBuilder, 'Magento_PageBuilder is not enabled.');

        foreach (self::MODULES as $module) {
            self::assertGreaterThan($pageBuilder, array_search($module, $names, true), "$module loads too early.");
        }
    }
}
