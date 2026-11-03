<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponentsExamples\Block\Component;

use MageOS\PageBuilderComponents\Model\Value\Link;

/**
 * Buttons of the example components: a link field and a text field each.
 */
trait ButtonsTrait
{
    /**
     * The buttons that have both a link and a text, in the order given.
     *
     * @param array $candidates Style (`primary`, `secondary`) => [link, text].
     * @phpstan-param array<string, array{0: Link|null, 1: string}> $candidates
     * @return array<string, array{link: Link, text: string}>
     */
    private function filterButtons(array $candidates): array
    {
        $buttons = [];
        foreach ($candidates as $style => [$link, $text]) {
            $text = trim($text);
            if ($link !== null && $link->url !== '' && $text !== '') {
                $buttons[$style] = ['link' => $link, 'text' => $text];
            }
        }

        return $buttons;
    }
}
