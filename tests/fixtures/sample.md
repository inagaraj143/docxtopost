---
title: DocxToPost Markdown Fixture
slug: this-is-ignored
tags:
  - one
  - two
---

# DocxToPost Markdown Fixture

This document exercises everything the free importer is expected to handle. It was written the way people actually write Markdown — a bit of everything, not a spec test.

## Text formatting

Plain paragraph with **bold**, *italic*, ~~strikethrough~~, `inline code` and a [link to the site](https://docxtowp.com). A bare URL like https://wordpress.org should autolink.

Line one of a paragraph
continues here because a single newline is just a space.

## Lists

- Apples
- Pears
  - Conference
  - Comice
- Plums

1. Draft
2. Review
3. Publish

## A quote

> The plugin should not become a generic file-conversion tool.
> It should be the easiest way to move documents into WordPress.

## Code

```php
<?php
add_filter( 'the_content', function ( $c ) {
    return $c . '<!-- [gallery] is not a shortcode here -->';
} );
```

    indented code block
    second line

## A table

| Feature        | Free | Pro |
|:---------------|:----:|----:|
| Single upload  | yes  | yes |
| Bulk import    | no   | yes |
| Drip publish   | no   | yes |

## Images

An external image that should be kept:

![WordPress logo](https://s.w.org/style/images/about/WordPress-logotype-standard.png)

A relative image that cannot be resolved from a single file:

![Diagram](images/architecture.png)

---

Final paragraph after a horizontal rule.
