# Changelog

All notable changes to this module are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versions follow
[Semantic Versioning](https://semver.org/). Releases are cut from git tags.

## [Unreleased]

### Added

- Components: Hero (`mageos_hero`), Cards (`mageos_cards`), USP List (`mageos_usp_list`), Image & Text
  (`mageos_image_text`) and Call to Action (`mageos_call_to_action`).
- CSS framework agnostic templates: only the module's own BEM classes (`mageos-pbc-*`), styled by a plain
  stylesheet (`css/components.css`) that works with Luma, Hyvä and other themes, themable with
  `--mageos-pbc-*` custom properties.
- Every component declares `css/components.css` in its `stylesheets`, so the stage preview shows it styled.
- Images: `width` and `height` from the image file when it can be measured (no layout shift). The Hero's image
  loads with `fetchpriority="high"` unless its new **Load with High Priority (First on Page)** setting is turned
  off; the Cards and Image & Text images load lazily and decode asynchronously.
- Cards: an alt text per card image (`card1ImageAlt` to `card4ImageAlt`); without one the image is decorative.
