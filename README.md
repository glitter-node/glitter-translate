# Glitter Translate

Glitter Translate is a GnuBoard7 Plugin for manually translating `sirsoft-board`
Post titles and contents without changing the original Post.

- Plugin ID: `glitter-translate`
- Global Namespace reservation: `GlitterTranslate`
- Vendor / Author / User / Publisher: `Glitter.kr`
- License: MIT
- License Holder / Copyright Holder: `Glitter.kr`
- Required functional dependency: `sirsoft-board`
- Version: `0.2.16`

The G7 manifest dependency contract is used:

```json
"dependencies": {
  "modules": {"sirsoft-board": ">=1.1.2"},
  "plugins": {}
}
```

The minimum is based on the current sirsoft-board Post model/API contract in
this checkout. G7 activation validates that the module is installed and active;
Glitter Translate also performs a runtime version check and returns a localized
503 response when the dependency is missing, inactive, or incompatible. Existing
translation rows are never deleted by dependency failure.

## Architecture

```text
sirsoft-board
    │
    └── Post
          ├── Original title/content
          │
          ▼
Glitter Translate
          ├── Translation Storage
          ├── Locale Resolver
          └── Source-hash Stale Detection
                    │
                    ▼
             Translation API
                    │
          ┌─────────┼─────────┐
          ▼         ▼         ▼
      Directory  Knowledge  Editorial
```

The storage table is owned by the Plugin and deliberately has no foreign key
to `board_posts`; this preserves extension lifecycle safety when the required
module is removed. Missing Post rows are reported as orphaned and are not
deleted during reads.

## Supported scope

Version 0.2.16 supports only `sirsoft-board.post` and the `title` and `content`
fields. It is manual translation only. Automatic translation, translation
memory, glossary, workflow, collaboration, and category translation are not
included.

When a requested locale has a current translation, the resolver returns it.
When it is missing or stale, the original Post value is returned and the status
is exposed separately. A source SHA-256 hash is stored with each translation;
changing the original title/content therefore marks the row stale without
mutating the database during a read.

Content translations entered through the plain Textarea are stored with
`content_mode: text`. The resolver returns the translated content mode for a
current translation and the original Post content mode for missing or stale
fallbacks. Consumers must use the existing sirsoft-board content
sanitizer/rendering contract; this Plugin does not bypass it. The Post
read/permission integration for public User Template rendering is
an explicit adapter boundary because G7 has no official Post Resource output
filter in the current checkout. The protected admin API is available now, and
Directory, Knowledge, and Editorial can all use the same API/Resolver contract
as an optional enhancement without requiring this Plugin for ordinary Post
display.

## API

The active Plugin route prefix is `/api/plugins/glitter-translate`.

### Public batch resolver

POST `/api/plugins/glitter-translate/posts/translations/batch` resolves a
bounded page of permission-filtered sirsoft-board Posts in one request. The
request contains a board slug, Post IDs, and the requested title/content
fields. At most 100 IDs are accepted.

The current G7 application locale is used; no Directory-specific locale state
is introduced. The response is keyed by visible Post ID. A current
translation returns its value, while missing or stale translations return the
original value with its status. Posts rejected by the existing sirsoft-board
read-scope query are omitted, so translation existence is not disclosed.

The resolver performs one permission-filtered Post query and one bulk
translation query for the requested collection. It does not call the single
resolver per Post. The existing single resolver remains compatible.

- `GET /posts/{slug}/{postId}/translations?locale=en` — permission-aware public resolver
- `POST /posts/translations/batch` — permission-aware bulk public resolver
- `GET /admin/translations` — paginated management overview
- `GET /admin/source-posts/{slug}` — current source Posts available for translation selection
- `DELETE /admin/translations/{translation}` — administrator-only deletion of one translation record by ID
- `GET /admin/posts/{postId}/translations?locale=en` — resolved values and status
- `PUT /admin/posts/{postId}/translations/{locale}/{field}` with `{ "value": "..." }`
- `DELETE /admin/posts/{postId}/translations/{locale}/{field}`

Admin endpoints require the authenticated G7 Admin context and Plugin
permissions. Public resolver endpoints use the existing Post read-permission
contract and do not require a manually supplied token for ordinary public
access. The batch resolver accepts at most 100 Post IDs and supports the
allowlisted `title` and `content` fields.
Locales come from G7's configured supported locales; arbitrary locale strings
are rejected. `GlitterTranslate` is reserved as the Plugin namespace, but no
browser global is emitted because the Admin UI uses the shared G7 layout engine
and API authentication contract rather than a Plugin-specific browser runtime.

## Translation management UI

The Admin menu entry `/admin/plugins/glitter-translate` provides a browser
workflow for selecting a `sirsoft-board` Board and Post, inspecting original
content, choosing a supported locale, and managing one locale's title/content
translation as a single editor. Save and delete actions orchestrate the
existing field-specific Admin API endpoints through the authenticated G7 Admin
browser context; administrators do not enter Bearer tokens manually. Field
status remains independent, and partial save/delete failures identify the
affected field without discarding the other field's input. Stale stored values
remain visible to administrators, while the public resolver continues to fall
back to the original Post until the translation is saved against the current
source hash.

## Lifecycle

Install and activate through G7's official Plugin lifecycle. Do not edit the
installed Plugin directory or the original `sirsoft-board` module. If
`sirsoft-board` is unavailable, translation routes fail safely and preserve
existing translation data.

For the supported Hub board detail layout, the plugin also registers the
official `core.layout_extension.after_apply` filter. It connects
`fields.content.content_mode` to the translated content renderer without
modifying the template source. Plain-text translations use the text rendering
path, while missing or stale translations retain the Resolver's original
content representation fallback. Title translations do not receive content
metadata.

On supported public pages, translated plain-text content gets a progressive
link-preview enhancement for standalone HTTP(S) URL lines. The browser uses
the official `g7-ckeditor5-superpack` link-preview endpoint with the translated
URL itself; it does not change the stored value or content mode. If the asset,
endpoint, or metadata request is unavailable, the original plain-text URL
remains visible.
