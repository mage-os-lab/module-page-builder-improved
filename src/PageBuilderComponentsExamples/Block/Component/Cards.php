<?php

declare(strict_types=1);

namespace MageOS\PageBuilderComponentsExamples\Block\Component;

use MageOS\PageBuilderComponents\Attribute\PageBuilderComponent;
use MageOS\PageBuilderComponents\Block\AbstractComponent;
use MageOS\PageBuilderComponents\Field\ImageField;
use MageOS\PageBuilderComponents\Field\InputField;
use MageOS\PageBuilderComponents\Field\SelectField;
use MageOS\PageBuilderComponents\Field\TextAreaField;
use MageOS\PageBuilderComponents\Field\UrlField;
use MageOS\PageBuilderComponents\Model\Value\Link;

/**
 * Grid of up to four cards (image, title, text, link) under an optional title and intro.
 *
 * The cards are fixed fields, `card1Image` to `card4LinkText`: a card with none of image, title and text set is
 * left out. The cards are worked out once per hydrate(), for the template and getColumnCount().
 */
#[PageBuilderComponent(
    name: 'mageos_cards',
    label: 'Cards',
    template: 'MageOS_PageBuilderComponentsExamples::component/cards.phtml',
    icon: 'icon-pagebuilder-column',
    sortOrder: 20,
    stylesheets: ['MageOS_PageBuilderComponentsExamples::css/components.css'],
)]
class Cards extends AbstractComponent
{
    #[InputField(label: 'Title', sortOrder: 10)]
    /**
     * @var string
     */
    public string $title = '';

    #[TextAreaField(label: 'Intro', sortOrder: 20)]
    /**
     * @var string
     */
    public string $intro = '';

    #[SelectField(label: 'Columns on Desktop', options: ['2' => '2', '3' => '3', '4' => '4'], sortOrder: 30)]
    /**
     * @var int
     */
    public int $columns = 3;

    #[ImageField(label: 'Card 1 Image', sortOrder: 100)]
    /**
     * @var string
     */
    public string $card1Image = '';

    #[InputField(label: 'Card 1 Image Alt Text', sortOrder: 105)]
    /**
     * @var string
     */
    public string $card1ImageAlt = '';

    #[InputField(label: 'Card 1 Title', sortOrder: 110)]
    /**
     * @var string
     */
    public string $card1Title = '';

    #[TextAreaField(label: 'Card 1 Text', sortOrder: 120)]
    /**
     * @var string
     */
    public string $card1Text = '';

    #[UrlField(label: 'Card 1 Link', sortOrder: 130)]
    /**
     * @var Link|null
     */
    public ?Link $card1Link = null;

    #[InputField(label: 'Card 1 Link Text', sortOrder: 140)]
    /**
     * @var string
     */
    public string $card1LinkText = '';

    #[ImageField(label: 'Card 2 Image', sortOrder: 200)]
    /**
     * @var string
     */
    public string $card2Image = '';

    #[InputField(label: 'Card 2 Image Alt Text', sortOrder: 205)]
    /**
     * @var string
     */
    public string $card2ImageAlt = '';

    #[InputField(label: 'Card 2 Title', sortOrder: 210)]
    /**
     * @var string
     */
    public string $card2Title = '';

    #[TextAreaField(label: 'Card 2 Text', sortOrder: 220)]
    /**
     * @var string
     */
    public string $card2Text = '';

    #[UrlField(label: 'Card 2 Link', sortOrder: 230)]
    /**
     * @var Link|null
     */
    public ?Link $card2Link = null;

    #[InputField(label: 'Card 2 Link Text', sortOrder: 240)]
    /**
     * @var string
     */
    public string $card2LinkText = '';

    #[ImageField(label: 'Card 3 Image', sortOrder: 300)]
    /**
     * @var string
     */
    public string $card3Image = '';

    #[InputField(label: 'Card 3 Image Alt Text', sortOrder: 305)]
    /**
     * @var string
     */
    public string $card3ImageAlt = '';

    #[InputField(label: 'Card 3 Title', sortOrder: 310)]
    /**
     * @var string
     */
    public string $card3Title = '';

    #[TextAreaField(label: 'Card 3 Text', sortOrder: 320)]
    /**
     * @var string
     */
    public string $card3Text = '';

    #[UrlField(label: 'Card 3 Link', sortOrder: 330)]
    /**
     * @var Link|null
     */
    public ?Link $card3Link = null;

    #[InputField(label: 'Card 3 Link Text', sortOrder: 340)]
    /**
     * @var string
     */
    public string $card3LinkText = '';

    #[ImageField(label: 'Card 4 Image', sortOrder: 400)]
    /**
     * @var string
     */
    public string $card4Image = '';

    #[InputField(label: 'Card 4 Image Alt Text', sortOrder: 405)]
    /**
     * @var string
     */
    public string $card4ImageAlt = '';

    #[InputField(label: 'Card 4 Title', sortOrder: 410)]
    /**
     * @var string
     */
    public string $card4Title = '';

    #[TextAreaField(label: 'Card 4 Text', sortOrder: 420)]
    /**
     * @var string
     */
    public string $card4Text = '';

    #[UrlField(label: 'Card 4 Link', sortOrder: 430)]
    /**
     * @var Link|null
     */
    public ?Link $card4Link = null;

    #[InputField(label: 'Card 4 Link Text', sortOrder: 440)]
    /**
     * @var string
     */
    public string $card4LinkText = '';

    /**
     * The cards, once worked out; null until then.
     *
     * @var list<array{
     *     image: string,
     *     imageAlt: string,
     *     title: string,
     *     text: string,
     *     link: Link|null,
     *     linkText: string
     * }>|null
     */
    private ?array $cards = null;

    /**
     * Set the fields from the block's data, and work out the cards again.
     *
     * @return void
     */
    public function hydrate(): void
    {
        parent::hydrate();
        $this->cards = null;
    }

    /**
     * The cards with an image, a title or a text, in order.
     *
     * `link` is null when the card has no link, or neither a link text nor a title to read it by. When the link
     * text is empty, the title is the link and the whole card is clickable.
     *
     * @return list<array{
     *     image: string,
     *     imageAlt: string,
     *     title: string,
     *     text: string,
     *     link: Link|null,
     *     linkText: string
     * }>
     */
    public function getCards(): array
    {
        if ($this->cards !== null) {
            return $this->cards;
        }

        $cards = [];
        foreach ($this->getCardSlots() as [$image, $imageAlt, $title, $text, $link, $linkText]) {
            $title = trim($title);
            $text = trim($text);
            if ($image === '' && $title === '' && $text === '') {
                continue;
            }
            $linkText = trim($linkText);
            // A link needs a text to read: its own, or the card's title.
            if ($link === null || $link->url === '' || ($linkText === '' && $title === '')) {
                $link = null;
                $linkText = '';
            }
            $cards[] = [
                'image' => $image,
                'imageAlt' => $image !== '' ? trim($imageAlt) : '',
                'title' => $title,
                'text' => $text,
                'link' => $link,
                'linkText' => $linkText,
            ];
        }

        return $this->cards = $cards;
    }

    /**
     * The number of grid columns on desktop: the setting, no more than the cards to show.
     *
     * @return int
     */
    public function getColumnCount(): int
    {
        return max(1, min($this->columns, \count($this->getCards())));
    }

    /**
     * The four card slots' fields: image, image alt text, title, text, link, link text.
     *
     * @return list<array{0: string, 1: string, 2: string, 3: string, 4: Link|null, 5: string}>
     */
    private function getCardSlots(): array
    {
        return [
            [
                $this->card1Image, $this->card1ImageAlt, $this->card1Title,
                $this->card1Text, $this->card1Link, $this->card1LinkText,
            ],
            [
                $this->card2Image, $this->card2ImageAlt, $this->card2Title,
                $this->card2Text, $this->card2Link, $this->card2LinkText,
            ],
            [
                $this->card3Image, $this->card3ImageAlt, $this->card3Title,
                $this->card3Text, $this->card3Link, $this->card3LinkText,
            ],
            [
                $this->card4Image, $this->card4ImageAlt, $this->card4Title,
                $this->card4Text, $this->card4Link, $this->card4LinkText,
            ],
        ];
    }
}
