<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponents\Test\Unit\Fixture;

use MageOS\PageBuilderComponents\Attribute\PageBuilderComponent;
use MageOS\PageBuilderComponents\Block\AbstractComponent;
use MageOS\PageBuilderComponents\Field\ConditionsField;
use MageOS\PageBuilderComponents\Field\ImageField;
use MageOS\PageBuilderComponents\Field\InputField;
use MageOS\PageBuilderComponents\Field\SelectField;
use MageOS\PageBuilderComponents\Field\TextAreaField;
use MageOS\PageBuilderComponents\Field\ToggleField;
use MageOS\PageBuilderComponents\Field\UrlField;
use MageOS\PageBuilderComponents\Field\WysiwygField;
use MageOS\PageBuilderComponents\Model\Value\Link;

/**
 * One field of each type, declared out of sort order.
 */
#[PageBuilderComponent(
    name: 'test_all_fields',
    label: 'All <Fields> & "More"',
    template: 'MageOS_PageBuilderComponents::test/all-fields.phtml',
    menuSection: 'elements',
    icon: 'icon-pagebuilder-heading',
    sortOrder: 5,
    stylesheets: ['MageOS_PageBuilderComponents::css/test/all-fields.css', 'Vendor_Module::css/other.min.css'],
)]
class AllFieldsComponent extends AbstractComponent
{
    #[WysiwygField(label: 'Content', sortOrder: 30)]
    /**
     * @var string
     */
    public string $content = '';

    #[InputField(label: 'Title', sortOrder: 10, required: true)]
    /**
     * @var string
     */
    public string $title = 'Default title';

    #[InputField(label: 'Count', sortOrder: 15)]
    /**
     * @var int
     */
    public int $count = 3;

    #[TextAreaField(label: 'Intro', sortOrder: 20)]
    /**
     * @var string
     */
    public string $intro = '';

    #[ImageField(label: 'Image', sortOrder: 40)]
    /**
     * @var string
     */
    public string $image = '';

    #[UrlField(label: 'Link', sortOrder: 50)]
    /**
     * @var Link|null
     */
    public ?Link $link = null;

    #[UrlField(label: 'Plain link', sortOrder: 55)]
    /**
     * @var string
     */
    public string $plainLink = '';

    #[SelectField(label: 'Layout', options: ['left' => 'Image left', 'right' => 'Image right'], sortOrder: 60)]
    /**
     * @var string
     */
    public string $layout = 'left';

    #[ToggleField(label: 'Overlay', sortOrder: 70)]
    /**
     * @var bool
     */
    public bool $overlay = true;

    #[ConditionsField(label: 'Products', sortOrder: 80)]
    /**
     * @var string
     */
    public string $products = '';

    /**
     * Not a field: no attribute.
     *
     * @var string
     */
    public string $notAField = 'untouched';
}
