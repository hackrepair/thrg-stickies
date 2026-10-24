=== The Hack Repair Guy's Stickies ===
Contributors: hackrepair
Tags: sticky notes, notes, feedback, collaboration, review
Requires at least: 6.9.4
Requires PHP: 7.4
Tested up to: 7.1
Stable tag: 1.1.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Support link: https://github.com/hackrepair/thrg-stickies/discussions

Pin sticky notes to the exact spot on any page of your site, mention a teammate, and mark the note resolved when the fix is done.

== Description ==

"The button on the contact page is the wrong color." "Client wants this paragraph shorter." "The plugin conflict shows up right here." Stickies lets you say it right where it happens.

Open any page of your site while logged in and click the note button on the left edge. A paper-yellow note appears. Type, drag it to the spot, resize it, and save. A Hack Repair heart pin shows the note is saved. Everyone on your team with Stickies access sees it in the same place.

**Made for the people building and looking after the site.** Administrators and editors can use Stickies as soon as it is activated, and you choose which roles can see, add or manage notes. Visitors never see notes, and no Stickies files load for them.

* Pin notes on any page of the site and on dashboard screens (both on by default)
* Drag by the top edge or the pin, resize from the corner, and save with the check button or Ctrl+S (Cmd+S on a Mac)
* Five colors, chosen from the button at the top right: yellow, blue, green, pink and gray
* Mention a teammate with @ and they get an email with a link straight to the note
* Choose who sees each note: the whole team, only the people mentioned, or just you
* Mark a note resolved when the work is done; it leaves the page with an Undo message and can be reopened from the notes panel or the Stickies list
* The notes panel lists every note on the page, with a count in the toolbar and the admin bar
* The Stickies menu in the dashboard lists every note on the site, with filters, search and bulk actions
* Web addresses in a note become links
* Works with mouse, touch and keyboard: with a note focused, arrow keys move it and Shift+arrow keys resize it
* On small screens, notes are listed in the panel instead of being drawn over the page

= Privacy =

Notes are stored in your own WordPress database and are only shown to logged-in people with Stickies permissions. Notes are fetched after the page loads, so cached pages never contain them. The only thing that leaves your site is the mention email, sent through your site's normal mail setup. Stickies makes no outside requests apart from checking GitHub for plugin updates.

= Mention emails =

Subjects follow `[example.com] [Stickies] You were mentioned in a note`. The email names who mentioned you, shows the note, and links to the page with the note highlighted. Each person gets at most one email per note every 10 minutes. You can turn mention emails off in Stickies > Settings.

= Credits =

Inspired by the 2014 WP Sticky Notes plugin by Soso Kruashvili (GPLv2). Stickies is a new plugin written from scratch; no code, images or text from it are used.

Update checks use [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker "Plugin Update Checker on GitHub") by Jānis Elsts (MIT license), included unmodified.

== Installation ==

1. In WordPress, open Plugins > Add New > Upload Plugin and select the plugin ZIP. You can also upload the thrg-stickies folder to wp-content/plugins/.
2. Activate The Hack Repair Guy's Stickies.
3. Open any page of your site while logged in and click the note button on the left edge, or choose Stickies in the admin bar.
4. Optional: review Stickies > Settings to choose which roles can see, add and manage notes, and whether notes appear on the site, on dashboard screens, or both.

== Support ==

For questions, ideas and community support, visit GitHub Discussions:
https://github.com/hackrepair/thrg-stickies/discussions

Report reproducible bugs through Issues:
https://github.com/hackrepair/thrg-stickies/issues

Report security concerns privately to Jim Walker at jim@hackrepair.com. Do not include credentials or sensitive site information in public posts.

== Frequently Asked Questions ==

= Can visitors see my notes? =

No. Notes, the toolbar and every Stickies file load only for logged-in people whose role has Stickies access. Visitors and search engines never see them.

= Who can use Stickies? =

Administrators always can. Editors can see and add notes by default. In Stickies > Settings you can give any role permission to see notes, add notes, or manage everyone's notes. Everyone can edit, move, resolve and delete their own notes.

= Who can resolve a note? =

The person who wrote it, anyone who manages notes, and anyone mentioned in it.

= Why is my note in a slightly different place on another screen? =

Notes remember their distance from the top of the page and from the center of the window, so they stay in place on centered layouts as the window changes size. Responsive themes can move content around on narrow screens, so below the width set in Stickies > Settings (782 pixels by default) notes are listed in the notes panel instead.

= Does a note follow the page if I add query strings or tracking links? =

Yes. Tracking parameters such as utm_source and fbclid are ignored, and so are http/https and the domain, so notes survive a move from staging to live.

= Can I put notes on dashboard screens? =

Yes. Dashboard screens are on by default. You can turn them off with "On dashboard screens" in Stickies > Settings.

= What happens to my notes if I uninstall? =

They are kept unless you tick "Delete all plugin data when the plugin is deleted" in Stickies > Settings. With that ticked, deleting the plugin removes every note, the settings and the Stickies permissions.

== Changelog ==

= 1.1.2 =
* Resolved notes leave the page; open them from the notes panel or the Stickies list.
* The push-pin is now a Hack Repair heart and can be dragged to move the note.
* A soft sheen confirms a color change.
* Friendlier messages for empty and unsaved notes.

= 1.1.0 =
* Added a Stickies item to the left dashboard menu, with the note list and Settings under it.
* Notes show on the site and on dashboard screens by default.
* Added a help tooltip next to the note visibility menu.
* Moved the color button to the top right of each note and made it larger.
* The resolve button now explains itself, lines up with the color button and shows an Undo message.
* Fixed spacing of the empty notes panel message.

= 1.0.0 =
* First release.
