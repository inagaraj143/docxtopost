# Releasing to WordPress.org

Current release: **1.1.1**. Update the version references below when cutting a
new one, or run `../build-svn-release.ps1`, which reads the version out of the
plugin header and refuses to run when readme.txt disagrees.

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
.\build-svn-release.ps1 -SvnPath D:\DEV\svn-docxtowp -Tag 1.1.0
```

The script:

- refuses to run if the plugin header version and `Stable tag` disagree, or
  if the tag already exists
- clears `trunk/` and copies the plugin in, **excluding** `screenshot-*.png`,
  `banner-*`, `icon-*`, `*.zip`, `RELEASE.md` and `.gitignore`
- copies the screenshots and both banners to `assets/`, and warns if the
  1544 banner is present without the 772
- runs `svn add` for new files and `svn delete` for removed ones
- copies `trunk/` to `tags/1.1.0`
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
| `A` | added — expect `readme.txt`, `includes/class-dtpost-blocks.php`, `admin/upgrade-page.php`, `admin/bulk-page.php`, the four screenshots, and `tags/1.1.0` |
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
svn commit -m "Release 1.1.0 - block editor output, list and table fixes, upsell pages" --username nagarajdev
```

You will be asked for your **WordPress.org account password** — the same one
you log in to wordpress.org with, not an application password. SVN caches it
after the first time.

The tag directory and the `Stable tag: 1.1.0` line must land in the **same**
commit. Commit the readme first and the tag second and the plugin points at a
tag that does not exist yet, which 404s the download for everyone in between.

## Step 5 — Confirm it went live

WordPress.org rebuilds within a few minutes.

- <https://wordpress.org/plugins/docxtowp/> shows **1.1.0** and four
  screenshots
- <https://plugins.svn.wordpress.org/docxtowp/tags/> lists `1.1.0`
- An existing install offers the update on its Plugins screen

If the page still says 1.0.0 after fifteen minutes, `Stable tag` and the tag
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
svn copy trunk tags/1.1.0
```

Step 3 is the one people forget, and it is the one that costs every user an
extra 860 KB on every install.

`Stable tag: 1.1.0` in `readme.txt` is already set. WordPress serves whatever
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
