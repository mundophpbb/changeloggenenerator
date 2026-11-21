# Changelog

## [1.17.4]

### Added

- Delete a saved or published release from the editor. The standard phpBB confirmation page is shown first; the release, its notes and its edit history are removed, it disappears from the public changelog and the action is recorded in the admin log.

## [1.17.3]

### Fixed

- Use utf8_htmlspecialchars() for the public page title and breadcrumbs.
- Stream the published history export through php://output instead of echo.
- Indent PHP, JavaScript, CSS, JSON and template files with tabs, following the phpBB coding guidelines.
- Rename LICENSE.txt to license.txt and phpunit.xml.dist to phpunit.xml so the official EPV recognises every file.

### Added

- Add a GitHub Actions workflow that checks PHP syntax and runs the unit tests on PHP 7.4 to 8.3.

## [1.17.2]

### Changed

- Redesign the public pages to match the ACP: catalogue cards with latest version, release count and last update; a product header with statistics and download and support buttons; a release timeline with a "Latest" badge, dates and colour-coded sections; a search bar with icon and clearer empty states.
- Keep the prosilver palette and width, use the forum's Font Awesome icons and keep buttons flat without hover, focus or click effects.

## [1.17.1]

### Changed

- Redesign the ACP module: product cards with latest version, release counts and public state; a product header with statistics; icon tabs; a release table with publication dates; collapsible panels; colour-coded note sections in the editor; a timeline for edit history and coloured ZIP and note comparisons.
- Keep the prosilver palette and flat buttons without hover, focus or click effects, and adapt the release table to narrow screens.

## [1.17.0]

### Added

- Add the "Can manage changelogs" (a_changelog) administrative permission, copied from a_board on update so current administrators keep access.
- Detect every translation shipped under language/ for exports, language selectors and Markdown heading recognition.
- Add unit tests for Markdown parsing, BBCode output, release comparison, ZIP inspection, signed import previews and validation rules.

### Changed

- Search and paginate the public catalogue in the database using a stored accent-insensitive copy of each product title.
- Split the ACP controller into request handling, screen rendering, URL building and dedicated import, export and validation services.

### Fixed

- Group the public product conditions in SQL so later filters cannot change which products are public.
- Remove the unused public product check left from the navigation link change.

## [1.16.3]

### Fixed

- Show the "Changelogs" navigation link whenever the option is enabled, instead of hiding it until a product has public content; new installs enable it by default.
- Let the public pages use the full prosilver content width and add Changelogs/product breadcrumbs.
- Replace the nested `<main>` element on public pages, as prosilver already provides the main landmark.

### Changed

- Remove hover, focus, active, shadow and gradient effects from public and ACP buttons.
- Use the default prosilver colour palette on the public pages and in the ACP, and let the ACP page use the full content width.

## [1.16.2]

### Changed

- Refine the ACP and public changelog with coordinated cards, buttons and form controls, including keyboard focus styles and responsive layouts.

## [1.16.1]

### Changed

- Manage download and support links together at product level and display both in the public header and reusable announcement.
- Remove per-release URL fields from the editor while preserving saved release links.

## [1.16.0]

### Added

- Set a single download page link per product and reuse it in a fixed BBCode announcement.

## [1.15.0]

### Added

- Preview and import several versions from a CHANGELOG.md into separate drafts while preserving existing releases.

## [1.14.0]

### Added

- Search the public catalogue by product name with pagination and accent-insensitive matching.

## [1.13.0]

### Added

- Filter Markdown and BBCode history exports by an optional inclusive publication period.

## [1.12.0]

### Added

- Export the full published history of a product as a BBCode text file.

## [1.11.0]

### Added

- Export the full published history of a product to a single CHANGELOG.md with release dates, notes and links.

## [1.10.0]

### Added

- Compare notes and release links from two saved versions of the same product in the ACP.

## [1.9.0]

### Added

- Record previous release content and the editor of each saved change.
- Review previous notes and release links in the ACP editor before restoring them.

## [1.8.0]

### Added

- Duplicate a saved release into a new draft editor with its notes, support link and previous version prefilled.

## [1.7.0]

### Added

- Preview the current editor content, including notes and release links, in the public layout before saving or publishing.
- Restrict release previews to administrators with access to the Changelog editor.

## [1.6.1]

### Changed

- Keep download and support fields visible directly below the version fields in the ACP.
- Show link validation errors next to the fields and display public release links before the notes.

## [1.6.0]

### Added

- Add optional download and support URLs to each release.
- Show configured URLs in the public changelog, individual release pages and BBCode announcements.

## [1.5.0]

### Added

- Search saved versions by a full or partial version number.
- Filter the ACP list to drafts or published versions.
- Keep filters and the list page when opening, editing and returning from a release.

## [1.4.0]

### Changed

- Show a product list when opening the Changelog ACP module.
- Give each product separate views for saved versions, Markdown import and ZIP comparison.
- Open the release editor only when editing or creating a release.

## [1.3.1]

### Added

- Open a standalone CHANGELOG.md in the ACP and load an existing version's notes into the editor.

### Changed

- Place the saved versions list above the editor and add an explicit Edit action for drafts and published releases.

## [1.3.0]

### Added

- Download the notes currently in the editor as a BBCode text file.
- Export a reusable CHANGELOG.md for a single version with English or Brazilian Portuguese headings.

## [1.2.1]

### Fixed

- Complete activation after a clean install or data purge regardless of migration file order.
- Repair the migration record left by an interrupted 1.2.0 activation while preserving existing data.

## [1.2.0]

### Added

- Search each public product history by version, with pagination for matching releases.
- Optionally show a Changelogs link in forum navigation when a release is published.

## [1.1.0]

### Added

- Import matching release notes from the new package's CHANGELOG.md when comparing two ZIPs.
- Share an individual published release from its product history or the ACP editor.

## [1.0.0]

### Added

- Compare two phpBB extension packages and draft release notes from the detected file changes.
- Edit and copy release notes as BBCode with English or Brazilian Portuguese headings.
- Publish a public changelog for each product.
