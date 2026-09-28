=== DocxToPost – Convert DOCX & Markdown to WP Posts, Pages & Custom Post Types ===
Contributors:      nagarajdev
Tags:              docx, word, markdown, importer, converter
Requires at least: 6.0
Tested up to:      7.1.2
Requires PHP:      8.0
Stable tag:        1.2.3
License:           GPLv2 or later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Import .docx and Markdown files as WordPress Posts, Pages, or Custom Post Types in seconds. No copy-paste. No block editor fighting.

== Description ==

**DocxToPost** is the easiest way to turn Microsoft Word documents — and Markdown files — into clean WordPress posts, pages, or custom post types.

No more copy-pasting from Word and losing your formatting. Upload your .docx file and DocxToPost parses it into clean, semantic HTML that WordPress understands — including every embedded image, which is uploaded to your Media Library automatically.

Writing in Markdown instead? Drop in a `.md` file and it becomes the same clean set of blocks — headings, lists, tables, code blocks and all. Notes from Obsidian or Notion, a README, or the output of an AI assistant all import without reformatting.

**Everything in the free version:**

* **Real blocks, not one big lump:** Your document arrives as proper paragraph, heading, list, image, table and quote blocks you can move and edit individually — not a single Classic block you have to fight. Running the classic editor instead? It detects that and gives you plain HTML.
* **Undo an import:** Changed your mind? One link on the confirmation screen moves the post to the trash.
* **Simple upload:** Drag and drop your .docx or .md file.
* **Markdown too:** `.md` and `.markdown` files import with headings, emphasis, links, nested lists, blockquotes, tables and fenced code blocks intact. Code becomes a real Code block, not a paragraph in a monospace font.
* **Formatting preserved:** Headings, paragraphs, bold, italic, underline, strikethrough, superscript, subscript, blockquotes and paragraph alignment all survive the trip.
* **Real lists:** Bulleted and numbered lists come through as proper lists, nested sub-bullets included.
* **Tables with headers:** Header rows become real table headers, so your tables stay readable and accessible.
* **Embedded image extraction:** Images inside your Word document are pulled out and added to the Media Library, with the alt text you set in Word.
* **All public post types:** Publish to Posts, Pages, or any Custom Post Type registered on your site.
* **Full status options:** Publish immediately, save as Draft, submit for Pending Review, or mark Private.
* **Live preview:** See exactly how the post will look before you publish.
* **Featured images:** Upload a featured image alongside your document.
* **Categories and tags:** Assign both while you import.
* **Post meta:** Edit the slug, excerpt and author.
* **Role-based access:** Choose which user roles can use the plugin.

= Why upgrade to Pro? =

The free version is a complete, unrestricted DOCX and Markdown importer for one document at a time. Pro is for the day you have a folder of them:

* **Bulk import, up to 100 files a run** — drag in .docx and .md files together. Each file uploads in its own request, so shared-hosting limits on upload count, post size and execution time never come into it. A corrupt document fails its own row and the rest carry on. Pause, resume, cancel, or retry only the failures — and close the tab if you like, because the queue lives in the database rather than the page.
* **A whole notebook, images and all** — drop your `_resources`, `attachments` or exported image folder in with the Markdown files and every image referenced by a relative path is matched by filename and placed in its post. That is a Joplin notebook, an Obsidian vault or a Notion export moved across in one run, rather than image by image in the editor.
* **Review everything before anything is created** — a table of every document with its detected title, featured image and SEO fields, each editable per row, and a toggle to leave any document out of the run. Nothing is written to your site until you say so.
* **Drip publishing** — schedule a batch instead of publishing it all at once. "One post per weekday from Monday at 09:00" is two clicks, weekends are excluded by default, and you can override any single date by hand without disturbing the rest of the sequence.
* **SEO automation** — SEO title, meta description and focus keyphrase for Yoast SEO and Rank Math, filled from templates using `{title}`, `{sitename}`, `{excerpt}`, `{filename}`, `{category}` and `{date}`, with per-document overrides.
* **Smart image optimization** — JPEG and PNG images from your documents are converted to WebP as they are imported, but only when the WebP is genuinely smaller, so photographs shrink while flat logos and diagrams keep their original format. Transparency is preserved, your originals are never deleted, and each import reports how much it saved.

Pro also adds one-click rollback of an entire import, import history with rollback from any past job, duplicate handling (skip, import anyway, or update the existing post), a featured-image cascade that matches companion images to their documents by filename, an activity log with re-import, automatic updates, and email support from the developer.

Learn more at [docxtowp.com](https://docxtowp.com)

== Installation ==

1. Install through the WordPress plugins screen, or upload the plugin files to `/wp-content/plugins/docxtowp`.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Go to **DocxToPost** in the admin menu and upload your first .docx or .md file.

== Frequently Asked Questions ==

= What post types does this plugin support? =
All registered public post types on your site — Posts, Pages, and any Custom Post Type. You can only create the types your user role already allows.

= Does it preserve formatting from Word? =
Yes. Headings, paragraphs, bold, italic, underline, strikethrough, superscript, subscript, bulleted and numbered lists (including nested levels), tables with header rows, blockquotes, paragraph alignment, and hyperlinks all carry over.

= Can I import Markdown files? =
Yes. Upload a `.md` or `.markdown` file the same way you would a .docx. Headings, paragraphs, bold, italic, strikethrough, links, nested lists, blockquotes, tables, horizontal rules and fenced code blocks are all supported — code blocks become real Code blocks in the editor. The first `# Heading` becomes the post title; if there is none, the filename is used.

Front matter (the `---` block some tools put at the top) is removed rather than shown in the post. Its `title:` is used as the post title; other keys such as slug, categories and dates are not read. Images with a full `https://` URL are kept as external images. Images with a relative path like `images/photo.jpg` cannot be resolved from a single uploaded file, so they are left out and the preview screen tells you which ones — upload them to the Media Library and add them in the editor.

= Where does Markdown from ChatGPT, Notion or Obsidian fit? =
Save or export it as a `.md` file and upload that. All three produce standard Markdown, which is what the importer reads. Notion's export puts images in a folder next to the file — see the previous answer for how those are handled.

= I imported the wrong document. Can I undo it? =
Yes. The confirmation screen has a "Move it to the trash" link, and the post stays recoverable from the Trash afterwards.

= Why will my file not upload? =
Go to Tools → Site Health → Status. DocxToPost adds a check there that reports the three usual causes: a missing PHP extension, an upload directory that is not writable, or a file size limit set higher than the server will actually accept.

= What happens to images inside my Word document? =
They are extracted, uploaded to the Media Library, and placed inline in the post. If you set alt text on an image in Word, that alt text comes with it.

= What status options are available? =
Publish, Draft, Pending Review, and Private. If your role cannot publish, the post is saved for review instead of being discarded.

= Is there an upload limit? =
There is no limit on how many documents you can convert. A per-file size limit (10MB by default) can be changed under Settings, up to whatever your server's PHP configuration allows.

= Do I need the block editor? =
No. By default the plugin looks at which editor your post type actually uses and matches it — block markup for the block editor, plain HTML for the classic editor. You can override that under Settings → Content Format if you would rather always get one or the other.

= Will my imported post be one big Classic block? =
Not any more. Each paragraph, heading, list, image, table and quote becomes its own block, so you can drag, duplicate and edit them the same as anything you wrote in the editor. Anything with no block equivalent is kept intact in an HTML block rather than being dropped.

= Does the plugin send my documents anywhere? =
No. Everything is parsed on your own server, and the plugin makes no outbound requests.

== Screenshots ==

1. Upload screen — drag and drop your .docx or .md file and an optional featured image
2. Preview and publish — the parsed document with post type, status, author, featured image, categories and tags
3. Confirmation after the post is created
4. Settings — file size limit, post defaults and which roles may use the plugin

== Changelog ==

= 1.2.3 =
* Changed: the Pro description no longer says Smart image optimization is "coming in Pro 1.3.0". It shipped in DocxToWP Pro 1.3.0 on 25 September 2026, so the readme and the Upgrade page now describe it in the present tense.
* Note: nothing functional changed. Importing, settings and Markdown import behave exactly as they did in 1.2.2.

= 1.2.2 =
* Fixed: **the document's heading appeared again as the first line of the post.** The title is taken from the first Heading 1 *or* Heading 2, but only a Heading 1 was ever removed from the body — so a document whose top heading was Heading 2 ended up with that heading repeated directly under the identical post title. Whichever heading supplies the title is now the one removed; later headings of the same level are untouched.
* Added: **Settings → Title From Filename**, with three choices for how a filename becomes a title when a document has no heading to take one from: sentence case ("Annual report for the board", the new default), Title Case ("Annual Report for the Board"), or left as written. It used to be "Annual Report For The Board" always, capitalising every word including *for* and *the*, which matches no house style. Sentence case never lowercases anything — it capitalises the first word and leaves the rest as typed, so `meeting-with-John-Smith.docx` stays "Meeting with John Smith" and `report-NHS.docx` keeps NHS. A document that has a heading keeps that heading's capitalisation exactly as you wrote it; this setting only affects titles taken from a filename.
* Note: a document with a heading is unaffected by the new setting, and Markdown import is unchanged. The same two fixes ship in DocxToWP Pro 1.2.10.

= 1.2.1 =
* Fixed: **headings in some Word documents imported as ordinary paragraphs**, even though the author had applied genuine Heading styles throughout. The importer recognised a heading only when the style's internal ID was literally `Heading1`…`Heading6`, and that ID is not stable: Word in German writes `berschrift1`, in French `Titre1`, in Spanish `Ttulo1`; Word also mints `Heading11` when a heading style is pasted between documents; and a custom style based on a heading has whatever ID its author gave it. The importer now reads the document's style definitions and resolves headings the way Word's own navigation pane does — by the style's canonical name, its outline level, or the heading it is based on — so all of those import as real headings. An outline level set directly on a paragraph is honoured too, and the built-in "TOC Heading" style correctly stays a paragraph.
* Note: Word import is otherwise unchanged, and Markdown import is unchanged. The same fix ships in DocxToWP Pro 1.2.6.

= 1.2.0 =
* Added: **Markdown import.** Upload a `.md` or `.markdown` file and it goes through the same preview-and-publish flow as a Word document. Headings, paragraphs, bold, italic, strikethrough, links, nested lists, blockquotes, tables (with column alignment), horizontal rules and fenced code blocks are all preserved.
* Added: a **Code block** in the block-editor output. Markdown code fences become real `core/code` blocks, with the content escaped so that a `[shortcode]` in a code sample is never executed.
* Added: the first `# Heading` in a Markdown file becomes the post title, as the first Heading 1 does in Word. If there is none the filename is used. A `title:` in front matter takes precedence, and the front matter block itself is removed from the post.
* Added: the preview screen now lists anything the parser had to leave out. Today that is Markdown images with a relative path, which cannot be resolved from a single uploaded file. Images with a full https:// URL are kept.
* Changed: the Site Health check wording distinguishes Word documents, which need the ZipArchive extension, from Markdown files, which do not.
* Security: Markdown permits raw HTML, so every converted document is passed through WordPress's post-content sanitiser before it reaches the preview screen or the post. Markdown files are parsed straight from the upload buffer and never written to disk.
* Note: Markdown parsing uses Parsedown 1.7.4 (MIT licence), bundled under a plugin-specific class name so it cannot collide with another plugin's copy. Word import is unchanged.

= 1.1.1 =
* Added: a dismissible notice on the plugin's own upload screen, letting you know that lifetime access to the Pro version ends on 30 September 2026. It counts down from your own server clock, disappears by itself once the date passes, and never appears anywhere outside this plugin's pages. The free plugin is not affected by the change and stays free.
* Changed: the two pages describing the Pro version no longer say how Pro is sold. They pointed at a one-time purchase, and the pricing model is changing, so they now point at the website for current pricing instead. Wording only.
* Note: nothing else changed. No functional differences, no new settings, and nothing about importing behaves differently.

= 1.1.0 =
* Added: **block editor output.** Imported documents now become real paragraph, heading, list, image, table and quote blocks instead of one Classic block containing the whole document. Anything without a block equivalent is preserved in an HTML block.
* Added: Settings → Content Format, with "Match the editor" (the default, which follows whichever editor the post type uses), "Always block editor" and "Always classic HTML".
* Added: **blockquotes** — Word's Quote and Intense Quote styles now become real blockquotes instead of plain paragraphs.
* Added: **paragraph alignment** — centred, right-aligned and justified paragraphs and headings keep their alignment.
* Added: **undo an import** — a "Move to trash" action on the confirmation screen, for when a document was not what you expected.
* Fixed: the confirmation screen said "Published successfully!" whatever had happened, including directly above a "Draft" badge. It now reports what actually happened — published, saved as a draft, submitted for review, scheduled or published privately.
* Fixed: a draft had no way to see the result. The confirmation screen now offers a Preview link when the post is not published.
* Added: **duplicate warning** — the preview screen tells you when a post with the same title already exists, and links to it. Advisory only; it never blocks.
* Added: **Site Health check** — Tools → Site Health now reports whether the required PHP extensions are present, whether the upload directory is writable, and whether your file size limit exceeds what the server will actually accept.
* Added: an Upgrade page describing what the Pro version does, and a Bulk Import (Pro) page describing that feature specifically. Both are descriptions — no locked or disabled functionality ships in the free plugin.
* Added: occasional dismissible notices on the plugin's own screens once you have converted a few documents. Each appears once, is dismissible, and stays dismissed.
* Note: this affects new imports only. Posts you have already created are ordinary WordPress posts and are not touched.

**Privacy:** the plugin counts how many documents you have converted, stored against your user account on your own site. It is used only to decide when to show the notices above. Nothing is sent anywhere — the plugin still makes no outbound requests.

= 1.0.1 =
* Fixed: three buttons in the admin — including "Upload Another" on the success screen — linked to a page that was never registered and returned a permissions error.
* Fixed: numbered lists were imported as bulleted lists. Word gives both the same paragraph style, so the list format is now read from the document's numbering definitions.
* Fixed: nested sub-bullets were flattened into a single level. List nesting is now preserved to any depth.
* Fixed: table header rows were imported as ordinary cells. They now become real `<thead>` and `<th>` elements.
* Fixed: a slightly corrupt .docx failed on the code path meant to recover it, because of a PHP constant that does not exist.
* Fixed: images lost their sizing, so wide images could overflow their container.
* Fixed: comments were forced on for every imported post, overriding the site's own default.
* Fixed: error messages returned the server's file paths to the browser. They now go to the error log.
* Added: strikethrough, superscript and subscript are now preserved.
* Added: alt text set on an image in Word is now saved to the Media Library attachment, not just the post content.
* Added: a dismissible card describing the Pro version, on the plugin's own screens only.
* Security: creating a post now checks that your role is allowed that post type, that you may publish rather than only draft, and that you may attribute a post to another user.
* Performance: the cleanup task is scheduled on activation instead of being checked on every page load.

= 1.0.0 =
* Initial public release
* Support for .docx to Post, Page, and Custom Post Type conversion
* Formatting preservation and live preview
* Embedded image extraction and auto-upload to Media Library
* Featured image support
* Categories, tags, and author support
* Publish, Draft, Pending, and Private status options
* Role-based access control

== Upgrade Notice ==

= 1.2.2 =
Stops a document's heading being repeated as the first line of the post, and adds a setting for how a filename becomes a title. Recommended for everyone.

= 1.2.1 =
Fixes Word headings importing as plain paragraphs in documents from non-English Word or with custom heading styles. Recommended for everyone.

= 1.2.0 =
Adds Markdown (.md) import alongside .docx, with real Code blocks in the editor output. Word import is unchanged. Recommended for everyone.

= 1.1.1 =
Wording change on the two Pro description pages. No functional changes. Safe to skip if you never open those pages.

= 1.1.0 =
Imports now produce real editor blocks instead of one Classic block, and numbered lists, nested bullets and table headers all import correctly. Recommended for everyone.

= 1.0.1 =
Fixes numbered lists, nested bullets and table headers importing incorrectly, repairs three broken admin links, and tightens permission checks. Recommended for everyone.

= 1.0.0 =
Initial release — enjoy clean DOCX to WordPress conversion!
