<?php
/**
 * Renders the example components' real templates with sample content into one page, for Test/Screenshots/run.sh.
 *
 *     php render.php <out dir> [autoload.php]
 *
 * Writes <out dir>/index.html (each component in a <div data-shot="...">, with the module's components.css next
 * to it, which run.sh copies) and the placeholder photos it uses
 * (drawn with GD, so no stock photos are needed). autoload.php defaults to the package's or Magento's vendor.
 */

declare(strict_types=1);

use Magento\Framework\Escaper;
use Magento\Framework\Translate\InlineInterface;
use Magento\Framework\ZendEscaper;
use MageOS\PageBuilderComponents\Attribute\PageBuilderComponent;
use MageOS\PageBuilderComponents\Block\AbstractComponent;
use MageOS\PageBuilderComponents\Model\ImageSize;
use MageOS\PageBuilderComponents\Model\Value\Link;
use MageOS\PageBuilderComponentsExamples\Block\Component\CallToAction;
use MageOS\PageBuilderComponentsExamples\Block\Component\Cards;
use MageOS\PageBuilderComponentsExamples\Block\Component\Hero;
use MageOS\PageBuilderComponentsExamples\Block\Component\ImageText;
use MageOS\PageBuilderComponentsExamples\Block\Component\UspList;

$module = dirname(__DIR__, 2);
$package = dirname($module, 2);
$out = $argv[1] ?? 'page';
$autoload = $argv[2] ?? (is_file("$package/vendor/autoload.php")
    ? "$package/vendor/autoload.php"
    : dirname($package, 3) . '/vendor/autoload.php');

$loader = require $autoload;
$loader->addPsr4('MageOS\\PageBuilderComponents\\', "$package/src/PageBuilderComponents/");
$loader->addPsr4('MageOS\\PageBuilderComponentsExamples\\', "$module/");
if (!is_dir($out) && !mkdir($out, 0777, true)) {
    fwrite(STDERR, "Cannot create $out\n");
    exit(1);
}

/**
 * A soft, photo-like placeholder: a sky gradient, a sun and rolling hills in the given hues.
 */
function placeholder(string $file, int $width, int $height, array $sky, array $land, int $seed): string
{
    mt_srand($seed);
    $image = imagecreatetruecolor($width, $height);
    for ($y = 0; $y < $height; $y++) {
        $t = $y / $height;
        $color = imagecolorallocate(
            $image,
            (int) ($sky[0][0] + ($sky[1][0] - $sky[0][0]) * $t),
            (int) ($sky[0][1] + ($sky[1][1] - $sky[0][1]) * $t),
            (int) ($sky[0][2] + ($sky[1][2] - $sky[0][2]) * $t)
        );
        imageline($image, 0, $y, $width, $y, $color);
    }
    imagefilledellipse(
        $image,
        (int) ($width * (0.6 + mt_rand(0, 25) / 100)),
        (int) ($height * 0.32),
        (int) ($height * 0.22),
        (int) ($height * 0.22),
        imagecolorallocatealpha($image, 255, 246, 220, 20)
    );
    foreach ([0.55, 0.68, 0.82] as $layer => $base) {
        $shade = 1 - $layer * 0.22;
        $color = imagecolorallocate(
            $image,
            (int) ($land[0] * $shade),
            (int) ($land[1] * $shade),
            (int) ($land[2] * $shade)
        );
        $phase = mt_rand(0, 628) / 100;
        $points = [0, $height];
        for ($x = 0; $x <= $width; $x += 8) {
            $points[] = $x;
            $points[] = (int) ($height * ($base + 0.05 * sin($x / $width * 5 + $phase)
                + 0.025 * sin($x / $width * 13 + $phase * 2)));
        }
        array_push($points, $width, $height);
        imagefilledpolygon($image, $points, $color);
    }
    imagejpeg($image, $file, 88);

    return basename($file);
}

/**
 * Renders a component's template, as Magento would, with the given property values.
 *
 * @param class-string<AbstractComponent> $class
 * @param array<string, mixed> $values
 */
function render(string $class, array $values, string $module, Escaper $escaper, ImageSize $sizes): string
{
    $block = (new ReflectionClass($class))->newInstanceWithoutConstructor();
    Closure::bind(static function (AbstractComponent $block) use ($sizes): void {
        $block->imageSize = $sizes;
    }, null, AbstractComponent::class)($block);
    foreach ($values as $name => $value) {
        $block->{$name} = $value;
    }
    $template = (new ReflectionClass($class))->getAttributes(PageBuilderComponent::class)[0]->newInstance()->template;
    ob_start();
    (static function () use ($block, $escaper, $module, $template): void {
        include $module . '/view/frontend/templates/' . explode('::', $template)[1];
    })();

    return (string) ob_get_clean();
}

$escaper = new Escaper();
foreach ([
    'translateInline' => new class implements InlineInterface {
        public function getAdditionalHtmlAttribute($tagName = null)
        {
            return null;
        }

        public function isAllowed()
        {
            return false;
        }

        public function processResponseBody(&$body, $isJson = false)
        {
            return $this;
        }

        public function getParser()
        {
            return null;
        }
    },
    'escaper' => new ZendEscaper(),
] as $property => $value) {
    $reflection = new ReflectionProperty(Escaper::class, $property);
    $reflection->setValue($escaper, $value);
}

// The placeholder photos' sizes, for the images' width and height, as ImageSize measures media files.
$sizes = new class ($out) extends ImageSize {
    public function __construct(private readonly string $dir)
    {
    }

    public function get(string $url): ?array
    {
        $size = is_file("$this->dir/$url") ? getimagesize("$this->dir/$url") : false;

        return $size ? ['width' => $size[0], 'height' => $size[1]] : null;
    }
};
$link = static fn (string $url): Link => new Link($url);
$photo = static fn (string $name, int $w, int $h, array $sky, array $land, int $seed): string =>
    placeholder("$out/$name.jpg", $w, $h, $sky, $land, $seed);

$hero = $photo('hero', 1920, 1080, [[250, 214, 165], [253, 239, 222]], [96, 140, 120], 1);
$night = $photo('hero-dusk', 1920, 1080, [[40, 48, 92], [196, 120, 120]], [52, 60, 90], 2);
$cards = [
    $photo('card-1', 800, 600, [[190, 220, 245], [240, 248, 255]], [120, 170, 110], 3),
    $photo('card-2', 800, 600, [[245, 200, 180], [255, 236, 220]], [170, 110, 90], 4),
    $photo('card-3', 800, 600, [[210, 200, 240], [244, 240, 255]], [110, 100, 160], 5),
    $photo('card-4', 800, 600, [[200, 235, 225], [240, 252, 248]], [70, 140, 130], 6),
];
$side = $photo('image-text', 1200, 900, [[235, 225, 205], [250, 246, 238]], [150, 130, 100], 7);

$cardText = [
    ['Living room', 'Sofas, armchairs and side tables that make room for the whole family.'],
    ['Bedroom', 'Calm colours, natural fabrics and beds made to last.'],
    ['Dining', 'Tables and chairs for long dinners and quick breakfasts.'],
    ['Outdoor', 'Weatherproof furniture for the balcony, the patio and the garden.'],
];
$cardValues = ['title' => 'Shop by room', 'intro' => 'Find everything for the room you are working on.'];
foreach ($cards as $i => $image) {
    $n = $i + 1;
    $cardValues += [
        "card{$n}Image" => $image,
        "card{$n}Title" => $cardText[$i][0],
        "card{$n}Text" => $cardText[$i][1],
        "card{$n}Link" => $link('#'),
        "card{$n}LinkText" => 'Discover',
    ];
}

$shots = [
    'hero' => render(Hero::class, [
        'image' => $hero,
        'imageAlt' => '',
        'eyebrow' => 'New collection',
        'title' => 'Made for slow Sundays',
        'content' => '<p>Soft linen, warm oak and colours that calm down a room. Discover the summer collection.</p>',
        'primaryLink' => $link('#'),
        'primaryText' => 'Shop the collection',
        'secondaryLink' => $link('#'),
        'secondaryText' => 'Get inspired',
    ], $module, $escaper, $sizes),
    'hero-centered-light' => render(Hero::class, [
        'image' => $night,
        'imageAlt' => '',
        'eyebrow' => 'Limited time',
        'title' => 'The evening sale',
        'content' => '<p>Up to 40% off lamps, rugs and throws, until midnight.</p>',
        'primaryLink' => $link('#'),
        'primaryText' => 'See the offers',
        'position' => 'center',
        'height' => 'small',
        'textColor' => 'light',
    ], $module, $escaper, $sizes),
    'cards' => render(Cards::class, $cardValues + ['columns' => 4], $module, $escaper, $sizes),
    'cards-3' => render(Cards::class, array_diff_key($cardValues, array_flip([
        'card4Image', 'card4Title', 'card4Text', 'card4Link', 'card4LinkText',
    ])) + ['columns' => 3, 'title' => 'Inspiration', 'intro' => ''], $module, $escaper, $sizes),
    'usp-list' => render(UspList::class, [
        'title' => '',
        'items' => "Free delivery from € 50\n30 days to return\nAssembled by our team\nPay in three instalments",
    ], $module, $escaper, $sizes),
    'usp-list-grid' => render(UspList::class, [
        'title' => 'Why shop with us',
        'items' => "Free delivery from € 50\n30 days to return\n10 year warranty on frames\nShowrooms in 12 cities"
            . "\nAssembled by our team\nPay in three instalments",
        'layout' => 'grid',
    ], $module, $escaper, $sizes),
    'image-text' => render(ImageText::class, [
        'image' => $side,
        'imageAlt' => '',
        'eyebrow' => 'Our story',
        'title' => 'Crafted in small workshops',
        'content' => '<p>Every piece is made by hand by people who have done it for decades. We visit each workshop'
            . ' ourselves, and pick woods and fabrics that age well.</p>',
        'primaryLink' => $link('#'),
        'primaryText' => 'Read more',
    ], $module, $escaper, $sizes),
    'image-text-right' => render(ImageText::class, [
        'image' => $cards[3],
        'imageAlt' => '',
        'title' => 'Design your own sofa',
        'content' => '<p>Choose the size, the fabric and the legs. We build it for you in six weeks.</p>',
        'primaryLink' => $link('#'),
        'primaryText' => 'Start designing',
        'imagePosition' => 'right',
    ], $module, $escaper, $sizes),
    'call-to-action' => render(CallToAction::class, [
        'title' => 'Get 10% off your first order',
        'content' => '<p>Sign up for our newsletter: new arrivals, ideas for every room, and members-only offers.</p>',
        'primaryLink' => $link('#'),
        'primaryText' => 'Sign up',
        'secondaryLink' => $link('#'),
        'secondaryText' => 'Learn more',
        'style' => 'primary',
    ], $module, $escaper, $sizes),
    'call-to-action-split' => render(CallToAction::class, [
        'title' => 'Need help choosing?',
        'content' => '<p>Book a free video call with one of our interior advisers.</p>',
        'primaryLink' => $link('#'),
        'primaryText' => 'Book a call',
        'style' => 'dark',
        'alignment' => 'split',
    ], $module, $escaper, $sizes),
];

$html = '';
foreach ($shots as $name => $shot) {
    $html .= "<div data-shot=\"$name\">\n$shot\n</div>\n";
}
file_put_contents("$out/index.html", <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Page Builder example components</title>
    <link rel="stylesheet" href="components.css">
    <!-- A theme's base styles, in short. -->
    <style>body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; }</style>
</head>
<body>
$html
</body>
</html>

HTML);
echo implode("\n", array_keys($shots)), "\n";
