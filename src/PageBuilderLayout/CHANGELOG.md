# Changelog

All notable changes to this module are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versions follow
[Semantic Versioning](https://semver.org/). Releases are cut from git tags.

## [Unreleased]

### Added

- **Tablet view** on the stage's viewport switcher (768 to 1023 px on the storefront), with Tablet forms for
  core's elements that have a Mobile one.
- **Columns: Layout settings**, per viewport: Column Gap, Row Gap, Columns per Line and Justify Columns. Column Gap
  was a single, all-viewport setting of `MageOS_PageBuilderComponents` (`data-column-gap`, never released): it
  moved here, as a per-viewport setting, and content saved with it needs its gap set again.
- **Row and Column: Content Layout settings**, per viewport: stacked, side by side (flex) or grid layout, grid
  columns, justify content, align items, wrap and gap.
