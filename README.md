<p align="center">
  <img src="assets/icon-256x256.png" alt="The Hack Repair Guy heart with a pinned sticky note" width="144" height="144">
</p>

<h1 align="center">The Hack Repair Guy's Stickies</h1>

Pin sticky notes to the exact spot on any page of your WordPress site, mention a teammate, and mark the note resolved when the fix is done.

"The button on the contact page is the wrong color." "Client wants this paragraph shorter." "The plugin conflict shows up right here." Stickies lets your team say it right where it happens. Notes are only for logged-in people with Stickies access; visitors never see them.

<p align="center">
  <strong>▶ Watch Stickies in action</strong><br>
  <a href="https://www.youtube.com/watch?v=cvO_PknGIyA"><img src="https://img.youtube.com/vi/cvO_PknGIyA/hqdefault.jpg" alt="Free Sticky Notes for Your WordPress Team: watch on YouTube" width="480"></a>
  <br><br>
  <a href="https://github.com/hackrepair/thrg-stickies/releases/latest" title="Download the latest Stickies plugin zip from GitHub"><img src="https://img.shields.io/badge/%E2%AC%87%20%20Download%20the%20latest%20release-b34700?style=for-the-badge&labelColor=b34700" alt="Download the latest release" height="46"></a>
  <br>
  <sub>Free. Upload the zip in WordPress, and later updates arrive on the Plugins screen.</sub>
  <br><br>
  <a href="https://hackrepair.github.io/thrg-stickies/" title="The Stickies project page">Visit the Stickies page</a>
</p>

## What it does

- A small toolbar on the left edge of every page (and a Stickies item in the admin bar) adds a note, hides or shows notes, and opens the list of notes on the page.
- Notes are paper-yellow by default, with blue, green, pink and gray as options. Pick a color from the button at the top right. Drag by the top edge or by the pin, resize from the corner, and pick a color: these save the moment you let go. Save text with the check button or Ctrl+S / Cmd+S. A Hack Repair heart pin shows a saved note.
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

## Frequently asked questions

### Why does my mention show “No recorded send”?

Mentioning your own logged-in account does not send you an email. This is intentional, including on Team notes with mention emails enabled. Use a different administrator or editor to test.

The label means there is no saved send result. It also covers older mentions from before 2.0.1 and skipped notifications: emails disabled, invalid recipient/email, recipient unable to see the note, or the 10-minute cooldown.

### Does saving or resolving a note resend emails?

No. Only newly added mentions trigger notification attempts. Re-saving the same mention, resolving, or reopening a note does not resend it.

### How do I test it?

Enable mention emails, create a new Team note, select another administrator or editor from the @ dropdown, and save. Refresh the notes list for the send result and check Mailpit if used locally. If the new email arrives but no result is recorded, investigate the database upgrade and status write.

### Why is an editor missing from mentions or unable to see Stickies?

Role-editing tools or migrations can remove WordPress’s stored Stickies capabilities while the saved Editor settings remain checked. Starting with 2.0.4, Stickies reconciles its role capabilities with the saved permission matrix on each request, writing only when they differ. Individual user restrictions are preserved. Stickies settings are the source of truth: if a role editor grants or removes Stickies access for a role directly, the next page load changes it back to match Stickies > Settings. Change Stickies access in Stickies > Settings, not in a role editor. If you type a name in the @ list and the person is missing because their role cannot see notes yet, administrators see a short note saying so (with the account email and role) and a link to Stickies > Settings. Other users see no such note.

### How can I tell two people with the same name apart?

Administrators who can list users see email addresses next to names and can search by email. Other Stickies users see account IDs. Only eligible users appear, and selecting a mention stores the account ID, so matching names remain separate people.

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
php tests/revision.php
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

### 2.1.1
- The Stickies list now pages and counts only the notes you can open. Before, "Mentioned only" notes you were not part of could leave short pages and inflate the total.
- The most notes allowed on one page now follows the `thrg_stk_page_limit` filter everywhere. Before, the page itself stopped loading at 200 even when the limit was raised.

### 2.1.0
- Fixed saved notes showing every line break as an extra blank line. The welcome note is also taller so its full message fits.
- Removed leftover code from the retired collapsed-pin design for resolved notes (an unused element, tooltip, click handler and styles, plus an unused page class). Nothing visible changes.
- Deleting Stickies with Delete all plugin data ticked now also removes the update checker's saved state and its rate-limit note.
- A welcome note now greets you on the home page the first time Stickies is installed. It is marked Just me, so only the administrator who activated Stickies sees it, and you can resolve or delete it like any other note.
- The welcome message on the dashboard links straight to that note while it is still open.
- Reinstalling with your notes kept, or activating from WP-CLI, does not add a welcome note.

### 2.0.7
- Administrators now get a short note in the @ mention list when someone matches the name but cannot be mentioned because their role cannot see notes yet, with a link to Stickies > Settings. Other users see no change and no account details.
- Fixed a rare case where pressing Enter after a search found no one could pick the person from the previous search.
- Removed unused code and two unused text strings ("Saving…" and "Only the author can change this note."). Translators can drop them.

### 2.0.6
- Moving, resizing or recoloring a saved note now saves as soon as you let go. Before, the change was lost if you left the page without clicking the check button.
- A move or color change made just before you leave the page is still saved.
- Text still waits for the check button (or Ctrl+S / Cmd+S) unless autosave is on, so half-typed notes are never saved by accident.
- The autosave setting now covers text only.

### 2.0.5
- Mention emails show the author and page name in bold again, and the footer uses one translatable string.
- A save that loses a race to another edit now returns the latest note with a Reload button; a real database error says so plainly.
- Bulk resolve and reopen use the revision they already loaded.
- Bulk and row actions in the Stickies list keep your search, filter, sort and page.
- The Stickies toolbar no longer loads inside the View details window.
- Documentation corrections.

### 2.0.4
- Repair missing role capabilities from the saved Stickies permissions, restoring editor controls and mention eligibility.
- Distinguish matching names in mention suggestions; administrators can identify and search eligible accounts by email.
- Keep email addresses out of suggestions for users who cannot list accounts.

### 2.0.3
- Declare WordPress 7.1.3 compatibility consistently in the readme and native View details.
- Add the walkthrough thumbnail to the View details banner and a clickable local video preview.

### 2.0.2
- Explain self-mentions, No recorded send, and notification troubleshooting in the FAQ.
- Add the Stickies walkthrough video link.
- Include the Support tab in View details.
- Remove the repeated delivery disclaimer from the notes list.

### 2.0.1
- Record mention email acceptance and failures per recipient in the notes list.
- Show attempt timestamps and distinguish mailer acceptance from inbox delivery.

### 2.0.0 (not released; included in 2.0.1)
- Preserve edits typed while saving.
- Add atomic revision checks to prevent concurrent overwrites.
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
