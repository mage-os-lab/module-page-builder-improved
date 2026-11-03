# MageOS Page Builder Layout

Layout settings for core Page Builder's **Columns**, **Row** and **Column** elements: gaps, flex and grid layouts,
justify and align. And a **Tablet** view on the stage, next to Desktop and Mobile, so each setting can differ per
viewport. Plain CSS on the storefront, for Luma and Hyvä alike.

## Viewports

The stage's viewport switcher gets **Tablet** between Desktop and Mobile (`etc/view.xml`). Core already defines
the tablet breakpoint, for the product carousel; the module puts it on the stage. Page Builder writes what is set
in each view in that viewport's media query:

| View    | Storefront widths | Stage width (full screen, content snapshot) |
|---------|-------------------|---------------------------------------------|
| Desktop | all, unless Tablet or Mobile set something else | the editor's |
| Tablet  | 768 to 1023 px    | 1023 px                                     |
| Mobile  | up to 767 px      | 768 px (core)                               |

In the Tablet and Mobile views, a setting left at **Default** keeps the Desktop value. Core's elements with a
Mobile setting (the min height of rows, columns, banners, slides, sliders, tabs and tab items) get the same
setting for Tablet.

## Columns

The Columns element's settings get a **Layout** tab:

| Setting          | Values                       | Does                                                         |
|------------------|------------------------------|--------------------------------------------------------------|
| Column Gap       | 0 to 64 px                   | Space between the columns of a line.                         |
| Row Gap          | Same as Column Gap, 0–64 px  | Space between wrapped or stacked columns, and between lines. |
| Columns per Line | the stage's widths, 1 to 6   | Equal columns that wrap onto new lines, e.g. 4 on Desktop, 2 on Tablet, 1 on Mobile. |
| Justify Columns  | start, center, end, space between/around/evenly | Where the columns of a line they don't fill go, e.g. the last line with Columns per Line. |

The columns keep the widths set on the stage, in proportion, next to a gap; below 768 px they stack, as core
lays them out, with the row gap between them. The stage shows the columns without these settings, because core's
column resizing measures their widths: check them in Preview.

## Row and Column

Rows and columns get a **Content Layout** tab, for the elements inside them:

| Setting         | Values                                         |
|-----------------|------------------------------------------------|
| Layout          | Default (Page Builder's stacking), Stacked, Side by side, Side by side reversed, Grid |
| Grid Columns    | 1 to 6 equal columns, with the Grid layout      |
| Justify Content | start, center, end, space between/around/evenly: along the line (across when side by side, down when stacked, where core's Vertical Alignment applies) |
| Align Items     | stretch, start, center, end, baseline: across the line |
| Wrap            | keep on one line, wrap: for Side by side       |
| Gap             | 0 to 64 px between the elements                |

A typical use: buttons or images side by side on Desktop and Tablet, Stacked on Mobile. These settings show on the
storefront and in Preview, not on the stage.

## How it works

- **Stored per viewport.** Page Builder writes an element's styles once per viewport, in a `<style>` block, the
  Tablet and Mobile ones in their media queries; the module's settings are styles
  (`view/adminhtml/pagebuilder/content_type/*.xml`), flagged for the Tablet and Mobile views in the forms
  (`breakpoints`). The Tablet and Mobile forms are core's Mobile ones, for Tablet, and new ones for Columns.
- **Columns** settings are custom properties on the column group (`js/converter/style/custom-property`,
  `columns-per-line`), which `view/frontend/web/css/layout.css` applies to its column lines. The CSS only touches
  column groups saved with the module (`data-mageos-layout`); without settings, it lays them out as core does.
  A column group inside another one doesn't inherit its settings.
- **Content layout** settings are the CSS properties themselves (`display`, `flex-direction`,
  `grid-template-columns`, `justify-content`, `align-items`, `flex-wrap`, `gap`), on the element that holds the
  content (a column; a row's inner element, or the row for full bleed). They win over core's (flex, stacked,
  Vertical Alignment). Each is also written as a custom property (`--mageos-justify-content`, ...), which
  `js/property/custom-property` reads back, so a setting is never mistaken for core's value of the same
  property. An empty setting writes nothing. A hidden row stays hidden.
- **Only the options' values.** The converters write a setting, and read it back, only when its value is one of
  the setting's options (`js/converter/style/values`, the values of `Model/Config/Source`): content pasted or
  imported with anything else in a style (`;`, `}`, `url(`, `expression(`) writes nothing for that setting.

## Development

```
# From the package root (app/code/MageOS): unit tests, PHPStan, PHP-CS-Fixer, PHPCS for every module
composer test
node PageBuilderLayout/Test/Js/converters.test.js
# Layout in headless Chrome (Test/Js/layout.html), with core's own style code; see Test/Js/browser.test.js
docker run --rm -e NODE_PATH=/home/pptruser/node_modules -v /path/to/magento:/m:ro \
    -e PAGE_BUILDER_WEB=/m/vendor/magento/module-page-builder/view/adminhtml/web -e LIB_WEB=/m/lib/web \
    -v "$PWD/PageBuilderLayout":/module:ro ghcr.io/puppeteer/puppeteer:24.10.0 node /module/Test/Js/browser.test.js
```

`Test/Js/_files/config.json` is the module's styles as core's config converter reads them, with the viewports'
media queries: `ViewportTest` checks it; run it with `MAGEOS_UPDATE_FIXTURES=1` to update it.

## License

[OSL-3.0](../LICENSE)
