# The Hack Repair Guy's Stickies: project notes

Developer notes for contributors. For what the plugin does, see [README.md](README.md "What the plugin does and how to install it"). This file keeps the architecture, decisions and limits, in the same format as [Site Inspector's PROJECT.md](https://github.com/hackrepair/thrg-site-inspector "Site Inspector, the reference for this plugin's structure").

Status: **2.0.1 release, October 23, 2026.** Schema 4 adds mention email status; schema 3 added atomic note revisions. GitHub distribution remains enabled.

Previous release: **1.1.2 released October 23, 2026** as the first public release (tag `v1.1.2`, asset `thrg-stickies.1.1.2.zip`) at https://github.com/hackrepair/thrg-stickies. Versions 1.0.0 to 1.1.1 were local builds only and were never published. Tested on the Zendogs sandbox (http://localhost:8081).

## Purpose

Logged-in team members pin sticky notes to the exact spot on any page of a WordPress site: "this button is the wrong color", "client wants this paragraph shorter", "plugin conflict shows up here". Notes stay where they were placed. People can mention a teammate with @, and that person gets an email with a link straight to the note. Once the work is done, the note gets marked resolved.

The plugin is for site owners, designers, developers and hack-repair work during build, review and cleanup. It is not a public commenting system, so visitors never see notes and never load its scripts.

## Reference plugin and what we keep

The example in `/Volumes/BACKUP8TB2/plugins/stick-notes/wp-sticker-notes-2.2` is WP Sticky Notes 2.1.5 by Soso Kruashvili (2014, GPLv2). We use it as a reference for features and appearance only. **No code, CSS, images, fonts or text are copied from it.** Every file in this plugin is written new. The credits name the plugin as the inspiration.

### Features in the reference

- A small fixed toolbar on the left edge of the page with three icon buttons: Add note, Hide notes, Show notes.
- A new note appears beside the toolbar. You drag it by a top strip, resize it from the corner, type into it, and save it with a check button or Ctrl+S.
- A saved note shows a blue push-pin at the top center. Moving, resizing or editing the note removes the pin until it is saved again.
- An X in the top-left corner deletes the note.
- Notes are tied to the page URL. Their horizontal position is stored as an offset from the center of the window, so they stay in place when the window is resized on centered layouts.
- Plain URLs typed into a note turn into links.
- Typing @ followed by letters shows a list of matching users. Picking one inserts a tag, which limits the note to those users and sends them an email.
- Read, create and edit capabilities are set per role, per user (on the profile screen) and for "Everyone" (logged-out visitors). A setting chooses which post types show notes.
- Notes are hidden on small screens.

### Look and feel to recreate (with our own assets)

- Note: warm pale yellow (around `#ffefb1`), 4px rounded corners, soft drop shadow (about `2px 1px 5px rgba(50,48,50,.33)`), default size around 270 × 190 px, minimum 220 × 100 px.
- Text: 18px, line height 1.2, dark gray `#333`, humanist sans-serif stack (Gill Sans / Myriad / Helvetica style). Comfortable padding, scrolls when long.
- Drag strip: about 23px tall across the top. The cursor changes to a move cursor and no visible bar is shown.
- Saved state: a push-pin at the top center (our own SVG; since 1.1.2 a Hack Repair heart pin).
- Small gray close (X) icon at top left and save (check) icon at bottom left. Save is hidden while the note is saved and unchanged.
- Toolbar: white, about 43px wide, attached to the left edge about 150px from the top, right corners rounded, light gray shadow, icons in blue (around `#229edc`).
- Mention suggestions: a white dropdown below the note with a light gray border. Rows highlight in the same blue with white text on hover.
- Mention tag: small rounded chip in pale blue-gray (around `#b3cbd7`) with a thin border, 14px text.
- Links inside notes: dark gray and underlined.

The THRG layer adds the 2026 heart branding on admin screens, the View details banner and the companion-plugins section. It does not change the paper-note look on the page.

### Review of the reference: problems we must not repeat

These are the reasons for a clean rewrite rather than an update of the example:

1. **SQL injection.** The note query builds SQL from the request URL and the `wpst_id` cookie with no escaping. It runs on every front-end page load, including for logged-out visitors.
2. **Unauthenticated write endpoints.** Save, delete and user-search are all registered as `wp_ajax_nopriv_*` with no nonce. Ownership of a note is decided by a client cookie.
3. **Stored XSS.** Note HTML from `contenteditable` is saved and rendered as-is. The mention chip is built by string concatenation. The notification email inserts the posted URL and note into HTML.
4. **User enumeration.** Anyone can search users by login, nicename, email and URL, and get back IDs and display names.
5. **CSRF and missing capability check on settings.** The permissions form is processed in the plugin file body on every request, with no nonce and no capability check.
6. **Work done on every request.** Capabilities are added to the administrator role, and a cookie is set for every visitor, on each page load.
7. **Broken admin page.** `pages/admin_main.php` is a duplicate of `plugin.php`. Loading it would fail with a redeclared-function fatal error, so the settings screen does not work in this copy.
8. **Uninstall** drops a hard-coded `wp_sticker_notes` table, which is wrong on sites with a custom prefix, and leaves options and capabilities behind.
9. **Dated stack.** It depends on jQuery UI 1.10 CSS, an icon font, user-agent sniffing, `alert()` error messages and PHP notices on undefined variables. The readme uses a legacy encoding. The folder says 2.2 while the header says 2.1.5, and a `color` column exists that is never used.
10. **Fragile URL matching.** Notes are matched on the full URL, including tracking parameters, and the scheme comes from port 443. Notes disappear behind proxies or when `?utm_source=` is added.

## Identity

| Item | Value |
|---|---|
| Plugin name | The Hack Repair Guy's Stickies |
| Short name in UI and emails | Stickies |
| Slug, folder, text domain | `thrg-stickies` |
| Main file | `index.php` (same as Site Inspector) |
| PHP prefix | `thrg_stk_`, classes `THRG_STK_*`, constants `THRG_STK_*` |
| CSS/JS prefix | `thrg-stk-` |
| Version constant | `THRG_STK_VERSION` |
| Repository | `hackrepair/thrg-stickies` (public, branch `main`) |
| REST namespace | `thrg-stickies/v1` |
| Database table | `{prefix}thrg_stickies` (schema version 4) |
| Local working copy | `/Volumes/BACKUP8TB2/plugins/thrg-stickies/thrg-stickies` |
| Releases folder | `/Volumes/BACKUP8TB2/plugins/thrg-stickies/releases/` (outside the repo) |
| Requires | WordPress 6.9.4+, PHP 7.4+ (checked on PHP 7.4.33 and 8.2.33, WordPress 7.1) |
| License | GPL v2 or later |
| Author | The Hack Repair Guy, https://hackrepair.com/ |

Plugin header, following Site Inspector:

```
Plugin Name:       The Hack Repair Guy's Stickies
Plugin URI:        https://github.com/hackrepair/thrg-stickies
Description:       Pin sticky notes to any spot on any page of your site, mention a teammate, and mark the note resolved when the fix is done.
Version:           1.1.2
Author:            The Hack Repair Guy
Author URI:        https://hackrepair.com/
Update URI:        https://github.com/hackrepair/thrg-stickies
Requires at least: 6.9.4
Requires PHP:      7.4
Text Domain:       thrg-stickies
License:           GPL v2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html
```

## Scope for 1.0.0

### On the page (front end and, optionally, admin screens)

- **Toolbar** on the left edge (the right edge on dashboard screens, clear of the admin menu), shown only to users who can read notes. It has these buttons:
  - Add note (requires create)
  - Hide/show notes on this page (remembered per browser)
  - Notes list: a small panel listing every note on the page, with a count badge. Clicking one scrolls to it and highlights it. This is also how notes are reached on small screens.
- **Admin bar item** "Stickies (n)" as a second way to open the list and toggle visibility. The toolbar can be turned off in settings in favor of the admin bar only.
- **Create:** a new note appears near the visible top of the window, focused and ready for typing.
- **Move and resize:** pointer events (mouse, pen, touch) with no jQuery UI. Keyboard alternatives are provided: with the note focused, arrow keys move it by 10px and Shift+arrow resizes it.
- **Save:** check button, Ctrl+S or Cmd+S while the note is focused, and an optional autosave after a pause in typing (setting, off by default). The Hack Repair heart pin (`assets/img/pin-heart.svg`) shows when saved and can be grabbed to move the note, and the note shows a "Saving…" state and errors inline, never through `alert()`.
- **Colors:** yellow (default), blue, green, pink and gray, chosen from a round color button at the top right of the note. A soft sheen confirms the change.
- **Resolve:** the round check button at the bottom right (same size as the color button, with a hover tip) marks the note resolved. The note leaves the page and an "Undo" message appears. A "Show resolved" toggle in the list panel lists resolved notes; clicking one shows it on the page so it can be reopened. The Stickies list also reopens notes.
- **Delete:** with an inline "Delete this note?" confirmation, never a browser dialog.
- **Mentions:** typing `@` and at least one character suggests up to 10 users who can read notes. Picking one inserts a chip. Mentioned users get an email.
- **Visibility per note:** *Team* (everyone who can read notes, the default), *Mentioned only* (author, mentioned users and note managers), or *Just me*. A small ? beside the menu gives a tooltip explaining Team and Mentioned only.
- **Links:** plain `http(s)://` URLs are turned into links when rendered, with `rel="noopener noreferrer nofollow"` and opened in a new tab.
- **Empty and unsaved notes:** saving an empty note shows a random friendly line ("Oopsy! Out of ink?", "Using invisible ink?", "Oh, you're joking?", "Don't be shy.", "Blank notes don't stick.", "Stickies need love too!"). An unsaved note's footer says "Save Me".
- **Footer line** in small gray text: author ("You" for your own notes), relative time, and "edited" only when the text changed. Save confirmations appear in this line so they never cover the note.
- **Position model:** the same idea as the reference, with vertical position from the top of the document and horizontal position as an offset from the horizontal center of the viewport. We also store the viewport width at save time. On reflow the note is clamped so it never goes off-screen. Below a configurable breakpoint (default 782px, the WordPress admin breakpoint) notes are not drawn on the page, and the list panel shows them instead.
- **Deep link:** `#thrg-stk-<id>` in a URL scrolls to and pulses that note. Email links use this.

### In the dashboard

- **Stickies** (left menu): a management page with the Site Inspector header style (2026 heart, plugin name, small version label). It has these parts:
  - Table of notes: color swatch and excerpt (link opens the page at the note), visibility tag when not Team, page title and path (Dashboard tag for admin screens), author, mentions, status (with who resolved it), updated. Views: All, Open, Resolved, Mentioning me. Search covers text, path and title. Pagination at 20 per page.
  - Row actions: open on page, resolve/reopen, delete. Bulk actions: resolve, reopen, delete.
  - A Support container at the bottom, with the same wording as Site Inspector 2.3.0 (GitHub Discussions and Buy Jim a coffee), and the Site Inspector-style footer card: the heart with "Something on your site acting strange? Contact The Hack Repair Guy."
- **Stickies > Settings** (page byline "Choose who can use Stickies and where notes appear"; the heart links back to the Stickies list):
  - Who can do what: a role matrix for *See notes*, *Add notes* and *Manage everyone's notes*. Administrators always have all three (their row is shown checked and locked). Option to let managers see Just me notes in the Stickies list.
  - Where notes appear: front end (on, default) and dashboard screens (on, default). Every front-end page counts, including archives.
  - Toolbar: left edge, admin bar only, or both. Small-screen breakpoint.
  - Mention emails: on/off.
  - Autosave: on/off.
  - Uninstall: *Delete all plugin data* (off by default), same wording as Site Inspector.
- **Plugins screen:** Settings and Notes action links, and a native **View details** dialog built from the bundled readme.txt, with banner, icons, the THRG brand banner and an "Other THRG Plugins" tab (Site Inspector, Plugin Detective, Group Password Reset, Disable Other Plugin Notices).
- **One-time notice** after activation, pointing to the toolbar and the settings. Dismissible and stored per user.

### Out of scope for 1.0.0

- Logged-out or anonymous notes. Notes are for logged-in users only. The reference's "Everyone" option is deliberately dropped (decided October 22, 2026).
- Threaded replies, attachments, screenshots, @mentions of email addresses outside the site, real-time multi-user updates, Slack or other outside integrations, and notes on specific DOM elements (anchoring to an element selector is a candidate for a later version).
- Front-end notes inside the block editor canvas.

## Architecture

```
thrg-stickies/
  index.php                          Header, constants, updater, class loading, activation hook
  uninstall.php                      Removes data only when "Delete all plugin data" is ticked; multisite aware
  readme.txt                         WordPress-format readme (feeds View details)
  README.md, PROJECT.md              GitHub docs (not packaged)
  LICENSE, phpcs.xml.dist
  includes/
    class-thrg-stk-plugin.php        Boot, activation (per site / network), loading rules, admin bar, browser strings
    class-thrg-stk-schema.php        Table definition, dbDelta install/upgrade, schema version
    class-thrg-stk-notes.php         Table access: find, for_page, insert, update, delete, list query (prepared only)
    class-thrg-stk-access.php        Capabilities, role matrix, per-note view/edit/resolve/delete rules (pure)
    class-thrg-stk-content.php       Sanitizer, mention tokens, safe renderer, excerpt (pure)
    class-thrg-stk-url.php           Page-path normalizer and key (pure)
    class-thrg-stk-rest.php          REST routes and the note shape sent to the browser
    class-thrg-stk-email.php         Mention emails per the THRG email standard (pure builders + send)
    class-thrg-stk-settings.php      Stickies > Settings and the role matrix
    class-thrg-stk-admin.php         Stickies menu, row and bulk actions, header/Support/footer, welcome notice
    class-thrg-stk-list-table.php    WP_List_Table for the Stickies list
    class-thrg-stk-details.php       Plugins row View details from the bundled readme
    class-thrg-stk-updater.php       GitHub release updates (same logic as THRG_SI_Updater)
    class-thrg-stk-update-notices.php
    companion-plugins.php            "Other THRG Plugins" tab in View details
  assets/
    css/thrg-stk-notes.css           On-page notes, toolbar and panel (scoped under .thrg-stk-root)
    css/thrg-stk-admin.css           Dashboard screens (Site Inspector palette)
    css/thrg-stk-plugin-details.css  Companion cards in View details
    js/thrg-stk-notes.js             On-page app (vanilla JS, no jQuery or libraries)
    img/pin-heart.svg                Hack Repair heart push-pin (drawn for this plugin)
    brand/                           THRG heart kit 1.0.1 (from hackrepair-with-heart-animated-2026/plugin-kit)
    banner-772x250.png, banner-1544x500.png, icon-128x128.png, icon-256x256.png
  plugin-update-checker/             Upstream 5.7, unmodified
  languages/
  scripts/build-release.py           Same builder as Site Inspector (0644 entries, verified)
  tests/                             Plain PHP tests, integration test, package-permissions.py
  .review/                           Ego Lite review scripts and screenshots (git-ignored, local only)
```

### Data

One custom table per site, `{$wpdb->prefix}thrg_stickies`, created with `dbDelta()` and tracked by the `thrg_stk_db_version` option. A custom table is chosen over a custom post type because notes are looked up by page on every load for logged-in users, need an index on the page key, and should not show up in search, sitemaps, exports or revisions.

| Column | Type | Notes |
|---|---|---|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | Primary key; used in `#thrg-stk-<id>` |
| `page_key` | CHAR(40) | SHA-1 of the normalized page path; indexed |
| `page_path` | VARCHAR(2048) | Normalized home-relative path and kept query string, for display and links |
| `page_title` | VARCHAR(255) | Browser title when the note was created (plain text), for the list and emails |
| `context` | VARCHAR(10) | `front` or `admin` |
| `content` | TEXT | Plain text with mention tokens `@[user:123]`; never HTML |
| `pos_top` | INT | px from document top |
| `pos_center` | INT | px offset from viewport center |
| `width`, `height` | SMALLINT UNSIGNED | Validated 120–2000 and 60–2000 by the API; the browser keeps 180–900 by 100–900 |
| `viewport_w` | SMALLINT UNSIGNED | Viewport width when saved |
| `color` | VARCHAR(10) | Allowlisted: yellow, blue, green, pink, gray |
| `visibility` | VARCHAR(10) | `team`, `mentioned`, `private` |
| `status` | VARCHAR(10) | `open`, `resolved` |
| `author_id` | BIGINT UNSIGNED | Indexed |
| `mentions` | VARCHAR(255) | Comma-separated user IDs, also derivable from content; used for filters |
| `created_at`, `updated_at` | DATETIME (GMT) | `updated_at` changes on any save and drives conflict checks |
| `edited_at` | DATETIME (GMT) | Nullable; set only when the text changes, so resolving is not shown as an edit (added in schema 2) |
| `resolved_by`, `resolved_at` | BIGINT UNSIGNED, DATETIME | Nullable |

Limits: 2,000 characters of note content, 10 mentions per note, 200 notes per page (`thrg_stk_page_limit` filter) and 5,000 notes per site (`thrg_stk_site_limit` filter). The API explains the limit when it is reached.

Schema upgrades run on `init` when `thrg_stk_db_version` differs from `THRG_STK_Schema::VERSION`, and for new network sites through `wp_initialize_site`. The 1 → 2 upgrade (adding `edited_at`) was exercised on Zendogs.

Options: `thrg_stk_options` (settings array), `thrg_stk_db_version`, `thrg_stk_show_welcome`. User meta: `thrg_stk_notice_dismissed`. Transients: `thrg_stk_mail_<note>_<user>` (mention throttle), `thrg_stk_github_limit`. Per-browser preferences (notes hidden, show resolved) use `localStorage` inside try/catch.

### Page key normalization (`THRG_STK_URL::key()`)

- Uses the path and query relative to `home_url()`, so scheme, host and port are ignored. This survives HTTP to HTTPS, staging to live copies and proxies.
- Removes the fragment, trailing `index.php` and duplicate slashes, and adds a trailing slash to match the permalink structure.
- Removes known tracking parameters (`utm_*`, `fbclid`, `gclid`, `msclkid`, `dclid`, `mc_cid`, `mc_eid`, `_ga`, `_gl`, `ref`, `yclid`, `igshid`) and sorts the rest. It parses the query itself, so PHP's dot-to-underscore rewriting never changes a key.
- Rejects dot segments, control characters and paths over 2,048 characters.
- Admin context: keeps `page`, `post`, `action`, `taxonomy`, `post_type`, `tab`, `tag_ID` and `user_id` only, so a note on "Edit Post 42" stays on that screen and nonces never become part of the key.
- This is a pure function with its own test file.

### Capabilities

- Custom capabilities: `thrg_stk_read`, `thrg_stk_create` and `thrg_stk_manage` (edit/resolve/delete others' notes).
- Editing, resolving and deleting your own notes needs only `thrg_stk_create`.
- Capabilities are granted on activation (administrators get all three, editors get read and create) and when the role matrix is saved. They are never granted on page load. The matrix is stored in settings and applied to roles in one place.
- Uninstall removes the capabilities from all roles when data deletion is ticked.
- Per-note decisions live in `THRG_STK_Access` (pure functions taking the note, user ID and capability booleans) rather than `map_meta_cap`. That keeps the rules testable without WordPress; the REST routes and the Stickies list both call the same functions.
- Resolving and reopening are also allowed for anyone mentioned in the note. Adding or managing notes implies seeing them.
- Multisite: super admins have all capabilities. Settings are per site.

### Visibility rule (`THRG_STK_Access::can_view( $note, $user )`)

The user must have `thrg_stk_read`. Then:

- `team`: visible.
- `mentioned`: author, mentioned users and `thrg_stk_manage`.
- `private`: author only (managers see it in the dashboard list only if a setting allows it; off by default).

This is a pure function, tested with a matrix.

### REST API

Namespace `thrg-stickies/v1`. Every route uses a `permission_callback` and the standard `wp_rest` nonce (the user must be logged in). Input is validated with `args` schemas.

| Method | Route | Purpose | Permission |
|---|---|---|---|
| GET | `/notes?uri=<path>&context=front` | Notes visible to the current user on a page (Just me notes only for their author) | `thrg_stk_read` |
| POST | `/notes` | Create | `thrg_stk_create` |
| PATCH | `/notes/<id>` | Edit content, position, size, color, visibility; or status | edit: owner with `thrg_stk_create`, or `thrg_stk_manage`; status: also anyone mentioned |
| DELETE | `/notes/<id>` | Delete | owner or `thrg_stk_manage` |
| GET | `/users?search=<text>` | Mention suggestions: ID, display name, avatar URL; searches login, nicename and display name (never email); only users with a Stickies capability; max 10 | `thrg_stk_create` |

- Notes are fetched with REST after the page loads, never printed into the HTML. Cached pages therefore never contain notes, and the front-end script is only enqueued for logged-in users with read access.
- PATCH sends only the changed fields. Edits use optimistic concurrency: the client sends `updated_at`, and the server replies 409 with the current note if it changed in the meantime. The note offers a "Reload note" button.
- Notes another user cannot see return 404 rather than 403, so their existence is not revealed.
- Creating stores `document.title` (sanitized, 200 characters) as the page title for the list and emails.
- Mention emails go out only to users newly added on that save, and at most one email per recipient per note every 10 minutes.
- Responses carry rendered, escaped HTML (`rendered`) alongside raw text (`content`). The client never builds HTML from user text.

### Content handling (`THRG_STK_Content`)

- Read mode shows the server's `rendered` HTML. Clicking the text switches to edit mode: a `contenteditable` area built from the raw text with `textContent` and `contenteditable=false` mention chips. Saving walks the DOM back to plain text and tokens. Paste and drop insert plain text only. No user HTML is accepted at any point.
- Sanitize: repair invalid UTF-8, normalize line breaks, strip control and zero-width characters, limit blank lines, cap at 2,000 characters without leaving half a token. Mention tokens for users who don't exist or can't see notes become a plain `@`. Text such as `<b>` is kept as text and escaped on output.
- Render: `htmlspecialchars()`, then auto-link `http(s)://` URLs (trailing punctuation stays outside, long labels shortened), then tokens to chips with the current display name, then line breaks. The output is run through `wp_kses()` with a tiny allowlist (`a[href,rel,target]`, `span[class,data-user]`, `br`) and http/https only.

### Mention email (`THRG_STK_Email`)

Follows `THRG-EMAIL-STANDARD.md`:

- Subject: `[example.com] [Stickies] You were mentioned in a note`
- From the site's default sender. Reply-To is the note author's email when valid.
- Body: who mentioned you, the page title and path, the note excerpt (escaped, mentions shown as names, 300 characters), a "View the note" button linking to the page with `#thrg-stk-<id>`, the site URL, and a line saying you need to be logged in to see it.
- HTML with a plain-text alternative, one personal message per recipient via `wp_mail()`, and no email addresses of other recipients in headers.
- Pure builders (`subject()`, `html()`, `text()`), unit-tested the same way as Site Inspector's email class.

### Front-end script (`thrg-stk-notes.js`)

- Vanilla ES2017 with no dependencies, loaded in the footer with `defer`. It receives a small config via `wp_add_inline_script()` (`window.thrgStickies`): REST root, nonce, context, current user, capabilities, settings, color list and interface strings.
- One `Note` object per note (element, drag, resize, keyboard, editing, mentions, save/resolve/delete), plus page-level toolbar, panel, admin bar link, deep link, small-screen switch and Ctrl+S / Cmd+S handling. It works with both pretty permalinks and `?rest_route=` REST URLs.
- `innerHTML` is used only for the file's own SVG icons and the server-rendered `rendered` field; `tests/smoke.php` enforces this.
- Leaving the page with unsaved notes triggers the browser's standard leave-page warning.
- Accessibility: the toolbar and buttons are real `<button>`s with labels, notes are `role="note"` with a label, focus is managed on create and delete, there are visible focus rings, `prefers-reduced-motion` turns off the pulse and pin animation, and colors meet contrast.
- Styles are scoped under `.thrg-stk-root`, which is attached once to `<body>`. Buttons are reset with `all: unset`, so button styles inside it use `.thrg-stk-root` selectors to win over that reset. `!important` is used only where WordPress admin or theme styles are known to override (links in notes, the visibility select).
- Position math converts document coordinates through the root's own offset, so themes that offset or position `<body>` do not shift notes.

### Updates (Hack Repair plugin standard)

- `plugin-update-checker/` is the unmodified upstream copy (5.7 by Jānis Elsts, MIT), declared in README, readme.txt and this file. Check upstream at each release.
- `THRG_STK_Updater` uses the same logic as `THRG_SI_Updater`: latest-release strategy only, release assets required, and the tag must equal the asset's version. `REPOSITORY_URL` is `https://github.com/hackrepair/thrg-stickies/` and `ASSET_PATTERN` is `/^thrg-stickies\.[0-9]+(?:\.[0-9]+){2}\.zip$/`.
- The updater is not gated by `is_admin()`. `THRG_STK_SKIP_UPDATER` is defined only by the tests.
- Release asset `thrg-stickies.X.Y.Z.zip` with a single top folder `thrg-stickies/`, tag `vX.Y.Z`. Never change the asset name pattern after the first release without a transition release.

## Design rules

- Nothing for visitors: no scripts, styles, cookies, REST access or markup for logged-out users or users without `thrg_stk_read`.
- Every write path needs a logged-in user, a nonce and a capability check, and uses prepared queries via `$wpdb->prepare()` or `insert`/`update`/`delete` with formats.
- No user-supplied HTML is ever stored or echoed. All output is escaped at the point of output.
- No outbound requests, scheduled jobs or file writes. Email is the only thing that leaves the site.
- Plain-language UI and messages, same voice as Site Inspector. Errors say what happened and what to do.
- Docs links carry tooltip text, written as `[text](url "tooltip")`.
- Inspiration credit for the reference plugin is in readme.txt Credits and README License and credits: "Inspired by the 2014 WP Sticky Notes plugin by Soso Kruashvili (GPLv2). Stickies is a new plugin written from scratch; no code, images or text from it are used."

## Tests

Plain PHP scripts in `tests/` (shared helpers in `tests/bootstrap.php`), plus the 0644 package check:

- `tests/url.php` (26): page-path normalization: tracking parameters, ordering, flags, dots in names, subfolder home, admin screens, rejected input.
- `tests/content.php` (25): sanitizer, length cap, mention tokens and filtering, rendering, auto-linking, and XSS payloads (`<script>`, quote breakouts, `javascript:`, tags after URLs, entity handling).
- `tests/access.php` (21): visibility and permission matrix for authors, writers, readers, mentioned users and managers, with and without the private-notes setting.
- `tests/email.php` (16): subject per the email standard (domain, subdomain, port, fallback), HTML escaping, full width, plain-text body.
- `tests/schema.php` (28): table SQL shape, every repository column present, row typing.
- `tests/smoke.php` (31): plugin loads with the updater skipped, hooks registered, no `wp_ajax_nopriv_` handlers, settings defaults and sanitizing, shipped files, `innerHTML` rule.
- `tests/uninstall.php` (9): data kept by default; table, options, user meta, capabilities and throttles removed when opted in, other data untouched.
- `tests/package-permissions.py`: build output uses 0644 entries even from 0600 sources.
- `tests/integration.php` (49): runs inside a real WordPress (`php tests/integration.php /var/www/html`, CLI only). It creates temporary users and notes, then checks:
  - REST permissions for logged-out users and every role
  - creation, sanitizing and rendering
  - mentions and the mention email (intercepted with `pre_wp_mail`)
  - page-key matching across hosts
  - edit, resolve, reopen and delete permissions
  - 409 conflicts and validation errors
  - private and mentioned-only visibility
  - user search limits and email throttling
  - the role matrix and the schema upgrade

  Afterwards it removes everything it created.

Shared PHPCS before every commit of PHP changes:

```sh
/Volumes/BACKUP8TB2/plugins/PHPCS/run-phpcs.sh "$PWD"
```

`phpcs.xml.dist` checks shipped PHP against WordPress Extra and PHPCompatibilityWP for PHP 7.4+, excluding tests, review helpers and the bundled updater.

## Validation, October 22, 2026

- Shared PHPCS: clean (0 errors, 0 warnings, 17 files).
- Unit tests: all pass on PHP 7.4.33 (thefitnessplace container) and PHP 8.2.33 (Zendogs container). JavaScript passes `node --check`.
- Integration test on Zendogs (WordPress 7.1, PHP 8.2): 49 passed, 0 failed.
- Ego Lite review on Zendogs (http://localhost:8081) with temporary review accounts (an administrator and an editor, removed afterwards with their notes). Screenshots and scripts are in `.review/`. Checked:
  - Toolbar and admin bar item, create, typing, @mention suggestions and chip, Ctrl/Cmd+S save, push-pin, drag, color change, reload persistence (same position), notes panel, resolve (collapses or hides), Show resolved, reopen, deep link with pulse and focus.
  - Phone width (390px): notes listed in the panel with expandable text and resolve, no horizontal overflow.
  - Logged out: no Stickies markup or config in the page, REST returns 401.
  - Editor mentioned in an admin's note: read-only note with a Resolve button, no delete.
  - Mention email captured in Mailpit with subject `[localhost:8081] [Stickies] You were mentioned in a note`, rendered correctly.
  - Tools > Stickies list, bulk resolve, Settings save through options.php, notes on dashboard screens (toolbar on the right edge), delete with inline confirmation, Plugins row links and the View details dialog with THRG branding.
- Fixed during review:
  - The save confirmation covered the note text; it now shows in the footer line.
  - The footer was crowded; it now says "You" for your own notes and the visibility menu is narrower.
  - Theme and admin styles overrode the note buttons and the visibility menu.
  - Themes added spacing inside the notes panel.
  - "(edited)" showed after a resolve; a new `edited_at` column now tracks text changes only.
  - The admin bar icon was generic.
  - The toolbar covered the admin menu on dashboard screens; it now uses the right edge there.
  - Result arguments stayed in the Tools address bar after an action.
- Not yet checked: keyboard-only and screen reader passes, reduced motion in the browser, multisite network activation, other themes and page builders, delivery to an external inbox, and installing through the published updater (no repository yet). The Codex Security diff review was not run; consider it before release.

## Validation, October 23, 2026 (1.1.2)

- Shared PHPCS clean, all unit tests pass on PHP 7.4 and 8.2, and the integration test passes on Zendogs (WordPress 7.1).
- The `.review/` screenshots were all retaken from the final 1.1.2 interface with Ego Lite (`r12.mjs` site screens, `r13.mjs` dashboard screens, `r14.mjs` phone, logged out, editor, email and deep link). The scripts read temporary review logins from `releases/_review-users.txt`, which is deleted afterwards along with the review users and their notes. Screenshots 01 to 26 cover:
  - Toolbar, new note, @mention suggestions, "Save Me" on an unsaved note, the heart pin on a saved note, the visibility help tip.
  - The color button and palette, the sheen on a color change, moving a note by its pin, the random empty-note message.
  - Notes panel, resolving (the note leaves the page with an Undo message), Show resolved, reopening from the panel, the empty panel.
  - Plugins row, View details, a note on a dashboard screen, the Stickies list, Settings top and bottom, inline delete confirmation.
  - Deep link from the email, phone width (390px, no horizontal overflow), mention email in Mailpit, logged out (no markup, REST 401), and the editor view of an admin's note (read-only, can resolve, no delete, no Settings menu item).
- Interface changes since the October 22 review: top-level Stickies menu with All stickies and Settings, color button moved to the top right, round resolve button with a hover tip at the bottom right, matching 26px grip buttons, Hack Repair heart pin that can be dragged, color sheen, Undo message after resolving, resolved notes hidden from the page, visibility help tip, orange Add plus, friendlier empty-note and "Save Me" messages, Settings byline and heart link back to the list.

## Build and release

Same steps as Site Inspector:

1. The public `hackrepair/thrg-stickies` repository exists (Discussions on, About text set).
2. Bump the version in the `Version:` header, `THRG_STK_VERSION`, `Stable tag` and the changelog.
3. Run all tests and PHPCS.
4. Scan text files and images for forbidden attribution words.
5. Commit and push `main`.
6. Build with `git archive --format=zip --prefix=thrg-stickies/ HEAD` excluding `tests`, `PROJECT.md`, `README.md`, `.gitignore` and `scripts` (or with `scripts/build-release.py`) into `releases/`. `.review/` and `dist/` are git-ignored, so they never reach the repository or the ZIP.
7. Scan the zip.
8. `gh release create vX.Y.Z`.
9. Verify the tag, asset name and that remote `main` matches.

A release goes live to every site using the plugin, so it is cut only when Jim asks to package, release or ship.

Installed copies get updates only from tagged releases that carry the `thrg-stickies.X.Y.Z.zip` asset. Pushing `main` alone ships nothing.

## Decisions, October 22, 2026

1. **Name:** The Hack Repair Guy's Stickies, short name Stickies, slug `thrg-stickies`.
2. **Logged-out notes:** not supported. A read-only client share link is a possible later feature.
3. **Where notes show:** front end and dashboard screens both on by default (changed October 23, 2026 after testing showed nothing visible in wp-admin); each can be turned off in Settings.
4. **Who has access:** administrators and editors (see and add) on activation.
5. **Companion section:** Stickies lists the other THRG plugins in its own View details. Adding Stickies to the other plugins' companion sections waits until the repository is public.
6. **Support and hosting tie-in:** same as Site Inspector: a Support container with the coffee link and GitHub Discussions, plus the footer card linking to The Hack Repair Guy.
7. **Dashboard menu (October 23, 2026):** Stickies is a top-level menu item (position 26, below Comments) with All stickies and Settings under it, replacing Tools > Stickies and Settings > Stickies.
8. **Resolved notes (October 23, 2026):** they leave the page instead of shrinking to a pin; the panel and the list are where to find and reopen them.

## Open items

- Add Stickies to the companion sections of Site Inspector, Plugin Detective, Group Password Reset and Disable Other Plugin Notices (only when Jim asks).
- Install through the published updater on a test site to confirm the release path end to end.
- Keyboard-only and screen reader passes, reduced motion in the browser, multisite network activation, other themes and page builders, and delivery to an external inbox.
- Consider a Codex Security diff review.

## Third-party code

- `plugin-update-checker/`: [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker "Plugin Update Checker on GitHub") 5.7 by Jānis Elsts (MIT license). Unmodified upstream copy, replaced as a whole folder on upgrade.
- No other third-party libraries. No jQuery, jQuery UI, icon fonts or autolinker library is bundled.

## Community support

Questions and ideas at https://github.com/hackrepair/thrg-stickies/discussions, reproducible bugs at https://github.com/hackrepair/thrg-stickies/issues, and security reports privately to jim@hackrepair.com.

## 2.0.0 Zendogs validation, October 23, 2026

Installed the locally built 2.0.0 package in the Zendogs sandbox at localhost:8081. Backed up the installed plugin and full local database to releases/zendogs-2.0.0-backup before installation. Schema upgrade and all 49 WordPress integration assertions passed. Ego Lite space 57 verified creation/save, links, color, private visibility, resolve/Undo, hide/show, mobile panel at 390px without overflow, keyboard move/resize, settings save, autosave, persistence, plugin details, delayed-save text preservation, and stale revision HTTP 409. Removed test note 30 and restored autosave off. Existing notes retained. PHPCS and JS syntax passed after final save correction. Screenshots: .review/200-site.png and .review/200-save.png (intermediate states).

Limits: no fresh pointer-drag/resize gesture, mention-suggestion UI, bulk-action UI, logged-out browser session, screen reader, multisite, external inbox, or published updater installation test in this pass. Mention email and role/visibility behavior passed integration tests; these do not replace browser or external-delivery proof. GitHub release remains unpublished.

## 2.0.1 email status

Notes list records the latest attempted mention email per recipient: Accepted by mailer, Send failed, or No recorded send. Timestamps use UTC. No raw mail errors, addresses, or message bodies are stored in this status. Mail metadata writes do not change note revisions. Schema 4 adds nullable mail_status; old notes remain unknown. Local Zendogs integration: 52 assertions passed, including acceptance, forced failure, and unchanged revisions. PHPCS passed. Mailer acceptance does not establish inbox delivery.
