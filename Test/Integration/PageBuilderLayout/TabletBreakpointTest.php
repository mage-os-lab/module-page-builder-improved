<?php

declare(strict_types=1);

namespace MageOS\PageBuilderImproved\Test\Integration\PageBuilderLayout;

use Magento\PageBuilder\Model\ConfigInterface;
use Magento\TestFramework\Fixture\AppArea;
use Magento\TestFramework\Helper\Bootstrap;
use PHPUnit\Framework\TestCase;

/**
 * The module's content type XML merges into core's: the layout content types get a Tablet form.
 *
 * Page Builder reads view/adminhtml/pagebuilder files through the admin theme, so this runs in the admin area.
 * One test over the content types rather than a data provider: Magento 2.4.8 runs its integration tests with
 * PHPUnit 10 and Mage-OS 3.x with PHPUnit 12, which no longer reads `@dataProvider`.
 */
#[AppArea('adminhtml')]
class TabletBreakpointTest extends TestCase
{
    /**
     * Content type => its Tablet form.
     */
    private const TABLET_FORMS = [
        'row' => 'pagebuilder_row_tablet_form',
        'column' => 'pagebuilder_column_tablet_form',
        'column-group' => 'pagebuilder_column_group_tablet_form',
        'tabs' => 'pagebuilder_tabs_tablet_form',
    ];

    public function testTheLayoutContentTypesHaveATabletForm(): void
    {
        $contentTypes = Bootstrap::getObjectManager()->get(ConfigInterface::class)->getContentTypes();

        foreach (self::TABLET_FORMS as $contentType => $form) {
            self::assertArrayHasKey($contentType, $contentTypes);
            self::assertSame(
                $form,
                $contentTypes[$contentType]['breakpoints']['tablet']['form'] ?? null,
                "$contentType has no Tablet form."
            );
        }
    }
}
