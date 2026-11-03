<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Test\Unit\Plugin;

use Magento\Framework\View\Model\Layout\Merge;
use Magento\PageBuilder\Model\Config\FileResolver as PageBuilderFileResolver;
use Magento\Ui\Config\Reader\FileResolver as UiFileResolver;
use MageOS\PageBuilderComponents\Model\Config\ContentTypeXmlGenerator;
use MageOS\PageBuilderComponents\Model\Config\FormXmlGenerator;
use MageOS\PageBuilderComponents\Model\Config\Xml;
use MageOS\PageBuilderComponents\Plugin\AddContentTypeFiles;
use MageOS\PageBuilderComponents\Plugin\AddFormFiles;
use MageOS\PageBuilderComponents\Plugin\AddFormLayoutHandle;
use MageOS\PageBuilderComponents\Test\Unit\RegistryFactory;
use PHPUnit\Framework\TestCase;

/**
 * The plugins that hand core's readers the components' generated files and layout.
 */
class AddConfigFilesTest extends TestCase
{
    public function testContentTypeFilesComeBeforeTheModulesFiles(): void
    {
        $plugin = new AddContentTypeFiles(RegistryFactory::create(), new ContentTypeXmlGenerator(new Xml()));
        $result = $plugin->afterGet(
            $this->createMock(PageBuilderFileResolver::class),
            ['/module/content_type/products.xml' => '<config/>'],
            'content_type/*.xml'
        );

        self::assertSame(
            [
                'virtual://MageOS_PageBuilderComponents/pagebuilder/content_type/test_all_fields.xml',
                'virtual://MageOS_PageBuilderComponents/pagebuilder/content_type/test_minimal.xml',
                '/module/content_type/products.xml',
            ],
            array_keys($result)
        );
        self::assertStringContainsString(
            '<type name="test_minimal"',
            $result['virtual://MageOS_PageBuilderComponents/pagebuilder/content_type/test_minimal.xml']
        );
    }

    public function testMenuSectionFilesAreLeftAlone(): void
    {
        $plugin = new AddContentTypeFiles(RegistryFactory::create(), new ContentTypeXmlGenerator(new Xml()));
        $files = ['/module/menu_section.xml' => '<config/>'];

        self::assertSame(
            $files,
            $plugin->afterGet($this->createMock(PageBuilderFileResolver::class), $files, 'menu_section.xml')
        );
    }

    public function testGivesAComponentFormItsGeneratedFileFirst(): void
    {
        $plugin = new AddFormFiles(RegistryFactory::create(), new FormXmlGenerator(new Xml()));
        $result = $plugin->afterGet(
            $this->createMock(UiFileResolver::class),
            ['/theme/ui_component/mageos_pbc_test_minimal_form.xml' => '<form/>'],
            'mageos_pbc_test_minimal_form.xml'
        );

        self::assertIsArray($result);
        self::assertSame(
            [
                'virtual://MageOS_PageBuilderComponents/ui_component/mageos_pbc_test_minimal_form.xml',
                '/theme/ui_component/mageos_pbc_test_minimal_form.xml',
            ],
            array_keys($result)
        );
        self::assertStringContainsString(
            '<namespace>mageos_pbc_test_minimal_form</namespace>',
            $result['virtual://MageOS_PageBuilderComponents/ui_component/mageos_pbc_test_minimal_form.xml']
        );
    }

    public function testLeavesOtherFormsAlone(): void
    {
        $plugin = new AddFormFiles(RegistryFactory::create(), new FormXmlGenerator(new Xml()));
        $files = ['/module/ui_component/cms_page_form.xml' => '<form/>'];

        self::assertSame(
            $files,
            $plugin->afterGet($this->createMock(UiFileResolver::class), $files, 'cms_page_form.xml')
        );
        self::assertSame(
            [],
            $plugin->afterGet($this->createMock(UiFileResolver::class), [], 'mageos_pbc_unknown_form.xml')
        );
    }

    public function testAddsTheFormsLayoutOncePerLayout(): void
    {
        $plugin = new AddFormLayoutHandle(RegistryFactory::create());
        $merge = $this->createMock(Merge::class);
        $merge->expects(self::once())->method('addUpdate')->with(
            '<update handle="styles"/><referenceContainer name="content">'
            . '<uiComponent name="mageos_pbc_test_minimal_form"/></referenceContainer>'
        );

        $plugin->afterAddHandle($merge, $merge, ['default', 'mageos_pbc_test_minimal_form']);
        $plugin->afterAddHandle($merge, $merge, 'mageos_pbc_test_minimal_form');

        $otherLayout = $this->createMock(Merge::class);
        $otherLayout->expects(self::once())->method('addUpdate');
        $plugin->afterAddHandle($otherLayout, $otherLayout, 'mageos_pbc_test_minimal_form');
    }

    public function testIgnoresOtherHandles(): void
    {
        $plugin = new AddFormLayoutHandle(RegistryFactory::create());
        $merge = $this->createMock(Merge::class);
        $merge->expects(self::never())->method('addUpdate');

        self::assertSame(
            $merge,
            $plugin->afterAddHandle($merge, $merge, ['default', 'pagebuilder_products_form', 'mageos_pbc_x_form'])
        );
    }
}
