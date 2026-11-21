# Changelog for phpBB

Compare two versions of a phpBB extension, edit the release notes, copy an announcement in BBCode, and publish a product changelog.

## Requirements

- phpBB 3.3.x
- PHP 7.4 or later with ZipArchive

## Installation and use

Select the product in the ACP to open its changelog. Saved versions lists drafts and published releases, with a version search, a status filter and an Edit action for each entry. Duplicate as draft opens a new editor with the source notes, support link and previous version filled in. Enter the new version number and download link before saving. Returning from the editor keeps the active filters and the current page when available. Import Markdown opens a standalone CHANGELOG.md, and Compare ZIP packages opens the package comparison form. Compare notes shows the note and link differences between any two saved releases of the product. Export published history in Saved versions downloads all published releases as CHANGELOG.md or as a BBCode text file, including UTC publication dates, notes and links. Use either optional date field to limit the export to an inclusive UTC publication period. Review and save the notes in the editor. Use Bulk import to preview up to 200 Markdown versions from a file of up to 512 KiB and create drafts for the selected new versions. Edit history records the previous content and the editor of each saved change. Open a previous entry to review its notes and links in the editor before saving. Use Public preview to check the current editor content on a private page in a new tab before publishing.

1. Install the package so its composer.json is at ext/mundophpbb/changelog/composer.json in your board.
2. Enable Changelog in the ACP under Customise → Manage extensions.
3. Go to Extensions → Changelog → Changelog Manager and create a product.
4. Submit the older ZIP first and the newer ZIP second. Each package needs a composer.json identifying the same phpBB extension and its version.
5. Review the detected changes and edit the draft. If the new ZIP contains a root CHANGELOG.md with the matching Markdown version heading and list items, those items fill the draft. Otherwise, the draft uses suggestions from the file comparison. Each nonempty line becomes a separate release note. You can also create a release manually.
6. Copy the BBCode or publish the release to the product page. The editor can also download the current notes as a BBCode text file or a CHANGELOG.md for a single release. Select a language for the export headings and write the release notes in the desired language. Every translation under language/<iso>/common.php is listed automatically and its Markdown headings are recognised on import. The exported Markdown can be included in the next ZIP or merged into an existing changelog.

The public index is /app.php/changelog and each published product has a page at /app.php/changelog/{slug}. With URL rewriting enabled, omit app.php. Product links are optional HTTP or HTTPS URLs. Previously saved release links remain available as a public fallback for products without shared links.

Set download and support topic links when creating or editing a product. The public header shows those links, and Saved versions offers a reusable BBCode announcement using the same addresses. The index lists products with published releases or a download or support link, with name search and pagination. Visitors can search a product history by its version number or part of it. In Public settings in the ACP, enable the optional forum navigation link to make the index easier to find. The link appears on compatible styles for every visitor while the option is enabled. Click a version in the public history or use its link in the ACP editor to open its individual release page. Returning a release to draft removes it from the public listing.

ZIPs are read from PHP's temporary upload directory and are never extracted. The comparison uses file hashes and ignores Composer dependencies in the extension's root vendor folder, Git metadata and common desktop files. The application limits each ZIP to 25 MB, 8,000 archive entries and 128 MiB of declared uncompressed data. The web server and PHP can enforce smaller limits. Technical comparisons are displayed in the ACP and release notes remain editable after saving.

## Permissions

The module and the public preview require the **Can manage changelogs** administrative permission (`a_changelog`, under ACP → Permissions → Misc). When the extension is updated, the permission is copied from **Can alter board settings** (`a_board`), so every administrator who could open the module keeps access.

## Translations

Copy `language/en/` to `language/<iso>/` and translate both files. Keep `CL_LOCALE` set to the folder name, set `CL_LANGUAGE_NAME` to the language's own name and list the section headings used in that language's CHANGELOG.md files under `CL_MD_SECTIONS`. No code changes are needed.

## Development

The services in `service/` are plain PHP and have unit tests that run without a phpBB board:

```
composer install
vendor/bin/phpunit
```

