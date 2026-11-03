<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponentsExamples\Test\Unit\Block\Component;

use MageOS\PageBuilderComponents\Model\DefinitionReader;
use MageOS\PageBuilderComponents\Model\Value\Link;
use MageOS\PageBuilderComponentsExamples\Block\Component\CallToAction;
use MageOS\PageBuilderComponentsExamples\Block\Component\Cards;
use MageOS\PageBuilderComponentsExamples\Block\Component\Hero;
use MageOS\PageBuilderComponentsExamples\Block\Component\ImageText;
use MageOS\PageBuilderComponentsExamples\Block\Component\UspList;
use MageOS\PageBuilderComponentsExamples\Test\Unit\TemplateRendering;
use PHPUnit\Framework\TestCase;

/**
 * The templates are CSS framework agnostic: they use only the module's own classes, which its stylesheet styles.
 */
class StylesheetTest extends TestCase
{
    use TemplateRendering;

    private const STYLESHEET = '/view/frontend/web/css/components.css';

    public function testEveryClassIsTheModulesAndStyled(): void
    {
        $css = (string) file_get_contents(\dirname(__DIR__, 4) . self::STYLESHEET);
        $classes = [];
        foreach ($this->renderEveryVariant() as $html) {
            preg_match_all('#\sclass="([^"]*)"#', $html, $matches);
            foreach ($matches[1] as $attribute) {
                $classes += array_flip(preg_split('#\s+#', trim($attribute)) ?: []);
            }
        }

        self::assertGreaterThan(40, \count($classes));
        $foreign = $unstyled = [];
        foreach (array_keys($classes) as $class) {
            $class = (string) $class;
            if (!str_starts_with($class, 'mageos-pbc')) {
                $foreign[] = $class;
            } elseif (!preg_match('#\.' . preg_quote($class, '#') . '[\s,.:{)]#', $css)) {
                $unstyled[] = $class;
            }
        }
        self::assertSame([], $foreign, 'Classes that are not the module\'s own');
        // A default state's class (a hook for themes) counts once the stylesheet's header names it.
        self::assertSame([], $unstyled, 'Classes without a rule in components.css');
    }

    public function testTheStylesheetIsLoadedOnEveryPage(): void
    {
        $layout = (string) file_get_contents(\dirname(__DIR__, 4) . '/view/frontend/layout/default_head_blocks.xml');

        self::assertStringContainsString(
            '<css src="MageOS_PageBuilderComponentsExamples::css/components.css"/>',
            $layout
        );
    }

    /**
     * @dataProvider \MageOS\PageBuilderComponentsExamples\Test\Unit\Block\Component\BuiltInComponentsTest::registeredComponents
     */
    public function testTheStagePreviewLoadsTheStylesheet(string $name, string $class): void
    {
        self::assertSame(
            ['MageOS_PageBuilderComponentsExamples::css/components.css'],
            (new DefinitionReader())->read($name, $class)->stylesheets
        );
    }

    /**
     * Each component with every field filled, in each of its options.
     *
     * @return array<int, string>
     */
    private function renderEveryVariant(): array
    {
        $link = new Link('https://shop.test/');
        $buttons = [
            'title' => 'Title',
            'content' => '<p>Text</p>',
            'primaryLink' => $link,
            'primaryText' => 'Primary',
            'secondaryLink' => $link,
            'secondaryText' => 'Secondary',
        ];
        $html = [];
        foreach (['small', 'medium', 'large', 'screen'] as $height) {
            foreach (['left', 'center', 'right'] as $position) {
                foreach (['dark', 'light'] as $textColor) {
                    $html[] = $this->renderComponent(Hero::class, $buttons + [
                        'image' => 'https://shop.test/a.jpg',
                        'eyebrow' => 'Eyebrow',
                        'height' => $height,
                        'position' => $position,
                        'textColor' => $textColor,
                    ]);
                }
            }
        }
        $html[] = $this->renderComponent(Hero::class, $buttons);
        foreach ([1, 2, 3, 4] as $count) {
            $cards = ['title' => 'Title', 'intro' => 'Intro', 'columns' => 4];
            for ($n = 1; $n <= $count; $n++) {
                $cards += [
                    "card{$n}Image" => 'https://shop.test/a.jpg',
                    "card{$n}Title" => 'Card',
                    "card{$n}Text" => 'Text',
                    "card{$n}Link" => $link,
                    "card{$n}LinkText" => $n === 1 ? '' : 'More',
                ];
            }
            $html[] = $this->renderComponent(Cards::class, $cards);
        }
        foreach (['row', 'grid', 'list'] as $layout) {
            $html[] = $this->renderComponent(
                UspList::class,
                ['title' => 'Title', 'items' => "A\nB", 'layout' => $layout]
            );
        }
        foreach (['left', 'right'] as $position) {
            $html[] = $this->renderComponent(ImageText::class, $buttons + [
                'image' => 'https://shop.test/a.jpg',
                'eyebrow' => 'Eyebrow',
                'imagePosition' => $position,
            ]);
        }
        $html[] = $this->renderComponent(ImageText::class, $buttons);
        foreach (['light', 'primary', 'dark'] as $style) {
            foreach (['center', 'split'] as $alignment) {
                $html[] = $this->renderComponent(
                    CallToAction::class,
                    $buttons + ['style' => $style, 'alignment' => $alignment]
                );
            }
        }

        return $html;
    }
}
