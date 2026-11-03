# Changelog

All notable changes to this module are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versions follow
[Semantic Versioning](https://semver.org/). Releases are cut from git tags; the tag is the source of truth
for the version (composer.json carries no version field).

**Origin.** The initial idea of the component system comes from
[SushyDev's Easy Page Builder](https://github.com/SushyDev/module-easy-page-builder) (`SushyDev_EasyPageBuilder`).
This module is an independent implementation of it; the "compared with EasyPageBuilder" notes below list where
it differs.

## [Unreleased]

### Added

- **Placeholder for an empty preview**: a component whose preview shows nothing (one just added, its fields
  empty, used to be 0px tall on the stage) shows "<Label> has no content yet. Click the gear icon to edit it.",
  and opens its form when clicked.

- Page Builder components declared with `#[PageBuilderComponent]` on a subclass of
  `Block\AbstractComponent`, registered on `Model\ComponentRegistry`.
- Fields: `InputField`, `TextAreaField`, `WysiwygField`, `ImageField`, `UrlField`, `ConditionsField`, and
  new compared with EasyPageBuilder: `SelectField` and `ToggleField`. Every field takes `required`, and
  takes its default from the property's default value.
- Each field is hydrated into its typed property before the template renders: decoded, images to their
  URL in the current store, links to a `Model\Value\Link` resolved through the URL rewrites, WYSIWYG HTML
  through the CMS block filter.

- A component registered as `xsi:type="null"` is left out, so a project can remove one another module
  registers.
- **Per-component stylesheets**: `stylesheets` on `#[PageBuilderComponent]` (`Vendor_Module::css/file.css`)
  are loaded in the stage preview after the theme's.
- `AbstractComponent::getImageSize()` (`Model\ImageSize`): an image's width and height from the file in the
  media directory, for `<img width height>`.

### Security

- **The stage preview iframe is sandboxed** (`allow-same-origin`, no `allow-scripts`): scripts in a
  component's HTML do not run in the admin.
- **Links only keep a safe scheme**: `http:`, `https:`, `mailto:`, `tel:` or none. `javascript:`, `data:`,
  `vbscript:`, a protocol-relative `//host` and the like give no link, also with mixed case, leading
  whitespace or control characters.
- **Image values** that are not a media path, a same-site path or an http(s) URL give no image.
- A preview error shows the exception's message in developer mode only; it is always logged.
- The icon and stylesheets of a component are validated.

### Fixed

- **Components are no longer offered in "Insert Widget" or Content > Widgets**, which stored their values
  unencoded, showed image fields as free text, and could hide a core widget with the same label. A stored
  directive still renders: the components are declared to core's lookup by type only.
- **Stage preview requests are debounced** (250ms), run one at a time per preview with the latest values, and
  a removed component's request is aborted, so its answer no longer updates it.
- The preview iframes are sized from a load listener alone: no MutationObserver on the whole admin page.
- **Generated config XML escapes every value**, keeps line breaks in attributes, and drops characters XML
  cannot hold, so any label, default or option makes well-formed XML that reads back the text.
- The component definitions are kept in the config cache instead of being read with reflection on every
  request; the widget declarations are built once per request.
- The widget description is translatable.

### Changed (compared with EasyPageBuilder)

- **Content types reach Page Builder through its config reader**, as generated `content_type` files, rather
  than being added to the stage config after it is built. Core validates, converts and caches them, and a
  module can change one with an XML file of its own.
- **Every field value is encoded in the directive.** Input, text area, image and URL values were written as
  they were, so a `"` or `%22` (core's tokenizer URL-decodes the parameters) ended the value and could set
  other parameters, `type` and `template` among them.
- **The template is the one the attribute declares.** It was read from the directive's `template`
  parameter.
- **The stage preview renders registered components only.** The preview request named the block class and
  the template to render, and any class and template were rendered.
- **Images are stored as their path under the media directory**, not their full URL.
- **Links to CMS pages resolve**, and "Open in new tab" is kept. Product and category links resolve through
  their URL rewrite in the current store, without loading the entity.
- The default menu section is `add_content`. It was `content`, which Page Builder does not have, so the
  component appeared in no section.
- **The stage preview has its content's height.** EasyPageBuilder sized the iframe once, on a script inside it.
  Sizing it from the stage only worked if the iframe was already in the page when the preview request returned;
  otherwise it stayed at the browser's 150px default and clipped the component. Every preview iframe is now
  sized when it loads, however late Knockout inserts it, and again when its body changes size (images,
  fonts, stage width). Its height follows its body, not the root element, so it shrinks as well as grows. The
  iframe body has no margin, so nothing is cut off.
- **Conditions fields are listed by the form.** The form provider read every `parameters[...]` input in the
  form data as a conditions field; the generated form now names its conditions fields, and the provider
  turns only those into their value. A combination left without conditions, also one holding only empty
  combinations, is cleared so the rule model can load the tree.
- An invalid component throws in developer mode, and is logged and left out otherwise.
- Field values are no longer duplicated into `data-*` attributes.
- **The stage preview is an iframe for every theme**, with the theme's stylesheets (Hyvä's `styles.css`, or
  Luma's `styles-m.css` and `styles-l.css`), so it looks like the storefront and the admin's styles do not
  leak in. Hyvä is detected from the store's theme and its parents, not from `Hyva_Theme` being enabled.
- Product, category and CMS page links take the canonical URL rewrite (not a redirect, not a category path),
  and each is looked up once per request.
- `ImageField` offers jpg, jpeg, png and gif, what core's upload controller accepts (webp and svg uploads
  always failed).
- A component re-hydrates when its data changes after it hydrated.
- `module.xml` and `composer.json` declare Magento_Catalog, Magento_CatalogWidget and Magento_Rule.
