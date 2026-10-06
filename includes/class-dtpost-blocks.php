<?php
/**
 * Converts parsed HTML into Gutenberg block markup.
 *
 * The parser produces plain semantic HTML, which is what the preview editor
 * edits and what the classic editor wants. Pasted into a block-editor post it
 * becomes one enormous Classic block that nobody can work with, which is a
 * poor result for a plugin whose pitch is "no block editor fighting".
 *
 * This runs at publish time, not parse time, so the preview screen keeps
 * showing editable HTML rather than a wall of block delimiters.
 *
 * @package DocxToPost
 */

defined( 'ABSPATH' ) || exit;

class DTPost_Blocks {

	/**
	 * Wraps parsed HTML in block delimiters.
	 *
	 * @param string $html Semantic HTML from DTPost_Parser or DTPost_Markdown.
	 * @return string Block markup, or the original HTML if it cannot be parsed.
	 */
	public static function serialize( string $html ): string {
		if ( '' === trim( $html ) ) {
			return '';
		}

		$dom = self::load( $html );
		if ( ! $dom ) {
			return $html;
		}

		$roots = $dom->getElementsByTagName( 'body' );
		if ( 0 === $roots->length ) {
			return $html;
		}

		$out = '';
		foreach ( iterator_to_array( $roots->item( 0 )->childNodes ) as $node ) {
			$out .= self::node_to_block( $node );
		}

		$out = trim( $out );

		// If nothing recognisable came out, keep the original rather than
		// handing back an empty post.
		return '' === $out ? $html : $out;
	}

	/**
	 * Parses an HTML fragment into a DOMDocument, UTF-8 intact.
	 */
	private static function load( string $html ): ?DOMDocument {
		libxml_use_internal_errors( true );

		$dom = new DOMDocument();
		$ok  = $dom->loadHTML(
			'<meta http-equiv="Content-Type" content="text/html; charset=utf-8"><body>' . $html . '</body>',
			LIBXML_HTML_NODEFDTD | LIBXML_NONET
		);

		libxml_clear_errors();

		return $ok ? $dom : null;
	}

	/**
	 * Dispatches one top-level node to the right block.
	 */
	private static function node_to_block( DOMNode $node ): string {
		if ( XML_TEXT_NODE === $node->nodeType ) {
			$text = trim( $node->textContent );
			return '' === $text ? '' : self::block( 'paragraph', [], '<p>' . esc_html( $text ) . '</p>' );
		}

		/*
		 * A comment node is passed through untouched, because the only
		 * comments reaching here are block delimiters.
		 *
		 * DTPost_Embeds turns a video URL into a `<!-- wp:embed -->` block
		 * before this runs, so that markup is already serialized and must not
		 * be serialized again. Without this branch it fell into the catch-all
		 * below and the entire embed was silently deleted, the video
		 * disappeared from the post with nothing to show why.
		 */
		if ( XML_COMMENT_NODE === $node->nodeType ) {
			$text = trim( (string) $node->textContent );
			return str_starts_with( $text, 'wp:' ) || str_starts_with( $text, '/wp:' )
				? '<!-- ' . $text . ' -->' . "\n"
				: '';
		}

		if ( XML_ELEMENT_NODE !== $node->nodeType ) {
			return '';
		}

		/** @var DOMElement $node */
		$tag = strtolower( $node->nodeName );

		/*
		 * An embed's <figure> is passed through as-is.
		 *
		 * DTPost_Embeds has already wrapped it in `<!-- wp:embed -->`
		 * delimiters, so sending it down the switch below would wrap it in a
		 * `wp:html` block *inside* the embed block, two block types nested in
		 * each other, which the editor reads as a broken embed. This is the
		 * second half of the comment-node branch above: that keeps the
		 * delimiters, this keeps what sits between them.
		 */
		if ( 'figure' === $tag && str_contains( (string) $node->getAttribute( 'class' ), 'wp-block-embed' ) ) {
			return trim( self::outer( $node ) ) . "\n";
		}

		switch ( true ) {
			case preg_match( '/^h[1-6]$/', $tag ) === 1:
				return self::heading( $node, (int) substr( $tag, 1 ) );

			case 'p' === $tag:
				return self::paragraph( $node );

			case 'ul' === $tag || 'ol' === $tag:
				return self::list_block( $node, 'ol' === $tag );

			case 'table' === $tag:
				return self::table( $node );

			case 'blockquote' === $tag:
				return self::block( 'quote', [], '<blockquote class="wp-block-quote">' . self::inner( $node ) . '</blockquote>' );

			case 'img' === $tag:
				return self::image( $node );

			case 'hr' === $tag:
				return self::block( 'separator', [], '<hr class="wp-block-separator has-alpha-channel-opacity"/>' );

			case 'pre' === $tag:
				return self::code( $node );

			case 'figure' === $tag:
				$img = $node->getElementsByTagName( 'img' );
				return $img->length > 0 ? self::image( $img->item( 0 ) ) : self::html_block( $node );

			default:
				return self::html_block( $node );
		}
	}

	// ── Individual blocks ────────────────────────────────────────────────────

	private static function heading( DOMElement $node, int $level ): string {
		$inner = trim( self::inner( $node ) );
		if ( '' === $inner ) {
			return '';
		}

		// The heading block's default level is 2, and core omits the attribute
		// in that case.
		$attrs = ( 2 === $level ) ? [] : [ 'level' => $level ];

		$align = self::alignment( $node );
		$class = 'wp-block-heading';
		if ( '' !== $align ) {
			$attrs['textAlign'] = $align;
			$class             .= ' has-text-align-' . $align;
		}

		return self::block(
			'heading',
			$attrs,
			sprintf( '<h%1$d class="%2$s">%3$s</h%1$d>', $level, esc_attr( $class ), $inner )
		);
	}

	/**
	 * Reads the alignment class the parser attached, if any.
	 *
	 * @return string 'center', 'right', 'justify', or '' for the default.
	 */
	private static function alignment( DOMElement $node ): string {
		$class = $node->getAttribute( 'class' );
		if ( '' === $class ) {
			return '';
		}

		return preg_match( '/has-text-align-(center|right|justify)/', $class, $m ) ? $m[1] : '';
	}

	/**
	 * A paragraph, plus any images it contains lifted out into their own
	 * blocks.
	 *
	 * An <img> left inside a paragraph block fails block validation, and Word
	 * puts most images in a paragraph of their own anyway, so the common case
	 * here is a paragraph with no text at all, which becomes purely an image.
	 */
	private static function paragraph( DOMElement $node ): string {
		$images = [];
		foreach ( iterator_to_array( $node->getElementsByTagName( 'img' ) ) as $img ) {
			$images[] = $img;
			$img->parentNode->removeChild( $img );
		}

		$out   = '';
		$inner = trim( self::inner( $node ) );

		if ( '' !== trim( wp_strip_all_tags( $inner ) ) ) {
			$attrs = [];
			$open  = '<p>';

			$align = self::alignment( $node );
			if ( '' !== $align ) {
				$attrs['align'] = $align;
				$open           = '<p class="has-text-align-' . esc_attr( $align ) . '">';
			}

			$out .= self::block( 'paragraph', $attrs, $open . $inner . '</p>' );
		}

		foreach ( $images as $img ) {
			$out .= self::image( $img );
		}

		return $out;
	}

	/**
	 * A whole list, nesting included, as one list block.
	 *
	 * This emits the pre-6.1 shape, plain <li> elements rather than inner
	 * list-item blocks. Core still registers that shape as a deprecation, so
	 * it parses without a validation error on every version this plugin
	 * supports, and upgrades itself the first time the list is edited.
	 */
	private static function list_block( DOMElement $node, bool $ordered ): string {
		if ( 0 === $node->getElementsByTagName( 'li' )->length ) {
			return '';
		}

		$node->setAttribute( 'class', 'wp-block-list' );

		return self::block(
			'list',
			$ordered ? [ 'ordered' => true ] : [],
			self::outer( $node )
		);
	}

	private static function table( DOMElement $node ): string {
		return self::block(
			'table',
			[],
			'<figure class="wp-block-table">' . self::outer( $node ) . '</figure>'
		);
	}

	/**
	 * A code block. Word has no such thing, so this only fires for Markdown.
	 *
	 * Core stores the block's content as escaped text inside
	 * <pre class="wp-block-code"><code>…</code></pre>. The language class
	 * Parsedown puts on <code> is dropped: core's own save() never emits one,
	 * and an attribute it does not expect invalidates the block on first open.
	 *
	 * Escaping is htmlspecialchars() with double-encoding on, not esc_html(),
	 * which leaves existing entities alone, wrong for code, where a literal
	 * `&amp;` in the sample is meant to be seen as `&amp;`.
	 */
	private static function code( DOMElement $node ): string {
		$inner  = $node->getElementsByTagName( 'code' );
		$source = $inner->length > 0 ? $inner->item( 0 ) : $node;
		$text   = rtrim( $source->textContent, "\n" );

		if ( '' === trim( $text ) ) {
			return '';
		}

		$escaped = htmlspecialchars( $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', true );
		// Core does the same, so a code sample containing [gallery] is never
		// run as a shortcode.
		$escaped = str_replace( '[', '&#91;', $escaped );

		return self::block( 'code', [], '<pre class="wp-block-code"><code>' . $escaped . '</code></pre>' );
	}

	private static function image( DOMElement $node ): string {
		$src = $node->getAttribute( 'src' );
		if ( '' === $src ) {
			return '';
		}

		$alt = $node->getAttribute( 'alt' );

		// Resolving from the URL rather than remembering it at parse time,
		// because the preview screen lets people add and remove images before
		// they publish.
		$id = attachment_url_to_postid( $src );

		$attrs = [];
		$class = '';
		if ( $id ) {
			$attrs['id']       = $id;
			$attrs['sizeSlug'] = 'large';
			$class             = ' class="wp-image-' . $id . '"';
		}
		$attrs['linkDestination'] = 'none';

		$figure_class = $id ? 'wp-block-image size-large' : 'wp-block-image';

		return self::block(
			'image',
			$attrs,
			sprintf(
				'<figure class="%s"><img src="%s" alt="%s"%s/></figure>',
				esc_attr( $figure_class ),
				esc_url( $src ),
				esc_attr( $alt ),
				$class
			)
		);
	}

	/**
	 * Anything with no block equivalent is preserved verbatim in an HTML
	 * block, which is lossless and still editable.
	 */
	private static function html_block( DOMElement $node ): string {
		$outer = trim( self::outer( $node ) );
		if ( '' === $outer ) {
			return '';
		}
		return self::block( 'html', [], $outer );
	}

	// ── Helpers ──────────────────────────────────────────────────────────────

	/**
	 * Assembles one block: delimiter, markup, closing delimiter.
	 *
	 * @param array<string,mixed> $attrs
	 */
	private static function block( string $name, array $attrs, string $inner ): string {
		$json = '';
		if ( ! empty( $attrs ) ) {
			// serialize_block_attributes() escapes the characters that would
			// otherwise break out of an HTML comment.
			$json = ' ' . serialize_block_attributes( $attrs );
		}

		return sprintf( "<!-- wp:%1\$s%2\$s -->\n%3\$s\n<!-- /wp:%1\$s -->\n\n", $name, $json, $inner );
	}

	private static function inner( DOMNode $node ): string {
		$html = '';
		foreach ( $node->childNodes as $child ) {
			$html .= $node->ownerDocument->saveHTML( $child );
		}
		return $html;
	}

	private static function outer( DOMNode $node ): string {
		return (string) $node->ownerDocument->saveHTML( $node );
	}
}
