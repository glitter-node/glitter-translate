# Changelog

## [0.2.16]

### Added

- Added progressive browser link previews for standalone HTTP(S) URL lines in translated plain-text content.
- Reused the official `g7-ckeditor5-superpack` link-preview endpoint without changing translation values or content mode.
- Added a translated-content marker and plugin-owned responsive preview presentation.

## [0.2.15]

### Fixed

- Synchronized copied `extensionPointProps` representation bindings in replace-mode `html_content` providers so translated plain-text content reaches the text renderer.
- Preserved translation values and sanitizer behavior while correcting the actual renderer representation selection.

## [0.2.14]

### Added

- Added a nullable translation `content_mode` with an upgrade-safe backfill of existing content rows to `text`.
- Added content representation metadata to single and batch resolver responses.

### Changed

- Plain-text content translations are stored as `text` without inheriting the original Post's HTML mode.
- Missing and stale content translations now return the original Post's content mode with the original value.
- Connected resolved content representation metadata to the supported Hub board detail renderer through the official layout extension filter.

## [0.2.13]

### Fixed

- Preserved the selected locale when searching source Posts and retained the source Post page and search context when changing the editor locale.

## [0.2.12]

### Changed

- Reduced the saved-translation delete action to the existing neutral secondary button treatment.
- Rebalanced the saved-translation and source-post tables so primary titles remain flexible while metadata and actions use compact columns.
- Aligned compact table headers and values consistently without changing their responsive wrapping behavior.

## [0.2.11]

### Fixed

- Restored automatic selected-post and translation reloads when `post_id` changes.
- Removed the pagination-era manual refetch chain that could prevent the editor from opening.

## [0.2.10]

### Fixed

- Fixed source-post selection success handling to read the nested API response envelope.
- The selected post and translation data sources now refetch when a valid post ID is selected.

## [0.2.9]

### Fixed

- Fixed source Post selection by passing the rendered button value to the navigation query.
- Preserved the current source Post page and other selector query parameters when opening the translation editor.

## [0.2.8]

### Fixed

- Restored translation editor selection by reading the source-post selection from the API response envelope.
- Preserved the current source-post page when selecting a post for translation.

## [0.2.7]

### Fixed

- Restored source-post selector pagination controls by using the API's `pagination.last_page` response field.

## [0.2.6]

### Fixed

- Fixed source-post selector pagination bindings to consume the existing `pagination` response contract.

## [0.2.5]

### Fixed

- Cleared stale deleted-post selections before loading translation details.
- Kept stored orphan translations available for explicit administrator cleanup.

## [0.2.4]

### Fixed

- Excluded soft-deleted source Posts from the administrator translation selector while preserving orphan translations in the stored translation list.

## [0.2.3]

### Added

- Added administrator-only deletion of individual translation records by their own identifier.
- Added a delete action to the stored translations list with contextual confirmation and list refresh.

### Changed

- Preserved orphan translation deletion without loading the source post.

## [0.2.2]

### Fixed

- Restored the backend PHP translation arrays lost during the `src/lang` to `lang` migration.
- Fixed the HTTP 500 caused by empty Plugin language files returning `int(1)`.
- Restored the batch response message key used by the existing Controller.

### Changed

- Kept Plugin backend language files in the official `lang/{locale}/` path.
- No API, schema, or resolver behavior changed; existing translation data is unaffected.

## [0.2.1]

### Fixed

- Fixed the Plugin PHP language directory for GitHub and ZIP installation by using `lang/{locale}/*.php`.

## [0.2.0]

### Added

- Added a permission-aware public batch resolver for sirsoft-board Post translations.
- Added bulk translation lookup with original fallback for missing and stale values.
- Added a bounded title-only optional integration for Directory listing cards.

### Fixed

- Corrected the public release documentation and localized the Admin locale selector labels.

## [0.1.2]

### Changed

- Unified title and content actions into one locale-level Translation Editor.
- Added field-specific partial failure feedback while preserving independent
  title/content status and the existing Admin API contract.
- Added a single confirmed locale-level delete action without changing the
  original Post or translation persistence model.

## [0.1.1]

### Added

- Added browser-based Admin Translation Management for selecting Posts, editing supported locale title/content translations, and deleting translation fields.
- Added original content, locale, status, missing, current, and stale presentation in the management editor.
- Reused the authenticated G7 Admin API and existing translation permission contract without requiring manual Bearer token handling.

## [0.1.0]

### Added

- Added manual sirsoft-board Post title/content translation storage and resolution.
- Added locale fallback to the original Post value and source-hash stale detection.
- Added protected translation management API and admin overview.
