<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponentsExamples\Block\Component;

use MageOS\PageBuilderComponents\Attribute\PageBuilderComponent;
use MageOS\PageBuilderComponents\Block\AbstractComponent;
use MageOS\PageBuilderComponents\Field\ImageField;
use MageOS\PageBuilderComponents\Field\InputField;
use MageOS\PageBuilderComponents\Field\SelectField;
use MageOS\PageBuilderComponents\Field\ToggleField;
use MageOS\PageBuilderComponents\Field\UrlField;
use MageOS\PageBuilderComponents\Field\WysiwygField;
use MageOS\PageBuilderComponents\Model\Value\Link;

/**
 * Full-width hero: background image, eyebrow, title, text and up to two buttons.
 *
 * The image loads with high priority (`fetchpriority="high"`), as a hero is usually the largest thing at the top
 * of the page. For a hero further down, turn `highPriority` off so it does not compete with the images above it.
 * It is never lazy loaded: a hero is rarely far below the fold, and lazy loading one that is in view delays it.
 */
#[PageBuilderComponent(
    name: 'mageos_hero',
    label: 'Hero',
    template: 'MageOS_PageBuilderComponentsExamples::component/hero.phtml',
    icon: 'icon-pagebuilder-banner',
    sortOrder: 10,
    stylesheets: ['MageOS_PageBuilderComponentsExamples::css/components.css'],
)]
class Hero extends AbstractComponent
{
    use ButtonsTrait;

    #[ImageField(label: 'Background Image', sortOrder: 10)]
    /**
     * @var string
     */
    public string $image = '';

    #[InputField(label: 'Image Alt Text', sortOrder: 15)]
    /**
     * @var string
     */
    public string $imageAlt = '';

    #[ToggleField(label: 'Load with High Priority (First on Page)', sortOrder: 17)]
    /**
     * @var bool
     */
    public bool $highPriority = true;

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

    #[UrlField(label: 'Primary Button Link', sortOrder: 40)]
    /**
     * @var Link|null
     */
    public ?Link $primaryLink = null;

    #[InputField(label: 'Primary Button Text', sortOrder: 45)]
    /**
     * @var string
     */
    public string $primaryText = '';

    #[UrlField(label: 'Secondary Button Link', sortOrder: 50)]
    /**
     * @var Link|null
     */
    public ?Link $secondaryLink = null;

    #[InputField(label: 'Secondary Button Text', sortOrder: 55)]
    /**
     * @var string
     */
    public string $secondaryText = '';

    #[SelectField(
        label: 'Text Position',
        options: ['left' => 'Left', 'center' => 'Center', 'right' => 'Right'],
        sortOrder: 60,
    )]
    /**
     * @var string
     */
    public string $position = 'left';

    #[SelectField(
        label: 'Height',
        options: ['small' => 'Small', 'medium' => 'Medium', 'large' => 'Large', 'screen' => 'Full screen'],
        sortOrder: 70,
    )]
    /**
     * @var string
     */
    public string $height = 'medium';

    #[SelectField(label: 'Text Color', options: ['dark' => 'Dark', 'light' => 'Light'], sortOrder: 80)]
    /**
     * @var string
     */
    public string $textColor = 'dark';

    #[ToggleField(label: 'Shade the Image Behind the Text', sortOrder: 90)]
    /**
     * @var bool
     */
    public bool $overlay = true;

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
