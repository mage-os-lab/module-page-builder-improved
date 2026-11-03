<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponentsExamples\Block\Component;

use MageOS\PageBuilderComponents\Attribute\PageBuilderComponent;
use MageOS\PageBuilderComponents\Block\AbstractComponent;
use MageOS\PageBuilderComponents\Field\ImageField;
use MageOS\PageBuilderComponents\Field\InputField;
use MageOS\PageBuilderComponents\Field\SelectField;
use MageOS\PageBuilderComponents\Field\UrlField;
use MageOS\PageBuilderComponents\Field\WysiwygField;
use MageOS\PageBuilderComponents\Model\Value\Link;

/**
 * Image beside a title, text and button; stacked on small screens.
 */
#[PageBuilderComponent(
    name: 'mageos_image_text',
    label: 'Image & Text',
    template: 'MageOS_PageBuilderComponentsExamples::component/image-text.phtml',
    icon: 'icon-pagebuilder-image',
    sortOrder: 40,
    stylesheets: ['MageOS_PageBuilderComponentsExamples::css/components.css'],
)]
class ImageText extends AbstractComponent
{
    use ButtonsTrait;

    #[ImageField(label: 'Image', sortOrder: 10)]
    /**
     * @var string
     */
    public string $image = '';

    #[InputField(label: 'Image Alt Text', sortOrder: 15)]
    /**
     * @var string
     */
    public string $imageAlt = '';

    #[InputField(label: 'Eyebrow', sortOrder: 20)]
    /**
     * @var string
     */
    public string $eyebrow = '';

    #[InputField(label: 'Title', sortOrder: 25)]
    /**
     * @var string
     */
    public string $title = '';

    #[WysiwygField(label: 'Text', sortOrder: 30)]
    /**
     * @var string
     */
    public string $content = '';

    #[UrlField(label: 'Button Link', sortOrder: 40)]
    /**
     * @var Link|null
     */
    public ?Link $primaryLink = null;

    #[InputField(label: 'Button Text', sortOrder: 45)]
    /**
     * @var string
     */
    public string $primaryText = '';

    #[SelectField(label: 'Image Position', options: ['left' => 'Left', 'right' => 'Right'], sortOrder: 50)]
    /**
     * @var string
     */
    public string $imagePosition = 'left';

    /**
     * The button, keyed `primary`, when it has both a link and a text.
     *
     * @return array<string, array{link: Link, text: string}>
     */
    public function getButtons(): array
    {
        return $this->filterButtons(['primary' => [$this->primaryLink, $this->primaryText]]);
    }
}
