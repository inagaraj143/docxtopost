# Releasing to WordPress.org

Current release: **1.2.3**, not yet published. **1.2.2 is already live** on
WordPress.org (published 23 September 2026), so everything below 1.2.3 in
this file is history — do not re-tag it.

Update the version references below when cutting a new one, or run
`../build-svn-release.ps1`, which reads the version out of the plugin header
and refuses to run when readme.txt disagrees.

> **Check wordpress.org before choosing a version number, and do not trust
> the line above.** It has been wrong twice. Work was once labelled 1.2.1
> when 1.2.1 had already shipped; and this file still read "1.2.2, not yet
> published" on 28 September, five days after 1.2.2 went live — because
> publishing happens by hand in SVN and nothing makes it update this file.
> The published version is one request away, and it is the only source that
> cannot go stale:
>
> ```powershell
> curl.exe "https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request%5Bslug%5D=docxtowp" | ConvertFrom-Json | Select-Object version, tested, last_updated
> ```
>
> Note the slug is **`docxtowp`**, not `docxtopost`. The local folder was
> renamed; a published WordPress.org slug cannot be.

## What is in 1.2.3

Description only. No code path changed, and no test needs re-running.

```
readme.txt / README.md             MOD  changelog, stable tag, and the Pro
                                        pillar drops "(coming in Pro 1.3.0)"
admin/upgrade-page.php             MOD  same label dropped from the pillar
                                        lead; the comment above it now states
                                        the rule for the next unreleased
                                        feature rather than describing this one
docxtopost.php                     MOD  version only
```

DocxToWP Pro 1.3.0 shipped on 25 September 2026, so Smart image optimization
is no longer forthcoming and the free plugin should stop saying it is. This is
the whole release: one commit, `a38ea73`, made two days after 1.2.2 went live.

Worth shipping on its own rather than waiting for the next fix, for two
reasons. The live readme currently advertises a Pro feature as "coming" when a
customer who clicks through can already buy it. And the directory's search
ranking notices `last_updated`, so a description correction that was going to
happen anyway is better spent as its own bump than folded into a later one.

No pre-flight beyond the lint — there is no behaviour to test:

```powershell
php -l docxtopost.php
```

Then stage and commit as in Step 2 onward, with `-Tag 1.2.3`.

## What is in 1.2.2  (published 23 September 2026)

Two fixes, both from one Pro customer's email, plus the Pro descriptions.

```
includes/class-dtpost-parser.php   MOD  the heading the title came from is the
                                        heading removed from the body; the
                                        filename fallback goes through
                                        DTPost_Title
includes/class-dtpost-title.php    NEW  sentence case / Title Case / as-written
admin/settings-page.php            MOD  Title From Filename select + sanitise
docxtopost.php                     MOD  requires the new class; registers
                                        dtpost_filename_title_case default
tests/test-title.php               NEW  26 checks over the three modes
tests/test-headings.php            MOD  5 checks for the duplication fix
readme.txt / README.md             MOD  changelog, stable tag, tested up to
                                        7.1.2, six Pro pillars
admin/upgrade-page.php             MOD  3 pillars -> 6
admin/bulk-page.php                MOD  Markdown notebooks + their images
```

A document whose top heading was Heading 2 had that heading repeated as the
first line of the post: the title is taken from the first h1 *or* h2 but only
an h1 was ever removed. And the filename fallback used `ucwords()`, giving
"Annual Report For The Board" — no house style capitalises every word. Both
ship in DocxToWP Pro 1.2.10; keep the two plugins in step.

Already published — **do not re-run the staging below for 1.2.2.** It is kept
as the worked example, because the next release repeats it with a new tag.

The checks that were run before tagging:

```powershell
php tests/test-headings.php      # expect "16 passed, 0 failed."
php tests/test-title.php         # expect "26 passed, 0 failed."
php tests/test-markdown.php      # expect "54 passed, 0 failed."
php -l includes/class-dtpost-parser.php; php -l includes/class-dtpost-title.php
```

On a real site: import a .docx whose top heading is **Heading 2** and confirm
that heading is not repeated under the title. Then import one with no heading
at all and check the title reads as a sentence, not As A Headline. Then the
staging and commit, which for 1.2.2 was:

```powershell
cd D:\DEV\htdocs\docxtowp
.\build-svn-release.ps1 -SvnPath D:\DEV\svn-docxtowp -Tag 1.2.2
cd D:\DEV\svn-docxtowp
svn status                       # expect A for the two new files and tags/1.2.2
svn commit -m "Release 1.2.2 - heading no longer duplicated, filename title case setting" --username nagarajdev
```

## What was in 1.2.1  (published 19 September 2026)

One fix, from a Pro customer's report, and nothing else.

```
includes/class-dtpost-parser.php   MOD  headings resolved through styles.xml
                                        (name, outlineLvl, basedOn), with the
                                        old ID match kept as the fallback
tests/test-headings.php            NEW  11 generated .docx fixtures
includes/class-dtpost-markdown.php MOD  optional image-resolver parameter and
                                        an images[] return, to stay identical to
                                        Pro's class; unused here, no behaviour
                                        change
admin/bulk-page.php                MOD  Pro description mentions .md in bulk
readme.txt / README.md             MOD  changelog, stable tag, Pro bullet
```

Word documents from non-English Word (`berschrift1`, `Titre1`), documents
with pasted styles (`Heading11`) and documents using custom styles based on
headings all imported every heading as a plain paragraph. The parser only
matched the style *ID*. It now reads `word/styles.xml`. Same fix as DocxToWP
Pro 1.2.6 (`docxtowp/includes/class-dwp-parser.php`), where it has the extra
`probe_title()` path; keep the two in step.


## What is in 1.2.0

Markdown import. One new capability, no changes to Word import.

```
includes/lib/Parsedown.php            NEW  Parsedown 1.7.4, class renamed
                                           DTPost_Parsedown, two params made
                                           explicitly nullable for PHP 8.4
includes/lib/LICENSE-Parsedown.txt    NEW  MIT — must ship with the file above
includes/class-dtpost-markdown.php    NEW  .md → title + HTML, same shape as
                                           DTPost_Parser::parse()
includes/class-dtpost-blocks.php      MOD  <pre> → core/code block
docxtopost.php                        MOD  upload handler branches on .md /
                                           .markdown; session carries
                                           'source' and 'warnings'
admin/upload-page.php                 MOD  accept=".docx,.md,.markdown", copy
admin/preview-page.php                MOD  warnings notice above the editor
assets/admin-v2.js                    MOD  client-side extension check
assets/admin.css                      MOD  .dtpost-parse-warning
tests/test-markdown.php               NEW  54 checks, standalone
tests/fixtures/sample.md              NEW  round-trip fixture
```

Design decisions worth knowing before a support thread asks:

- **Markdown files are never written to disk.** The upload buffer is parsed
  straight into the session transient. There is no `.md` in `dtpost-temp/`
  and nothing for the cron to clean.
- **Relative images are dropped, not left broken.** A single uploaded file
  has nothing to resolve `images/hero.jpg` against. The preview screen lists
  what was left out. Absolute `https://` images are kept as external images
  and are *not* sideloaded — that would be the plugin's first outbound
  request, and it is a Pro-shaped feature anyway.
- **Front matter: title only.** `slug:`, `categories:`, `date:` are removed
  with the block and not read. Half a mapping is worse than none.
- **No `<h2>` title fallback**, unlike .docx. Markdown files routinely open
  with a section heading; the filename is the better guess.
- **Everything goes through `wp_kses_post()`** before the preview. Markdown
  permits raw HTML, and without this a `.md` file is a `<script>` delivery
  route into wp-admin.
- **Pro does not have this yet.** Only one of the two plugins can be active,
  so a free user who upgrades loses Markdown until Pro catches up. Decided
  and accepted for this release; do not headline Markdown on docxtowp.com
  until Pro ships it.

Before tagging, run the tests and lint:

```powershell
php tests/test-markdown.php          # expect "54 passed, 0 failed."
php tests/test-deadline-expiry.php
php -l docxtopost.php; php -l includes/class-dtpost-markdown.php; php -l includes/lib/Parsedown.php
```

Test on a real install, in this order:

- Upload `tests/fixtures/sample.md`. Title reads "DocxToPost Markdown
  Fixture", the preview shows one warning naming `images/architecture.png`,
  and the front matter block is nowhere in the content.
- Publish it to a block-editor post type and open it in the editor. Every
  heading, paragraph, list, quote, table and the code block should be its own
  block, with **no** "This block contains unexpected or invalid content".
  The code block is the one to look at hardest — it is new.
- The table keeps its column alignment (centre / right) in the editor.
- Switch Settings → Content Format to **Always classic HTML** and import
  again; `<pre><code>` arrives as plain HTML.
- Upload a `.md` containing `<script>alert(1)</script>` and an
  `onclick=` attribute. Neither reaches the preview.
- Upload a `.docx`. Nothing about that path has changed; confirm it anyway.
- Rename a `.jpg` to `.md` and upload it: "This does not look like a text
  file." Rename a `.docx` to `.md`: same message (a zip has NUL bytes).
- Drop a `.txt` on the dropzone: rejected client-side with the new message.
- Tools → Site Health → Status still shows the DocxToPost check.

`screenshot-1.png` shows the old "Word Document (.docx)" label. It is not
wrong, just behind — re-shoot it whenever you next have a local install open,
alongside the `screenshot-4.png` already noted below.

## What is in 1.1.1

Two wording changes and nothing else. Verified by diffing the local tree
against the published 1.1.0 zip from wordpress.org:

```
admin/bulk-page.php      "One-time purchase, no subscription"
                      -> "Scheduling is a Pro feature"

admin/upgrade-page.php   "Everything here is a one-time purchase"
                         "No subscription. Pricing, a full feature ..."
                      -> "Everything here comes with Pro"
                         "Pricing, a full feature ..."
```

Both described how Pro is sold. Pro moves from a one-time purchase to annual
licences on 1 October 2026, and directory updates take days to reach installs,
so this needs to go out well before then rather than on the day.


The SVN repo is `https://plugins.svn.wordpress.org/docxtowp/`. It has three
top-level directories and they are not interchangeable:

```
docxtowp/
  assets/   <- banner, icon, screenshots. NOT shipped to users.
  tags/     <- one frozen directory per release
  trunk/    <- the plugin code
```

There is a script for the fiddly part: `../build-svn-release.ps1` copies the
right files to the right places and refuses to run if the version numbers
disagree. The manual equivalent is spelled out below it either way.

---

## Step 0 — Install Subversion

It is not on this machine. Either works:

- **TortoiseSVN** — <https://tortoisesvn.net/downloads.html>. During install,
  open the feature tree and switch **command line client tools** to "Will be
  installed on local hard drive". It is off by default, and without it there
  is no `svn` command.
- **Slik SVN** — <https://sliksvn.com/download/>. Command line only, nothing
  else to configure.

Open a **new** terminal afterwards so `PATH` is picked up, then check:

```powershell
svn --version --quiet
```

## Step 1 — Check out the repository

Once, into a folder that is *not* inside this project:

```powershell
cd D:\DEV
svn checkout https://plugins.svn.wordpress.org/docxtowp/ svn-docxtowp
```

This pulls every past release, so it is not instant. You keep this checkout
and reuse it for every future release — do not re-clone each time.

## Step 2 — Stage the files

```powershell
cd D:\DEV\htdocs\docxtowp
.\build-svn-release.ps1 -SvnPath D:\DEV\svn-docxtowp -Tag 1.2.0
```

The script:

- refuses to run if the plugin header version and `Stable tag` disagree, or
  if the tag already exists
- clears `trunk/` and copies the plugin in, **excluding** `screenshot-*.png`,
  `banner-*`, `icon-*`, `*.zip`, `RELEASE.md` and `.gitignore`
- copies the screenshots and both banners to `assets/`, and warns if the
  1544 banner is present without the 772
- runs `svn add` for new files and `svn delete` for removed ones
- copies `trunk/` to `tags/1.2.0`
- **commits nothing** — it prints `svn status` and stops

Do it by hand instead if you prefer; see "Manual staging" at the bottom.

## Step 3 — Look at what you are about to publish

```powershell
cd D:\DEV\svn-docxtowp
svn status
```

Read the letters in the first column:

| | |
|---|---|
| `A` | added — expect `includes/class-dtpost-markdown.php`, `includes/lib/Parsedown.php`, `includes/lib/LICENSE-Parsedown.txt`, `tests/test-markdown.php`, `tests/fixtures/sample.md`, and `tags/1.2.0` |
| `M` | modified — the files you changed |
| `D` | deleted — should be nothing this release |
| `?` | untracked — **stop.** Something was missed by `svn add` and will not be committed |

No line should start with `?`. If one does, `svn add` it before continuing.

Then confirm the screenshots went to the right place:

```powershell
svn status | Select-String screenshot
```

Every one must read `assets\screenshot-N.png`. If any says `trunk\`, remove
it: `svn revert trunk\screenshot-1.png` and delete the file.

## Step 4 — Commit

One commit for everything, including the tag:

```powershell
svn commit -m "Release 1.2.0 - Markdown import, code blocks" --username nagarajdev
```

You will be asked for your **WordPress.org account password** — the same one
you log in to wordpress.org with, not an application password. SVN caches it
after the first time.

The tag directory and the `Stable tag: 1.2.0` line must land in the **same**
commit. Commit the readme first and the tag second and the plugin points at a
tag that does not exist yet, which 404s the download for everyone in between.

## Step 5 — Confirm it went live

WordPress.org rebuilds within a few minutes.

- <https://wordpress.org/plugins/docxtowp/> shows **1.2.0** and four
  screenshots
- <https://plugins.svn.wordpress.org/docxtowp/tags/> lists `1.2.0`
- An existing install offers the update on its Plugins screen

If the page still says 1.1.1 after fifteen minutes, `Stable tag` and the tag
directory have got out of step. Check both.

---

## The screenshots, in detail

`screenshot-1.png` … `screenshot-4.png` go in **`assets/`** and must **not**
go in `trunk/` — anything in trunk is downloaded by every user, and 860 KB of
screenshots on a 200 KB plugin is dead weight on every install.

The numbers map to the captions in `readme.txt` in order. Renumbering the
files renumbers the captions.

## Why the banner never appeared

`banner-1544x500.png` has been in `assets/` since r3568179 on 10 June 2026 —
the same commit as the two icons, which display fine. The file itself is
correct: valid PNG, exactly 1544×500, 8-bit RGBA, not interlaced. And the CDN
serves it: `https://ps.w.org/docxtowp/assets/banner-1544x500.png` returns 200.

It still never showed, because the plugin API reports `"banners": []`. The
reason is in the handbook, verbatim:

> You **cannot** use the retina image alone, it only works as an "add-on" to
> the 772×250 image.

`banner-1544x500` is the *retina add-on*. Without `banner-772x250`,
WordPress.org registers no banner at all. It works exactly like the icons,
where `icon-128x128` is the base and `icon-256x256` the 2× variant — both are
present, which is why the icon displays and the banner does not.

**Fixed:** both banners now sit beside the screenshots in this folder, and
`build-svn-release.ps1` stages them to `assets/` and warns if the 1544 is ever
present without the 772.

### About the artwork

Both sizes are generated by `../make-banner.php` from one 2×
render, so the retina file is pixel-exactly twice the base. Re-run it to
change the copy; do not edit the two PNGs separately or they drift.

The palette is sampled from the previous banner rather than invented —
`#001040` deep navy, `#0040F0` accent blue, `#C0D0F0` tint. Amber appears
only on the PRO badge and its bullets, which is deliberate: the free
capabilities sit on the left in blue pills, the paid ones sit in a separate
bordered panel in amber, and nobody skim-reading the header can come away
thinking bulk import is included. That is the same reasoning as the
"(Pro)" suffix on the Bulk Import menu item.

PNG rather than JPEG for both, despite JPEG being allowed: the artwork is
flat colour and type, which is exactly where JPEG puts ringing artefacts
around letter edges. At 52 KB and 107 KB there is nothing to gain by
switching.

Once committed, the banner appears on the plugin page within a few minutes.
Confirm with:

```powershell
curl.exe "https://api.wordpress.org/plugins/info/1.2/?action=plugin_information&request%5Bslug%5D=docxtowp&request%5Bfields%5D%5Bbanners%5D=1"
```

`"banners"` should list a `low` entry, and a `high` one alongside it.

### `screenshot-4.png` — shipping as is, by decision

It shows the Settings screen from an older build, where Post Type was a
disabled field reading "Post". The shipped plugin renders a `<select>` of
every public post type, so the image is out of date relative to the
description bullet that advertises "All public post types".

Reviewed and accepted — it ships as is. Worth knowing what the residual risk
looks like so it is not a surprise: the likeliest form is a support thread
asking whether the plugin really only does Posts. The answer is that it does
not, and re-shooting that one screenshot closes it whenever you next have a
local install open. Nothing about it blocks the release.

### One privacy note on `screenshot-2.png`

The permalink row reads `https://www.gieom.com/dev/...`. If that is a client
site, the screenshot puts an unreleased URL on a public page. Blur it or
re-take on a local install.

## What goes in `trunk/`, and what does not

Everything in this folder **except** what `build-svn-release.ps1` filters out:

- `screenshot-*.png` (they go in `assets/`)
- `*.zip` (a build artefact — shipping a zip of the plugin inside the plugin
  doubles every download)
- `RELEASE.md` (this file)
- `.gitignore`

`phpcs.xml` **is** shipped, because it is already in trunk from 1.0.0 and it
is 453 bytes. Removing it is a deletion for no benefit. If you would rather
it were gone, add it to `$Exclude` in the script and the staging step will
mark it deleted on the next release.

`README.md` is a duplicate of `readme.txt`, kept in sync so the directory
parses the same content whichever it picks up. If you would rather not carry
both, delete `README.md` — `readme.txt` is the documented format and is what
the readme validator checks.

## Manual staging

If you would rather not use the script:

```powershell
cd D:\DEV\svn-docxtowp

# 1. Wipe trunk, keeping SVN's own metadata.
Get-ChildItem trunk -Force | Where-Object Name -ne '.svn' | Remove-Item -Recurse -Force

# 2. Copy the plugin in.
Copy-Item D:\DEV\htdocs\docxtowp\docxtopost\* trunk\ -Recurse -Force

# 3. Take back out what must not ship.
Remove-Item trunk\screenshot-*.png, trunk\RELEASE.md, trunk\*.zip -Force -ErrorAction SilentlyContinue

# 4. Screenshots to assets.
Copy-Item D:\DEV\htdocs\docxtowp\docxtopost\screenshot-*.png assets\ -Force

# 5. Let SVN see it.
svn add --force . --auto-props --parents --depth infinity
svn status | Where-Object { $_ -match '^!' } | ForEach-Object { svn delete ($_ -split '\s+',2)[1] }

# 6. Tag.
svn copy trunk tags/1.2.0
```

Step 3 is the one people forget, and it is the one that costs every user an
extra 860 KB on every install.

`Stable tag: 1.2.0` in `readme.txt` is already set. WordPress serves whatever
`Stable tag` names, so the tag directory must exist in that same commit —
otherwise the plugin 404s for everyone in between.

## Test it before you announce it

- The plugin page shows four screenshots.
- The upload screen's title and tagline sit on one line, and the Pro card in
  the sidebar lists three features.
- The menu shows **Bulk Import (Pro)** and **Upgrade**, and both pages load.
- A Word document with a numbered list imports as `<ol>`, and sub-bullets
  nest.
- The "Upload Another" button on the success screen works — that is the bug
  this release exists to fix.
- **Import a real document and open it in the block editor.** Every paragraph,
  heading, list, image and table should be its own block, selectable on its
  own, with no "This block contains unexpected or invalid content" warning
  anywhere. This is the one change worth testing against several of your own
  documents rather than one sample.
- Switch Settings → Content Format to **Always classic HTML**, import again,
  and confirm you get plain HTML with no block comments.
- Tools → Site Health → Status shows the DocxToPost check, and it passes.
- Import a document twice; the second time, the preview screen warns that a
  post with that title already exists.
- The "Move it to the trash" link on the confirmation screen works, and the
  post is recoverable from the Trash.
- The menu reads **Bulk Import (Pro)**, and that page opens with the blue Pro
  banner before any of the feature copy.

## Not built: the email capture

Phase 3 of the plan included an opt-in email list, on the reasoning that it is
the only channel that does not depend on WordPress.org search. It is not in
this release, and deliberately so.

Doing it from inside the plugin would mean the free plugin making outbound HTTP
requests for the first time — which is allowed, but brings a privacy
disclosure, an explicit-consent requirement, and a class of support problem
(firewalls, timeouts) that the plugin currently does not have at all. The
better shape is a **link** to a signup page on docxtowp.com, which keeps the
plugin at zero outbound requests.

That needs two things that do not exist yet, and one of them is your decision:

1. An email provider. There is nothing configured in `docxtowp-landing` —
   no Mailchimp, ConvertKit, Resend or equivalent — so this is an open choice.
2. A signup page to link to, plus whatever is being offered in exchange.

Adding a link to a page that 404s would be worse than not adding it, so the
plugin ships without one. Once the page exists, the link is a one-line
addition to `admin/upgrade-page.php` using `dtpost_pro_url( 'newsletter' )`.

## The "Bulk Import (Pro)" menu item

This is an advertisement, and it is allowed to be one. Guideline 5 says
outright that "attempting to upsell the user on ad-hoc products and features
*is* acceptable, provided it falls within bounds of guideline 11", and
guideline 11 sets that bound at "contextually or only on the plugin's setting
page". A page inside this plugin's own menu is inside that bound.

What guideline 5 actually prohibits is functionality that is "restricted or
locked, only to be made available by payment or upgrade" — code that is
present and disabled. So `admin/bulk-page.php` contains no interface at all:
no drop zone, no disabled buttons, no form elements. It is prose describing a
feature, with a link. `scratchpad/test-pages.php` asserts that mechanically,
failing the build if `<form>`, `<input>`, `disabled` or a dropzone class ever
appears on either informational page.

The menu label is **"Bulk Import (Pro)"**, not "Bulk Import". The feature name
is what makes it findable — someone with a folder of documents is looking for
those two words, not for "Upgrade" — and the suffix is what stops it being a
promise the free plugin does not keep. If you drop the suffix, expect a
one-star review reading "the bulk import doesn't work".

## Shipping this as one release or two

Everything here is built and tested together as 1.1.0. If you would rather de-
risk the block change, the alternative is to ship the fixes first:

1. Set the version back to `1.0.1` in `docxtopost.php` and `readme.txt`,
   remove `includes/class-dtpost-blocks.php`, its `require_once`, the
   `dtpost_use_block_format()` function, the Content Format setting and the
   two lines in `DTPost_Publisher::publish()` that call the serializer.
2. Ship, wait a week, then put them back for 1.1.0.

That also gives you two "last updated" bumps instead of one, which the
directory's search ranking notices. The trade is that the dead-link fix — the
reason 1.0.1 exists — waits on nothing either way, so shipping both together
is the simpler call unless block output worries you.

## Verifying the parser changes

`scratchpad/test-parser.php` covers list nesting, list type resolution from
`numbering.xml`, table headers, run formatting and malformed-XML recovery. It
stubs WordPress, so it runs standalone:

```sh
cp includes/class-dtpost-parser.php /path/to/scratchpad/parser-under-test.php
php /path/to/scratchpad/test-parser.php
```

Ten checks, all passing as of this release.
