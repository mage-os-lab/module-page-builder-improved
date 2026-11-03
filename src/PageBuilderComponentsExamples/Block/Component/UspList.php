<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponentsExamples\Block\Component;

use MageOS\PageBuilderComponents\Attribute\PageBuilderComponent;
use MageOS\PageBuilderComponents\Block\AbstractComponent;
use MageOS\PageBuilderComponents\Field\InputField;
use MageOS\PageBuilderComponents\Field\SelectField;
use MageOS\PageBuilderComponents\Field\TextAreaField;

/**
 * List of unique selling points ("Free delivery", "30 days to return", ...), one per line.
 */
#[PageBuilderComponent(
    name: 'mageos_usp_list',
    label: 'USP List',
    template: 'MageOS_PageBuilderComponentsExamples::component/usp-list.phtml',
    icon: 'icon-pagebuilder-text',
    sortOrder: 30,
    stylesheets: ['MageOS_PageBuilderComponentsExamples::css/components.css'],
)]
class UspList extends AbstractComponent
{
    #[InputField(label: 'Title', sortOrder: 10)]
    /**
     * @var string
     */
    public string $title = '';

    #[TextAreaField(label: 'USPs (one per line)', sortOrder: 20, required: true)]
    /**
     * @var string
     */
    public string $items = '';

    #[SelectField(
        label: 'Layout',
        options: ['row' => 'In a row', 'grid' => 'Grid', 'list' => 'List'],
        sortOrder: 30,
    )]
    /**
     * @var string
     */
    public string $layout = 'row';

    #[SelectField(label: 'Icon', options: ['check' => 'Check mark', 'none' => 'None'], sortOrder: 40)]
    /**
     * @var string
     */
    public string $icon = 'check';

    /**
     * The USPs: one per non-empty line, trimmed.
     *
     * @return string[]
     */
    public function getItems(): array
    {
        $lines = preg_split('/\R/u', $this->items) ?: [];

        return array_values(array_filter(array_map('trim', $lines), static fn (string $line): bool => $line !== ''));
    }
}
