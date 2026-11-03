# Changelog

All notable changes to this module are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versions follow
[Semantic Versioning](https://semver.org/). Releases are cut from git tags; the tag is the source of truth
for the version (composer.json carries no version field).

## [Unreleased]

### Fixed

- "Save page" saves the form its Page Builder is in (a modal's form, in a modal), instead of the first Save in
  the page.
- Reopening in full screen after "Save page" only happens on the page saved from: after a save that failed its
  validation, the next page, block or product opened no longer jumps into full screen.
- The Templates menu no longer stacks document handlers when toggled fast; Escape closes it.
- The panel's handlers on the stage are replaced, not added to, when it renders again.

### Changed

- Keyboard and screen readers: the panel's and the edit form's tabs are WAI-ARIA tabs (arrow keys, Home, End,
  roving tabindex); the tree is a plain list instead of an ARIA tree without its keys; the full screen toggle is
  a button.
- "Edit with Page Builder" serialises its config with `JsonHexTag`, through a view model.
- A test fails when core's `page-builder.html` or `panel.html` changes, so the module's copies get re-synced.

### Added

- **Reworked Page Builder panel**, moved here from `MageOS_PageBuilderComponents`: collapsible, with two tabs.
  "Add element" holds core's elements; "Current tree" shows the elements on the stage as a tree, with actions to
  move them up or down, duplicate, edit and remove them, and highlights an element on the stage from its node.

- **A fresh new look for the editor**:
  - The panel is light and 300 px wide. "Add element" is a grid of tiles under section headings, with every
    section open. Collapsed, the panel is a 48 px strip with one icon per tab.
  - "Current tree" is a list view: a selected node, a node marked while its element is hovered on the stage,
    "Expand all" and "Collapse all", an empty state, and an "Add element" button under the tree.
  - The elements' toolbar on the stage, the hover outline and the header (viewport switcher, template buttons,
    full screen bar) share the same light, flat style.

- **Edit form as a sidebar with tabs**: an element's edit form is a light 480 px sidebar on the right; its
  fieldsets ("Content", "Advanced", ...) are tabs, like the panel's, with a red dot on a tab holding an
  invalid field. Labels sit above their fields.

- **Header**: "Apply Template" and "Save as Template" in one "Templates" menu, and a primary "Save page" button
  that clicks the page's Save; saved from full screen, Page Builder opens in full screen again after the reload.

- **Edit with Page Builder** in the CMS pages' and blocks' grids, after "Edit": opens the edit page with Page
  Builder in full screen.

- **Screenshot harness** (`Test/Screenshots`): renders the panel with core's compiled admin styles next to a
  stand-in stage, in headless Chrome, without a live admin.
