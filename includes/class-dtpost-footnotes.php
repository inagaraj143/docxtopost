<?php
/**
 * Footnotes and endnotes: numbered references, a notes list, and core's block.
 *
 * Two halves, at two moments:
 *
 *   - When a Word file is read, render() turns the parser's placeholders into
 *     numbered references and puts a numbered list of the notes at the end,
 *     linked both ways. Plain HTML, so it reads the same on the preview screen,
 *     in the classic editor, on an older WordPress and in a post type without
 *     the Footnotes block, and it survives being edited on the preview screen.
 *
 *   - When the post is saved as blocks, to_core() turns that list into what the
 *     block editor itself writes: references as <sup data-fn>, the notes in the
 *     `footnotes` post meta as JSON, and a Footnotes block where the list was.
 *     Only where core has registered that meta for the post type, which it does
 *     for types with the editor, custom fields and revisions (WordPress 6.3+).
 *
 * Footnotes and endnotes become one list, in the order their references appear,
 * as the Footnotes block has only one list.
 *
 * The same class ships in DocxToWP Pro as DWP_Footnotes. Keep the two in step.
 *
 * @package DocxToPost
 */

defined( 'ABSPATH' ) || exit;

class DTPost_Footnotes {

	/** The attribute on the parser's placeholder: <sup data-docx-note="footnote-3"></sup>. */
	public const ATTR = 'data-docx-note';

	/** The class the notes list carries, and what to_core() looks for. */
	public const LIST_CLASS = 'footnotes';

	/** The placeholder the parser leaves where a reference was. */
	public static function placeholder( string $key ): string {
		return '<sup ' . self::ATTR . '="' . esc_attr( $key ) . '"></sup>';
	}

	/**
	 * Numbers the references in the order they appear, and adds the notes list.
	 *
	 * Numbered here, after the parser has finished, rather than as the document
	 * is read: by now the title heading is gone, along with any reference in it,
	 * so the numbers have no gaps.
	 *
	 * @param array<string, string> $notes Placeholder key => the note's HTML.
	 */
	public static function render( string $html, array $notes ): string {
		$number = array();
		$list   = '';

		$html = (string) preg_replace_callback(
			'~<sup\b[^>]*\b' . self::ATTR . '="([^"]*)"[^>]*>\s*</sup>~i',
			static function ( array $m ) use ( $notes, &$number, &$list ): string {
				$key = html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' );
				if ( ! isset( $notes[ $key ] ) || '' === trim( $notes[ $key ] ) ) {
					return '';
				}

				// The same note referred to twice links to one entry. Only the
				// first reference carries the id the entry's back link goes to.
				if ( isset( $number[ $key ] ) ) {
					return sprintf( '<sup class="fn"><a href="#fn-%1$d">%1$d</a></sup>', $number[ $key ] );
				}

				$n              = count( $number ) + 1;
				$number[ $key ] = $n;
				$list          .= sprintf(
					'<li id="fn-%1$d">%2$s <a href="#fnref-%1$d" aria-label="%3$s">&#8617;&#65038;</a></li>',
					$n,
					$notes[ $key ],
					/* translators: %d: footnote number */
					esc_attr( sprintf( __( 'Back to reference %d', 'docxtowp' ), $n ) )
				);
				return sprintf( '<sup class="fn"><a href="#fn-%1$d" id="fnref-%1$d">%1$d</a></sup>', $n );
			},
			$html
		);

		return '' === $list ? $html : rtrim( $html ) . "\n" . '<ol class="' . self::LIST_CLASS . '">' . $list . '</ol>';
	}

	/** Whether this post type has core's Footnotes block to put notes in. */
	public static function core_available( string $post_type ): bool {
		return function_exists( 'registered_meta_key_exists' ) && registered_meta_key_exists( 'post', 'footnotes', $post_type );
	}

	/**
	 * Rewrites a notes list made by render() into core's footnotes.
	 *
	 * Reads the HTML as it came back from the preview screen, so whatever the
	 * user changed there is what is kept. If the list cannot be found, because
	 * it was deleted or rebuilt by hand, nothing is touched and it stays a list.
	 *
	 * @return array{content:string,notes:array<int,array{content:string,id:string}>}|null
	 *     `content` still needs block delimiters, and the Footnotes block added
	 *     after them: see block_markup(). `notes` is the post meta, in order.
	 */
	public static function to_core( string $html ): ?array {
		if ( ! preg_match( '~\s*<ol\b[^>]*\bclass="[^"]*\b' . self::LIST_CLASS . '\b[^"]*"[^>]*>(.*?)</ol>~is', $html, $list, PREG_OFFSET_CAPTURE ) ) {
			return null;
		}

		preg_match_all( '~<li\b[^>]*\bid="fn-(\d+)"[^>]*>(.*?)</li>~is', $list[1][0], $items, PREG_SET_ORDER );

		$text = array();
		foreach ( $items as $item ) {
			// Our own back link goes; core draws its own.
			$content = (string) preg_replace( '~\s*<a\b[^>]*href="#fnref-\d+"[^>]*>.*?</a>\s*$~is', '', $item[2] );
			if ( '' !== trim( wp_strip_all_tags( $content ) ) ) {
				$text[ (int) $item[1] ] = trim( $content );
			}
		}

		if ( empty( $text ) ) {
			return null;
		}

		$body  = substr_replace( $html, '', $list[0][1], strlen( $list[0][0] ) );
		$ids   = array();
		$notes = array();

		$body = (string) preg_replace_callback(
			'~<sup\b[^>]*>\s*<a\b[^>]*href="#fn-(\d+)"[^>]*>.*?</a>\s*</sup>~is',
			static function ( array $m ) use ( $text, &$ids, &$notes ): string {
				$n = (int) $m[1];
				if ( ! isset( $text[ $n ] ) ) {
					return $m[0];
				}
				if ( ! isset( $ids[ $n ] ) ) {
					$ids[ $n ] = wp_generate_uuid4();
					$notes[]   = array( 'content' => $text[ $n ], 'id' => $ids[ $n ] );
				}
				$position = (int) array_search( $ids[ $n ], array_column( $notes, 'id' ), true ) + 1;

				// Exactly what the editor writes, which is also what core's own
				// excerpt filter expects to find and remove.
				return sprintf( '<sup data-fn="%1$s" class="fn"><a href="#%1$s" id="%1$s-link">%2$d</a></sup>', $ids[ $n ], $position );
			},
			$body
		);

		// A note whose reference the user deleted is kept, at the end, rather
		// than thrown away with no word.
		foreach ( $text as $n => $content ) {
			if ( ! isset( $ids[ $n ] ) ) {
				$notes[] = array( 'content' => $content, 'id' => wp_generate_uuid4() );
			}
		}

		return array( 'content' => $body, 'notes' => $notes );
	}

	/** The Footnotes block, which draws the notes from the post meta. */
	public static function block_markup(): string {
		return "\n\n<!-- wp:footnotes /-->";
	}

	/**
	 * The notes as post meta, ready for meta_input.
	 *
	 * Slashed, because update_post_meta() unslashes what it is given, and the
	 * backslashes in JSON would otherwise be taken out of it.
	 *
	 * @param array<int, array{content:string,id:string}> $notes
	 */
	public static function meta_value( array $notes ): string {
		return wp_slash( (string) wp_json_encode( $notes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) );
	}
}
