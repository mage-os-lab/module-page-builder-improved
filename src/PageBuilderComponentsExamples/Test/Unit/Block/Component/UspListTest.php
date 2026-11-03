<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponentsExamples\Test\Unit\Block\Component;

use MageOS\PageBuilderComponentsExamples\Block\Component\UspList;
use MageOS\PageBuilderComponentsExamples\Test\Unit\TemplateRendering;
use PHPUnit\Framework\TestCase;

class UspListTest extends TestCase
{
    use TemplateRendering;

    public function testReadsOneUspPerNonEmptyLine(): void
    {
        $list = $this->createComponent(UspList::class, [
            'items' => "  Free delivery \r\n\n30 days to return\r  \nMade in Belgium",
        ]);

        self::assertSame(['Free delivery', '30 days to return', 'Made in Belgium'], $list->getItems());
    }

    public function testRendersTheUsps(): void
    {
        $html = $this->renderComponent(UspList::class, [
            'title' => 'Why us',
            'items' => "Free delivery\n<b>Fast</b> & friendly",
            'layout' => 'grid',
        ]);

        self::assertStringContainsString('Why us', $html);
        self::assertSame(2, substr_count($html, '<li '));
        self::assertSame(2, substr_count($html, '<svg'));
        self::assertStringContainsString('&lt;b&gt;Fast&lt;/b&gt; &amp; friendly', $html);
        self::assertStringContainsString('mageos-pbc-usp-list--grid', $html);
    }

    public function testLeavesOutTheIcon(): void
    {
        $html = $this->renderComponent(UspList::class, ['items' => 'Free delivery', 'icon' => 'none']);

        self::assertStringNotContainsString('<svg', $html);
    }

    public function testRendersNothingWithoutUsps(): void
    {
        self::assertSame('', trim($this->renderComponent(UspList::class, ['title' => 'Why us', 'items' => " \n "])));
    }
}
