<?php
/**
 * Parses Markdown files into clean HTML for DocxToPost.
 *
 * Produces the same shape as DTPost_Parser::parse(), a title and a body of
 * plain semantic HTML, so everything downstream (the preview editor,
 * DTPost_Publisher, the SEO fields) is shared with the .docx path and needs
 * no idea where the content came from.
 *
 * The same class ships in DocxToWP Pro as DWP_Markdown. Keep the two in
 * step: a fix to one is a fix to both. Here only the single-file screen
 * calls it, and it never passes a resolver, so every relative image is
 * reported; Pro's bulk import passes one that looks through the images
 * uploaded with the job.
 *
 * The parsing itself is Parsedown (includes/lib/Parsedown.php): CommonMark
 * plus the GitHub extensions people actually use, tables, fenced code with
 * a language hint, strikethrough, autolinked URLs.
 *
 * What this class adds on top:
 *
 *   - YAML front matter is removed. A `title:` key becomes the post title
 *     and nothing else in it is read. Mapping slug, categories and dates
 *     from front matter is a separate feature, and half a mapping is worse
 *     than none, a silently ignored `categories:` line is a support ticket.
 *   - The first <h1> becomes the title and is removed from the body, as it
 *     is for .docx. There is no <h2> fallback: Markdown files routinely open
 *     with a section heading rather than the document title, and the
 *     filename is the better guess when there is no <h1>.
 *   - Images with a relative path are removed and reported. A single
 *     uploaded file has nothing to resolve `images/hero.jpg` against, and a
 *     broken <img> in the post is worse than an honest notice on the
 *     preview screen. Absolute http(s) URLs are kept as external images.
 *   - Table cell alignment is rewritten from the inline style Parsedown
 *     emits into the class + data attribute the core table block reads, so
 *     it survives into the editor instead of invalidating the block.
 *   - Everything goes through wp_kses_post(). Markdown permits raw HTML,
 *     so without this a .md file is a route for <script> into the preview
 *     screen and the post.
 *
 * @package DocxToPost
 */

defined( 'ABSPATH' ) || exit;

class DTPost_Markdown {

	/**
	 * Converts a Markdown document to a title and HTML body.
	 *
	 * @param string        $markdown      Raw file contents.
	 * @param string        $original_name Uploaded filename, used as the title fallback.
	 * @param callable|null $resolve_image Given a relative image reference as
	 *     written in the file (`../_resources/abc.png`, `images/hero.jpg`,
	 *     URL-decoded), returns the absolute URL to use instead, or '' to say
	 *     "I do not have it". Bulk import passes a lookup over the companion
	 *     images uploaded with the job; the single-file screen passes nothing
	 *     and every relative image is reported. The parser stays ignorant of
	 *     where images might come from, which is what keeps it testable.
	 * @return array{title:string,content:string,warnings:string[],images:string[]}|WP_Error
	 *     `images` lists the absolute URLs of every image kept in the body,
	 *     resolved ones first-come, so a caller can pick a featured image.
	 */
	public function parse( string $markdown, string $original_name = '', ?callable $resolve_image = null ): array|WP_Error {
		// A NUL byte never appears in text. Anything containing one is a
		// binary file wearing a .md extension, and there is no point running
		// a Markdown parser over it.
		if ( str_contains( $markdown, "\0" ) ) {
			return new WP_Error( 'dtpost_md_binary', __( 'This does not look like a text file.', 'docxtowp' ) );
		}

		// Editors on Windows prepend a byte-order mark; Parsedown would treat
		// it as text and the first heading would stop being a heading.
		if ( str_starts_with( $markdown, "\xEF\xBB\xBF" ) ) {
			$markdown = substr( $markdown, 3 );
		}

		if ( ! preg_match( '//u', $markdown ) ) {
			return new WP_Error( 'dtpost_md_encoding', __( 'The file is not valid UTF-8 text. Re-save it as UTF-8 and try again.', 'docxtowp' ) );
		}

		$markdown = str_replace( [ "\r\n", "\r" ], "\n", $markdown );

		$front_title = '';
		$markdown    = $this->strip_front_matter( $markdown, $front_title );

		if ( '' === trim( $markdown ) ) {
			return new WP_Error( 'dtpost_empty', __( 'Document appears to be empty.', 'docxtowp' ) );
		}

		$parser = new DTPost_Parsedown();
		$parser->setBreaksEnabled( false ); // CommonMark: a single newline is a space.
		$parser->setUrlsLinked( true );
		$html = $parser->text( $markdown );

		if ( '' === trim( wp_strip_all_tags( $html ) ) && ! str_contains( $html, '<img' ) ) {
			return new WP_Error( 'dtpost_empty', __( 'Document appears to be empty.', 'docxtowp' ) );
		}

		$title = $this->extract_title( $html, $front_title, $original_name );
		$html  = $this->remove_title_heading( $html, $title, '' !== $front_title );

		$warnings = [];
		$images   = [];
		$html     = $this->filter_images( $html, $warnings, $images, $resolve_image );
		$html     = $this->rewrite_table_alignment( $html );
		$html     = $this->clean_html( $html );

		// Last, and not optional. Parsedown passes raw HTML through
		// untouched, which is correct for Markdown and wrong for us.
		$content = wp_kses_post( $html );

		return [
			'title'    => $title,
			'content'  => $content,
			'warnings' => $warnings,
			'images'   => $images,
		];
	}

	/**
	 * The title alone. Unused in the free plugin; kept so the class matches Pro.
	 *
	 * Mirrors DTPost_Parser::probe_title(). For Markdown a full parse is a few
	 * milliseconds, so this simply runs one and keeps the title, cheaper to
	 * reason about than a second title-finding routine that could disagree
	 * with the real one at import time.
	 */
	public function probe_title( string $markdown, string $original_name = '' ): string {
		$result = $this->parse( $markdown, $original_name );
		if ( ! is_wp_error( $result ) && '' !== trim( (string) $result['title'] ) ) {
			return (string) $result['title'];
		}

		$fallback = DTPost_Title::from_filename( (string) $original_name );
		return '' !== $fallback ? $fallback : __( 'Untitled Document', 'docxtowp' );
	}

	// =========================================================================
	// Front matter
	// =========================================================================

	/**
	 * Removes a leading YAML front matter block, capturing its title.
	 *
	 * Only a block that starts on the very first line counts. `---` further
	 * down is a horizontal rule and stays.
	 */
	private function strip_front_matter( string $markdown, string &$title ): string {
		if ( ! preg_match( '/\A---[ \t]*\n(.*?)\n(?:---|\.\.\.)[ \t]*(?:\n|\z)/s', $markdown, $m ) ) {
			return $markdown;
		}

		if ( preg_match( '/^title:[ \t]*(.+?)[ \t]*$/mi', $m[1], $t ) ) {
			$title = trim( $t[1] );
			// YAML allows the value to be quoted either way.
			if ( strlen( $title ) >= 2 && in_array( $title[0], [ '"', "'" ], true ) && $title[0] === substr( $title, -1 ) ) {
				$title = substr( $title, 1, -1 );
			}
			$title = trim( wp_strip_all_tags( $title ) );
		}

		return substr( $markdown, strlen( $m[0] ) );
	}

	// =========================================================================
	// Title
	// =========================================================================

	private function extract_title( string $html, string $front_title, string $original_name ): string {
		if ( '' !== $front_title ) {
			return $front_title;
		}

		if ( preg_match( '/<h1[^>]*>(.*?)<\/h1>/is', $html, $m ) ) {
			$t = trim( wp_strip_all_tags( $m[1] ) );
			if ( '' !== $t ) {
				return $t;
			}
		}

		$fallback = DTPost_Title::from_filename( $original_name );
		if ( '' !== $fallback ) {
			return $fallback;
		}

		return __( 'Untitled Document', 'docxtowp' );
	}

	/**
	 * Drops the heading that became the title, so it is not repeated as the
	 * first line of the post.
	 *
	 * With no front matter this is the first <h1>, wherever it is, the same
	 * rule as .docx. With front matter the <h1> is only removed when it says
	 * the same thing as the title; a different <h1> is real content.
	 */
	private function remove_title_heading( string $html, string $title, bool $from_front_matter ): string {
		if ( ! preg_match( '/<h1[^>]*>(.*?)<\/h1>\s*/is', $html, $m, PREG_OFFSET_CAPTURE ) ) {
			return $html;
		}

		if ( $from_front_matter ) {
			$heading = trim( wp_strip_all_tags( $m[1][0] ) );
			if ( 0 !== strcasecmp( $heading, $title ) ) {
				return $html;
			}
		}

		return substr_replace( $html, '', $m[0][1], strlen( $m[0][0] ) );
	}

	// =========================================================================
	// Images
	// =========================================================================

	/**
	 * Keeps images with an absolute http(s) URL, offers every relative path
	 * to the resolver, and removes (with a notice) whatever is left.
	 *
	 * A relative path cannot be resolved from a single uploaded file, which
	 * is why the single-file screen passes no resolver and sees the notice.
	 * Bulk import passes one that looks through the companion images uploaded
	 * with the job, so `../_resources/4b2d.png` (Joplin), `attachments/x.png`
	 * (Obsidian) or `Page%20Name/photo.png` (Notion) lands in the post when
	 * the file was dropped in alongside. Data URIs are refused by
	 * wp_kses_post() anyway, so removing them here just means the user is
	 * told rather than left with an <img> that has no src.
	 *
	 * @param string[]      $warnings Appended to, one entry per removed image.
	 * @param string[]      $images   Appended to: the URL of every image kept.
	 * @param callable|null $resolve  See parse().
	 */
	private function filter_images( string $html, array &$warnings, array &$images, ?callable $resolve ): string {
		$skipped = [];

		$html = preg_replace_callback(
			'/<img\b[^>]*>/i',
			static function ( array $m ) use ( &$skipped, &$images, $resolve ): string {
				$src = '';
				if ( preg_match( '/\bsrc\s*=\s*"([^"]*)"/i', $m[0], $s ) ) {
					$src = trim( $s[1] );
				}

				if ( preg_match( '#^(?:https?:)?//#i', $src ) ) {
					$images[] = $src;
					return $m[0];
				}

				// Anything with a scheme that is not http(s): data: file:,
				// ftp: is not a relative path and is never resolvable.
				if ( '' !== $src && $resolve && ! preg_match( '#^[a-z][a-z0-9+.\-]*:#i', $src ) ) {
					// Parsedown has entity-encoded the attribute; the resolver
					// wants the path as the author wrote it, decoded twice,
					// once for HTML, once for the %20 a Notion export uses.
					$path = rawurldecode( html_entity_decode( $src, ENT_QUOTES, 'UTF-8' ) );
					$url  = (string) $resolve( $path );
					if ( '' !== $url && preg_match( '#^(?:https?:)?//#i', $url ) ) {
						$images[] = $url;
						return preg_replace(
							'/\bsrc\s*=\s*"[^"]*"/i',
							'src="' . htmlspecialchars( $url, ENT_QUOTES, 'UTF-8' ) . '"',
							$m[0],
							1
						);
					}
				}

				$skipped[] = '' === $src ? '(empty)' : $src;
				return '';
			},
			$html
		);

		if ( ! empty( $skipped ) ) {
			// One notice, not one per image, and cut the list at five so a
			// document with eighty screenshots does not produce a wall.
			$shown = array_slice( $skipped, 0, 5 );
			$more  = count( $skipped ) - count( $shown );
			$list  = implode( ', ', array_map( static fn( string $s ): string => wp_strip_all_tags( html_entity_decode( $s, ENT_QUOTES, 'UTF-8' ) ), $shown ) );
			if ( $more > 0 ) {
				/* translators: %d: number of additional skipped images */
				$list .= ' ' . sprintf( _n( 'and %d more', 'and %d more', $more, 'docxtowp' ), $more );
			}

			$warnings[] = sprintf(
				/* translators: 1: number of images, 2: comma-separated list of image paths */
				_n(
					'%1$d image was left out because its path is relative and there is nothing to resolve it against: %2$s. Upload it to the Media Library and add it in the editor.',
					'%1$d images were left out because their paths are relative and there is nothing to resolve them against: %2$s. Upload them to the Media Library and add them in the editor.',
					count( $skipped ),
					'docxtowp'
				),
				count( $skipped ),
				$list
			);
		}

		return (string) $html;
	}

	// =========================================================================
	// Tables
	// =========================================================================

	/**
	 * Turns Parsedown's `style="text-align: right;"` on cells into what the
	 * core table block expects: a has-text-align-* class and data-align.
	 *
	 * The style attribute would otherwise be stripped by clean_html(), and
	 * had it survived, the block would fail validation on first open.
	 */
	private function rewrite_table_alignment( string $html ): string {
		return (string) preg_replace(
			'/<(t[hd])\s+style="text-align:\s*(left|center|right);?"/i',
			'<$1 class="has-text-align-$2" data-align="$2"',
			$html
		);
	}

	// =========================================================================
	// Cleanup
	// =========================================================================

	/**
	 * Mirrors DTPost_Parser::clean_html(), minus the class stripping: the
	 * only classes here are ones this file put there on purpose.
	 */
	private function clean_html( string $html ): string {
		$html = preg_replace( '/\s+(style|lang|dir|xml:lang)="[^"]*"/i', '', $html );
		// Paragraphs emptied by image removal.
		$html = preg_replace( '/<(p|li|h[1-6])>\s*<\/\1>/i', '', $html );
		return trim( (string) $html );
	}
}
