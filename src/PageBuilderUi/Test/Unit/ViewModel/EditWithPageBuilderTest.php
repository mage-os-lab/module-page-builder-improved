<?php

declare(strict_types=1);

namespace MageOS\PageBuilderUi\Test\Unit\ViewModel;

use Magento\Framework\Serialize\Serializer\JsonHexTag;
use MageOS\PageBuilderUi\ViewModel\EditWithPageBuilder;
use PHPUnit\Framework\TestCase;

class EditWithPageBuilderTest extends TestCase
{
    public function testConfiguresTheComponentWithTheFieldset(): void
    {
        $config = (new EditWithPageBuilder(new JsonHexTag()))->getInitConfig('cms_page_form.cms_page_form.content');

        self::assertSame(
            ['*' => ['MageOS_PageBuilderUi/js/edit-with-page-builder' => [
                'fieldset' => 'cms_page_form.cms_page_form.content',
            ]]],
            json_decode($config, true)
        );
    }

    public function testCannotCloseTheScriptElement(): void
    {
        $config = (new EditWithPageBuilder(new JsonHexTag()))->getInitConfig('</script><script>alert(1)</script>');

        self::assertStringNotContainsString('<', $config);
        self::assertStringNotContainsString('>', $config);
        self::assertSame(
            '</script><script>alert(1)</script>',
            json_decode($config, true)['*']['MageOS_PageBuilderUi/js/edit-with-page-builder']['fieldset']
        );
    }
}
