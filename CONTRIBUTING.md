# Contributing

Thanks for wanting to help. This package is open source under OSL-3.0 and welcomes pull requests, bug reports and
documentation fixes.

## Before you open an issue

- Search [existing issues](https://github.com/mage-os-lab/module-page-builder-improved/issues) first.
- For a bug, give the package version, the Magento / Mage-OS version, the PHP version, the theme and the steps to
  reproduce it. The **Bug report** template asks for all of these.
- For a security vulnerability, don't open a public issue: see [`SECURITY.md`](SECURITY.md).

## Before you open a pull request

1. Open or comment on the issue the change covers, so we agree on its scope before code moves.
2. Branch off `main`. `feat/<topic>` or `fix/<topic>` is fine, nothing strict.
3. Write commit messages as [Conventional Commits](https://www.conventionalcommits.org/): `feat:`, `fix:`,
   `refactor:`, `test:`, `chore:`, `docs:`, with the module as scope when it is one module, such as
   `fix(layout): ...` or `feat(components): ...`. Releases and the changelog are generated from them by
   [release-please](https://github.com/googleapis/release-please), so a `feat:` or `fix:` message is what users read
   in the release notes.
4. Every PHP file starts with `declare(strict_types=1);`. Match the surrounding style.
5. Run the checks from the package's root before pushing:
   ```bash
   composer install
   composer test        # PHPUnit, PHPStan (level 8), PHP-CS-Fixer, PHPCS
   npm install --no-save knockout@3.5.1 requirejs@2.3.7 underscore@1.13.8 puppeteer@24.10.0
   composer test:js     # the JavaScript tests, in Node
   node src/PageBuilderComponents/Test/Js/browser.test.js
   node src/PageBuilderLayout/Test/Js/browser.test.js
   ```
   CI runs the same checks, plus installation, DI compilation and Magento's coding standard on every supported
   Magento version.
6. A change to the editor or the storefront output: add or update the module's screenshots (each module's README
   says how), and attach them to the pull request.
7. Keep pull requests small and about one thing.

## Adding a component to the examples

`MageOS_PageBuilderComponentsExamples` is read as much as it is used: a new component there should show something
the others don't, keep its templates free of any CSS framework (the module's own `mageos-pbc-*` classes, styled in
`css/components.css`), and come with a unit test and a screenshot.

## Releases

Versions follow [Semantic Versioning](https://semver.org/). release-please keeps a release pull request open on
`main` with the next version and its changelog; merging it tags the release, and Packagist picks the tag up.

## Attribution

The idea of declaring Page Builder components with PHP attributes on a block class comes from
[SushyDev's Easy Page Builder](https://github.com/SushyDev/module-easy-page-builder). See the
[README](README.md#attribution).

## Code of conduct

Participation is governed by the [Contributor Covenant 2.1](CODE_OF_CONDUCT.md).

## License of contributions

By opening a pull request, you agree that your contribution is licensed under
[OSL-3.0](LICENSE), the license of this package.
