<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponentsExamples\Block\Component;

use MageOS\PageBuilderComponents\Attribute\PageBuilderComponent;
use MageOS\PageBuilderComponents\Block\AbstractComponent;
use MageOS\PageBuilderComponents\Field\InputField;
use MageOS\PageBuilderComponents\Field\SelectField;
use MageOS\PageBuilderComponents\Field\UrlField;
use MageOS\PageBuilderComponents\Field\WysiwygField;
use MageOS\PageBuilderComponents\Model\Value\Link;

/**
 * Coloured band with a title, a short text and up to two buttons.
 */
#[PageBuilderComponent(
    name: 'mageos_call_to_action',
    label: 'Call to Action',
    template: 'MageOS_PageBuilderComponentsExamples::component/call-to-action.phtml',
    icon: 'icon-pagebuilder-button',
    sortOrder: 50,
    stylesheets: ['MageOS_PageBuilderComponentsExamples::css/components.css'],
)]
class CallToAction extends AbstractComponent
{
    use ButtonsTrait;

    #[InputField(label: 'Title', sortOrder: 10)]
    /**
     * @var string
     */
    public string $title = '';

    #[WysiwygField(label: 'Text', sortOrder: 20)]
    /**
     * @var string
     */
    public string $content = '';

    #[UrlField(label: 'Primary Button Link', sortOrder: 30)]
    /**
     * @var Link|null
     */
    public ?Link $primaryLink = null;

    #[InputField(label: 'Primary Button Text', sortOrder: 35)]
    /**
     * @var string
     */
    public string $primaryText = '';

    #[UrlField(label: 'Secondary Button Link', sortOrder: 40)]
    /**
     * @var Link|null
     */
    public ?Link $secondaryLink = null;

    #[InputField(label: 'Secondary Button Text', sortOrder: 45)]
    /**
     * @var string
     */
    public string $secondaryText = '';

    #[SelectField(
        label: 'Style',
        options: ['light' => 'Light', 'primary' => 'Primary color', 'dark' => 'Dark'],
        sortOrder: 50,
    )]
    /**
     * @var string
     */
    public string $style = 'light';

    #[SelectField(
        label: 'Alignment',
        options: ['center' => 'Centered', 'split' => 'Text left, buttons right'],
        sortOrder: 60,
    )]
    /**
     * @var string
     */
    public string $alignment = 'center';

    /**
     * The buttons that have both a link and a text, keyed by style.
     *
     * @return array<string, array{link: Link, text: string}>
     */
    public function getButtons(): array
    {
        return $this->filterButtons([
            'primary' => [$this->primaryLink, $this->primaryText],
            'secondary' => [$this->secondaryLink, $this->secondaryText],
        ]);
    }
}
