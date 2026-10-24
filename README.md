<p align="center">
  <img src="assets/icon-256x256.png" alt="The Hack Repair Guy heart with a pinned sticky note" width="144" height="144">
</p>

<h1 align="center">The Hack Repair Guy's Stickies</h1>

Pin sticky notes to the exact spot on any page of your WordPress site, mention a teammate, and mark the note resolved when the fix is done.

"The button on the contact page is the wrong color." "Client wants this paragraph shorter." "The plugin conflict shows up right here." Stickies lets your team say it right where it happens. Notes are only for logged-in people with Stickies access; visitors never see them.

Current release: **2.0.1**.

## What it does

- A small toolbar on the left edge of every page (and a Stickies item in the admin bar) adds a note, hides or shows notes, and opens the list of notes on the page.
- Notes are paper-yellow by default, with blue, green, pink and gray as options. Pick a color from the button at the top right. Drag by the top edge or by the pin, resize from the corner, and save with the check button or Ctrl+S / Cmd+S. A Hack Repair heart pin shows a saved note.
- Type `@` to mention a teammate. They get an email linking straight to the note.
- Each note can be seen by the whole team, only the people mentioned, or just you. The ? beside the menu explains each choice.
- Mark a note resolved with the round check button. It leaves the page, with an Undo message, and stays in the notes panel (tick Show resolved) and the Stickies list, where it can be reopened.
- **Stickies** in the dashboard menu lists every note on the site with filters, search, and bulk resolve, reopen and delete.
- **Stickies > Settings** sets which roles can see, add or manage notes, whether notes show on dashboard screens, where the controls appear, mention emails, autosave and uninstall cleanup.

## Privacy and scope

Notes live in one table in your site's database. They are fetched after the page loads through the WordPress REST API, so page caches never contain them. Nothing loads for visitors. Mention emails leave the site, update checks contact GitHub, and mention suggestions may load avatars from Gravatar or your configured avatar provider. The plugin is not a public comment system and has no anonymous notes.

## Install

1. Download `thrg-stickies.X.Y.Z.zip` from the latest release.
2. In WordPress, open Plugins > Add New > Upload Plugin and choose the ZIP.
3. Activate it, open any page of your site while logged in, and click the note button on the left edge.

Updates arrive through the normal WordPress Plugins screen from this repository's GitHub releases.

## Development

Requires WordPress 6.9.4+ and PHP 7.4+. Tests are plain PHP scripts:

```
php tests/url.php
php tests/content.php
php tests/access.php
php tests/email.php
php tests/schema.php
php tests/smoke.php
php tests/uninstall.php
python3 tests/package-permissions.py
```

`tests/integration.php` runs against a real WordPress install (it loads `wp-load.php`; see the file header). It creates temporary users and notes and removes them afterwards.

Run shared PHPCS with `/Volumes/BACKUP8TB2/plugins/PHPCS/run-phpcs.sh "$PWD"`. Build a release ZIP with `python3 scripts/build-release.py`.

See [PROJECT.md](PROJECT.md "Architecture, design decisions and known limits") for the architecture, design decisions and known limits.

## Author and support

**[Jim Walker](https://hackrepair.com/ "The Hack Repair Guy: WordPress security and hack repair")**, The Hack Repair Guy, author and maintainer.

[Questions, ideas and community support](https://github.com/hackrepair/thrg-stickies/discussions "GitHub Discussions") · [Report a bug](https://github.com/hackrepair/thrg-stickies/issues "GitHub Issues") · [Security reporting](mailto:jim%40hackrepair%2Ecom "Email Jim privately") · [Buy Jim a coffee](https://hackrepair.com/donations/buy-jim-a-coffee "Support The Hack Repair Guy")

## License and credits

[GPLv2 or later](https://www.gnu.org/licenses/gpl-2.0.html "Read the GNU General Public License, version 2").

Inspired by the 2014 WP Sticky Notes plugin by Soso Kruashvili (GPLv2). Stickies is a new plugin written from scratch; no code, images or text from it are used.

Update checks use [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker "Plugin Update Checker on GitHub") by Jānis Elsts (MIT license), included unmodified.

Made by [The Hack Repair Guy](https://hackrepair.com/ "The Hack Repair Guy: WordPress security and hack repair").

## Changelog

### 2.0.1
- Show per-recipient mention email acceptance/failure and attempt timestamps in the notes list.
- Historical emails display No recorded send; acceptance does not confirm inbox delivery.

### 2.0.0
- Preserve edits during manual saves and protect concurrent updates with revisions.
- Translate mention emails and clarify avatar privacy.


### 1.1.2
- Resolved notes leave the page; open them from the notes panel or the Stickies list.
- The push-pin is now a Hack Repair heart and can be dragged to move the note.
- A soft sheen confirms a color change.
- Friendlier messages for empty and unsaved notes.

### 1.1.0
- Added a Stickies item to the left dashboard menu, with the note list and Settings under it.
- Notes show on the site and on dashboard screens by default.
- Added a help tooltip next to the note visibility menu.
- Moved the color button to the top right of each note and made it larger.
- The resolve button now explains itself, lines up with the color button and shows an Undo message.
- Fixed spacing of the empty notes panel message.

### 1.0.0
- First release.

Privacy clarification: Mention suggestions load avatars using WordPress’s configured avatar provider, which may contact Gravatar. GitHub is contacted for plugin update checks.
