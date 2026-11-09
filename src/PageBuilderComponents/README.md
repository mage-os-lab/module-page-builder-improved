# MageOS Page Builder Components

Declare a Page Builder content type with PHP attributes on a block class. The module builds its content type
config, its edit form, its stage preview and its storefront rendering, so there is no `content_type/*.xml`,
`ui_component/*.xml`, layout handle, preview or master template, or JavaScript to write.

```php
#[PageBuilderComponent(name: 'acme_hero', label: 'Hero', template: 'Acme_Theme::component/hero.phtml')]
class Hero extends AbstractComponent
{
    #[ImageField(label: 'Image', sortOrder: 10)]
    /**
     * @var string
     */
    public string $image = '';

    #[InputField(label: 'Title', sortOrder: 20, required: true)]
    /**
     * @var string
     */
    public string $title = '';

    #[UrlField(label: 'Button link', sortOrder: 30)]
    /**
     * @var Link|null
     */
    public ?Link $link = null;
}
```

```php
<?php /** @var \Acme\Theme\Block\Component\Hero $block */ /** @var \Magento\Framework\Escaper $escaper */ ?>
<h2><?= $escaper->escapeHtml($block->title) ?></h2>
```

## Requirements

- Magento Open Source 2.4.8 or 2.4.9, or Mage-OS 1.3 to 3.x, with Page Builder 2.2.6 or 2.2.7 (see the package
  README)
- PHP 8.3 or 8.4, or 8.5 on Magento 2.4.9 / Mage-OS 3.x

## Installation

```
composer require mage-os/module-page-builder-improved
bin/magento module:enable MageOS_PageBuilderComponents
bin/magento setup:upgrade
```

The module adds nothing to the database.

## Example components

`MageOS_PageBuilderComponentsExamples`, in the same package, has five ready-to-use components built on this
module: Hero, Cards, USP List, Image & Text and Call to Action. Enable it to use them as they are, or read them
as examples of how to write your own.

## Removing a component

To leave out a component another module registers, set it to null in your module's `etc/di.xml` (with that
module in your `module.xml` sequence). Content already saved with it is no longer rendered.

```xml
<type name="MageOS\PageBuilderComponents\Model\ComponentRegistry">
    <arguments>
        <argument name="components" xsi:type="array">
            <item name="mageos_usp_list" xsi:type="null"/>
        </argument>
    </arguments>
</type>
```

## Writing a component

1. Extend `MageOS\PageBuilderComponents\Block\AbstractComponent`.
2. Put `#[PageBuilderComponent]` on the class:

   | Argument      | Default                  | Notes                                                           |
   |---------------|--------------------------|-----------------------------------------------------------------|
   | `name`        | (required)               | Content type name: `a-z`, `0-9`, `_`. **Prefix it with your vendor**: a component named like an existing content type would be merged into it. |
   | `label`       | (required)               | Shown in the Page Builder panel and as the form's title.        |
   | `template`    | (required)               | `Vendor_Module::path.phtml`, in `view/frontend/templates`.      |
   | `menuSection` | `add_content`            | Panel section: `layout`, `elements`, `media`, `add_content`, or your own. |
   | `icon`        | `icon-pagebuilder-block` | CSS class(es) of the panel icon, e.g. `icon-pagebuilder-banner`: letters, digits, `_`, `-`, separated by single spaces. |
   | `sortOrder`   | `100`                    | 0 to 127 (Page Builder's schema types it as a byte).            |
   | `stylesheets` | `[]`                     | Stylesheets the stage preview loads after the theme's, as `Vendor_Module::css/file.css` (in `view/frontend/web`). See [Stage preview](#stage-preview). |

3. Put one field attribute on each public property the editor fills in. Every field takes `label`,
   `sortOrder` (default 10) and `required` (default false). The property's default value is the field's
   default in the form, and the value the template gets when the field was never set.
4. Register the class in your module's `etc/di.xml`, under the same name:

   ```xml
   <type name="MageOS\PageBuilderComponents\Model\ComponentRegistry">
       <arguments>
           <argument name="components" xsi:type="array">
               <item name="acme_hero" xsi:type="string">Acme\Theme\Block\Component\Hero</item>
           </argument>
       </arguments>
   </type>
   ```

   and add `MageOS_PageBuilderComponents` to your `module.xml` sequence.

Put the field attribute **above** the property's docblock. Magento's coding standard only finds a docblock
directly before the property, and PHP reads both either way.

A registered class that is not a valid component throws an `InvalidComponentException` in developer mode,
with a message saying what to fix. In default and production mode it is logged as critical and left out, so
one broken component does not take down every page that renders a widget.

The definitions are read from the classes once and kept in the config cache, like the content types and forms
made from them. After changing a component's attributes, clean the config cache (`bin/magento cache:clean
config`).

## Stage preview

The stage shows each component rendered by the storefront theme of the store being edited, in an iframe, so it
looks as it does on the storefront and the admin's styles do not leak into it. The iframe loads:

1. the theme's stylesheets: `css/styles.css` for a Hyvä theme, otherwise Luma's `css/styles-m.css` and
   `css/styles-l.css` (the latter from 768px wide, as on the storefront);
2. then every stylesheet in the component's `stylesheets`, in order.

A component whose styles are not in the theme's stylesheet (a module's own CSS file, loaded on the storefront by
layout XML) declares that file, so the preview has it too:

```php
#[PageBuilderComponent(
    name: 'acme_hero',
    label: 'Hero',
    template: 'Acme_Theme::component/hero.phtml',
    stylesheets: ['Acme_Theme::css/hero.css'],
)]
```

The module does not add `stylesheets` to storefront pages: load them there as you would any stylesheet.

A theme is taken for a Hyvä theme when it, or a theme it inherits from, has a code starting with `Hyva/`. A Hyvä
theme without such a parent (one you add to Hyvä's `hyvaBaseThemes`) must be added here too:

```xml
<type name="MageOS\PageBuilderComponents\Model\Stage\ComponentRenderer">
    <arguments>
        <argument name="hyvaThemes" xsi:type="array">
            <item name="Acme/hyva" xsi:type="boolean">true</item>
        </argument>
    </arguments>
</type>
```

The iframe is sandboxed without scripts: the preview shows the component's HTML and CSS, but its JavaScript
(Alpine.js, sliders, ...) does not run, so it shows the state before any script runs. It cannot take the mouse
either, which stays the stage's for dragging and selecting.

## Fields

| Attribute         | Property type      | The template gets                                                              |
|-------------------|--------------------|--------------------------------------------------------------------------------|
| `InputField`      | `string` or `int`  | The text.                                                                      |
| `TextAreaField`   | `string`           | The text, line breaks included.                                                |
| `WysiwygField`    | `string`           | HTML, already run through the CMS block filter (`{{media}}`, `{{widget}}`, ...). Print it unescaped. |
| `ImageField`      | `string`           | The image's full URL for the current store, or `''`. The path under `pub/media` is stored, so content survives a domain change. jpg, jpeg, png or gif, as core's upload controller accepts. A stored value that is not a media path, a same-site path or an http(s) URL gives `''`. Use `$block->getImageSize($url)` for its width and height. |
| `UrlField`        | `?Link` or `string`| A `Link` (`url`, `openInNewTab`, `getTargetAttributes()`) or null; or just the URL. Product, category and CMS page links resolve through their canonical URL rewrite in the current store. A typed URL is kept only with `http:`, `https:`, `mailto:`, `tel:` or no scheme (a relative path, a query, a `#fragment`); `javascript:`, `data:`, a protocol-relative `//host` and any other gives no link. |
| `SelectField`     | `string` or `int`  | One of `options` (value => label). A stored value no longer among them gives the default. |
| `ToggleField`     | `bool`             | The switch.                                                                    |
| `ConditionsField` | `string`           | Catalog product conditions as JSON, the form `ProductsList` takes as `conditions_encoded`. |

`type`, `template`, `id`, `name`, `store_id`, `type_name`, `conditions`, `cache_lifetime`, `cache_key`,
`cache_tags`, `module_name`, `data`, `appearance`, `css_classes`, `margins_and_padding` and `html` are
reserved: they mean something else to the widget directive or to Page Builder.

## Column gap and layout

Column Gap, and the other layout settings of core's Columns, Row and Column elements, live in their own module,
`MageOS_PageBuilderLayout`, with the stage's Tablet view.

## Page Builder panel

The reworked Page Builder panel (wider, collapsible, with "Add element" and "Current tree" tabs) lives in its own
module, `MageOS_PageBuilderUi`, with the editor's other UI changes.

## How it works

- **Content type.** `Plugin\AddContentTypeFiles` gives Page Builder's config reader one generated
  `content_type` file per component. It is validated, merged, converted and cached like a module's own file.
  It is merged first, so a module can still change it with a `content_type/<name>.xml` of its own.
- **Form.** `Plugin\AddFormFiles` gives the UI component reader a generated `mageos_pbc_<name>_form`, which
  extends core's `pagebuilder_base_form` (Advanced fieldset included). `Plugin\AddFormLayoutHandle` gives it
  the layout handle Page Builder loads it by.
- **Storage.** Like core's Products content type, a component is saved as a `{{widget}}` directive:
  `type` is the block class, and there is one parameter per field. Every value is encoded
  (`Model\DirectiveValueCodec`, and the same codec in JavaScript), so a `"`, `%22`, `{`, `}`, `<` or `&` in a
  field cannot end its value, set another parameter or break the content around it.
- **Storefront.** `Plugin\AddWidgetConfig` declares each component as a widget to
  `Widget::getWidgetByClassType()`, which is what lets core's widget filter render its directive. The lists of
  widgets leave the components out, so they are not offered in the editor's "Insert Widget" dialog or in
  Content > Widgets, which would not store their values encoded. The block decodes and resolves the values into its properties before
  the template renders. The template is always the one the attribute names: a `template` parameter in a
  directive is ignored.
- **Stage preview.** Core's preview controller calls `Model\Stage\ComponentRenderer` (role
  `mageos_component`) in the storefront area. The request names a registered component, never a class or a
  template. The HTML is shown in a sandboxed iframe with the theme's and the component's stylesheets (see
  [Stage preview](#stage-preview)); `js/utils/preview-frame` keeps each iframe as tall as its content, also when
  images load or the stage changes width. A preview asks for its HTML 250ms after the last change to its values,
  with one request at a time, and a removed component's request is aborted. Outside developer mode a rendering error shows a generic message; the
  exception is logged.
  While a preview shows (next to) nothing, as a component just added with its fields empty does,
  `js/utils/empty-preview` swaps in a placeholder telling the editor to click the gear icon; clicking the
  placeholder opens the form too.

## Development

The module is part of `mage-os/module-page-builder-improved`: `composer install` and `composer test` (unit
tests, PHPStan level 8, PHP-CS-Fixer, PHPCS) run from the [package root](../README.md), for every module. From
this module's directory:

```
node Test/Js/codec.test.js    # the JavaScript codec against the PHP codec's cases
node Test/Js/provider.test.js path/to/magento/module-rule   # conditions fields' values on save
node Test/Js/field-values.test.js
node Test/Js/preview.test.js  # preview requests: debounce, one at a time, abort on destroy
node Test/Js/widget-directive.test.js path/to/magento/module-page-builder
# Layout tests in headless Chrome: preview sizing and the empty preview (Test/Js/*.html)
docker run --rm -e NODE_PATH=/home/pptruser/node_modules -v "$PWD":/module:ro \
    ghcr.io/puppeteer/puppeteer:24.10.0 node /module/Test/Js/browser.test.js
```

`Test/Unit/_files/codec-vectors.json` holds the codec cases both languages are tested against.
`Test/Unit/_files/stage-directive.txt` is a directive written by the stage's converter: the JavaScript test
checks the converter writes it, and `ValueHydratorTest` checks PHP reads it back.

## Attribution

The initial idea of this component system (declaring a Page Builder content type with PHP attributes on a block
class, and generating its form and configuration instead of writing XML) comes from
[SushyDev's Easy Page Builder](https://github.com/SushyDev/module-easy-page-builder). This module is an
independent implementation of that idea; see the [changelog](CHANGELOG.md) for how it differs.

## License

[OSL-3.0](../LICENSE)
