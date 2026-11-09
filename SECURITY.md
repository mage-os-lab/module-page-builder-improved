# Security policy

## Supported versions

| Version         | Supported |
|-----------------|-----------|
| Latest release  | yes       |
| Older releases  | no: upgrade to the latest release |

## Reporting a vulnerability

Please don't open a public issue for a security problem. Report it privately, in either of these ways:

- **Email security@mage-os.org** (preferred). It reaches the Mage-OS security team, who follow up with the
  maintainers, so a report is handled even when a maintainer is unavailable. Include a concise title, the
  affected version, and the steps to reproduce it.
- **GitHub's private advisory flow**:
  1. Go to https://github.com/mage-os-lab/module-page-builder-improved/security/advisories/new
  2. Give it a concise title, and a description with the steps to reproduce it.
  3. Submit. The maintainers are notified privately.

## What to expect

- An acknowledgement within five working days.
- A triage and severity assessment within ten working days.
- A fix or a published advisory within thirty days of the report, depending on severity.
- Coordinated disclosure. The reporter is credited in the release notes unless they prefer not to be.

## Scope

In scope, in any module of this package:

- XSS through component field values, the stage preview or the editor UI.
- Directive injection: a field value that ends its widget directive parameter and sets others.
- The stage preview rendering a block class or template that is not a registered component.
- Admin authorization bypass on the preview or any other admin request the modules add.

Out of scope:

- Issues in Magento / Mage-OS core, including core Page Builder, or in unrelated third-party modules.
- Findings that need admin access the merchant already granted (an admin can edit CMS content by design).
- Social engineering, physical attacks, denial of service by volume.
