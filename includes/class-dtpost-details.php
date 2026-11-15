<?php
/**
 * What a document says about itself: slug, excerpt, tags and categories.
 *
 * Two places can say it, and both end up in the same small array, so the
 * preview screen does not care which one it was:
 *
 *   - Markdown front matter: slug, excerpt, tags, categories.
 *   - Word's own properties (File → Info): Keywords become tags, and Title is the
 *     title when no heading opens the document.
 *
 * The same class ships in DocxToWP Pro as DWP_Details, which also reads labelled
 * "SEO title:" and "URL slug:" lines from the top of a Word document. Free has no
 * SEO fields to put those in, so that half is not here. Keep the rest in step: a
 * fix to one is a fix to both.
 *
 * @package DocxToPost
 */

defined( 'ABSPATH' ) || exit;

class DTPost_Details {

	/**
	 * Word titles that are only ever a leftover. Word writes "Document1"-style
	 * names, PDF printers write "Microsoft Word - report.doc", and a file name
	 * is no better a title than the real file name would be.
	 */
	private const PLACEHOLDER_TITLE = '/^(?:untitled(?:\s+document)?|document\s*\d*|(?:new\s+)?(?:microsoft\s+)?word\s+document|microsoft\s+word\b.*|title|template|.*\.(?:docx?|dotx?|rtf|pdf|tmp))$/i';

	/**
	 * Nothing known yet.
	 *
	 * @return array{slug:string,excerpt:string,tags:string[],categories:string[]}
	 */
	public static function blank(): array {
		return array(
			'slug'       => '',
			'excerpt'    => '',
			'tags'       => array(),
			'categories' => array(),
		);
	}

	/**
	 * What was found, as names to show a person. Empty when nothing was.
	 *
	 * @param array<string, mixed> $details
	 * @return string[]
	 */
	public static function summary( array $details ): array {
		$labels = array(
			'slug'       => __( 'URL slug', 'docxtowp' ),
			'excerpt'    => __( 'excerpt', 'docxtowp' ),
			'tags'       => __( 'tags', 'docxtowp' ),
			'categories' => __( 'categories', 'docxtowp' ),
		);

		$found = array();
		foreach ( $labels as $key => $label ) {
			if ( ! empty( $details[ $key ] ) ) {
				$found[] = $label;
			}
		}
		return $found;
	}

	/**
	 * Accepts a slug however it was written: "my-post", "/my-post/", or the
	 * whole address. WordPress would turn the slashes of "blog/my-post" into
	 * hyphens, and the last part is the only part a slug can set.
	 */
	private static function clean_slug( string $value ): string {
		$value = (string) preg_replace( '/[?#].*$/', '', $value );
		$parts = array_values( array_filter( explode( '/', $value ), 'strlen' ) );
		return $parts ? sanitize_title( (string) end( $parts ) ) : '';
	}

	// =========================================================================
	// Word properties
	// =========================================================================

	/**
	 * Word's Title and Keywords, from docProps/core.xml.
	 *
	 * @return array{title:string,keywords:string[]} Empty values for a file that
	 *     has none, which is most of them, or that cannot be read.
	 */
	public static function word_properties( string $file_path ): array {
		$props = array( 'title' => '', 'keywords' => array() );

		if ( ! class_exists( 'ZipArchive' ) || ! is_file( $file_path ) ) {
			return $props;
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $file_path ) ) {
			return $props;
		}
		// A real one is under a kilobyte. The cap is for a file that is not real.
		$xml = $zip->getFromName( 'docProps/core.xml', 1048576 );
		$zip->close();

		if ( ! $xml ) {
			return $props;
		}

		$previous = libxml_use_internal_errors( true );
		$dom      = new DOMDocument();
		$loaded   = $dom->loadXML( $xml, LIBXML_NONET );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		if ( ! $loaded ) {
			return $props;
		}

		$read = static function ( string $ns, string $name ) use ( $dom ): string {
			$found = $dom->getElementsByTagNameNS( $ns, $name );
			return $found->length ? trim( (string) $found->item( 0 )->textContent ) : '';
		};

		$title = $read( 'http://purl.org/dc/elements/1.1/', 'title' );
		if ( ! preg_match( self::PLACEHOLDER_TITLE, $title ) ) {
			$props['title'] = $title;
		}

		$keywords = $read( 'http://schemas.openxmlformats.org/package/2006/metadata/core-properties', 'keywords' );
		// Word's box takes either separator, and people use both.
		$props['keywords'] = self::clean_list( (array) preg_split( '/[,;]+/', $keywords ) );

		return $props;
	}

	// =========================================================================
	// Front matter
	// =========================================================================

	/**
	 * Reads slug, excerpt, tags and categories from a front matter block.
	 *
	 * Not a YAML parser, and it does not try to be: it reads the handful of
	 * shapes these four keys take in Obsidian, Joplin, Jekyll, Hugo and hand-made
	 * files, and skips every other key without looking inside it.
	 *
	 * @param string $yaml The block, without its --- lines.
	 * @return array{slug:string,excerpt:string,tags:string[],categories:string[]}
	 */
	public static function from_front_matter( string $yaml ): array {
		$keys = array(
			'slug'       => 'slug',
			'excerpt'    => 'excerpt',
			'tags'       => 'tags',
			'tag'        => 'tags',
			'categories' => 'categories',
			'category'   => 'categories',
		);
		$out  = self::blank();

		$lines = explode( "\n", $yaml );
		$count = count( $lines );

		for ( $i = 0; $i < $count; $i++ ) {
			if ( ! preg_match( '/^([A-Za-z_][\w-]*)[ \t]*:(?:[ \t]+(.*))?$/', rtrim( $lines[ $i ] ), $m ) ) {
				continue;
			}

			$field = $keys[ strtolower( $m[1] ) ] ?? '';
			if ( '' === $field || ! empty( $out[ $field ] ) ) {
				continue;
			}

			$value = trim( $m[2] ?? '' );

			// What belongs to this key: indented lines, and "- x" list items,
			// which YAML allows at the same indent as the key itself.
			$block = array();
			while ( $i + 1 < $count && preg_match( '/^(?:[ \t]+\S|-[ \t]+\S|[ \t]*$)/', $lines[ $i + 1 ] ) ) {
				$block[] = trim( $lines[ ++$i ] );
			}
			$block = array_values( array_filter( $block, 'strlen' ) );

			if ( 'tags' === $field || 'categories' === $field ) {
				$out[ $field ] = self::front_matter_list( $value, $block );
			} else {
				$text = self::front_matter_text( $value, $block );
				$out[ $field ] = 'slug' === $field ? self::clean_slug( $text ) : $text;
			}
		}

		// A tag cannot hold a comma: tags travel as a comma-separated string.
		$out['tags'] = self::clean_list( array_map( static fn( string $t ): string => (string) preg_replace( '/\s*,\s*/', ' ', $t ), $out['tags'] ) );

		return $out;
	}

	/**
	 * A one-line value, or a folded block.
	 *
	 * @param string[] $block
	 */
	private static function front_matter_text( string $value, array $block ): string {
		if ( preg_match( '/^([|>])[-+]?$/', $value, $m ) ) {
			return trim( wp_strip_all_tags( implode( '|' === $m[1] ? "\n" : ' ', $block ) ) );
		}

		$text = self::unquote( trim( $value . ' ' . implode( ' ', $block ) ) );
		return in_array( $text, array( 'null', '~' ), true ) ? '' : trim( wp_strip_all_tags( $text ) );
	}

	/**
	 * The list shapes: [a, "b"], a block of "- a", or a plain "a, b".
	 *
	 * @param string[] $block
	 * @return string[]
	 */
	private static function front_matter_list( string $value, array $block ): array {
		if ( preg_match( '/^\[(.*)\]$/s', $value, $m ) ) {
			preg_match_all( '/\s*(?:"([^"]*)"|\'([^\']*)\'|([^,]+))/', $m[1], $parts, PREG_SET_ORDER );
			$items = array_map( static fn( array $p ): string => (string) end( $p ), $parts );
		} elseif ( '' === $value ) {
			$items = array();
			foreach ( $block as $line ) {
				if ( preg_match( '/^-[ \t]+(.+)$/', $line, $m ) ) {
					$items[] = self::unquote( $m[1] );
				}
			}
		} elseif ( str_starts_with( $value, '#' ) ) {
			// Obsidian's habit: tags: #one #two.
			$items = (array) preg_split( '/\s+/', $value );
		} else {
			$items = (array) preg_split( '/\s*,\s*/', self::unquote( $value ) );
		}

		return self::clean_list( $items );
	}

	private static function unquote( string $value ): string {
		$value = trim( $value );
		if ( strlen( $value ) >= 2 && in_array( $value[0], array( '"', "'" ), true ) && $value[0] === substr( $value, -1 ) ) {
			return substr( $value, 1, -1 );
		}
		return $value;
	}

	/**
	 * Trimmed, without a leading #, no blanks, no repeats in either case.
	 *
	 * @param array<int, mixed> $items
	 * @return string[]
	 */
	private static function clean_list( array $items ): array {
		$out = array();
		foreach ( $items as $item ) {
			$name = trim( wp_strip_all_tags( ltrim( trim( (string) $item ), '#' ) ) );
			if ( '' !== $name && ! in_array( $name, array( 'null', '~' ), true ) ) {
				$out[ mb_strtolower( $name ) ] = $name;
			}
		}
		return array_values( $out );
	}

	// =========================================================================
	// Categories
	// =========================================================================

	/**
	 * Sorts the names a document gave into ones the site has and ones it doesn't.
	 *
	 * Upper and lower case do not matter, and a category's slug counts as its
	 * name, since that is what some front matter holds.
	 *
	 * @param string[] $names
	 * @return array{ids:int[],missing:string[]}
	 */
	public static function match_categories( array $names, string $taxonomy = 'category' ): array {
		$result = array( 'ids' => array(), 'missing' => array() );
		if ( empty( $names ) ) {
			return $result;
		}

		$known = array();
		$terms = get_terms( array( 'taxonomy' => $taxonomy, 'hide_empty' => false ) );
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			$known[ (string) $term->slug ] = (int) $term->term_id;
			$known[ mb_strtolower( html_entity_decode( (string) $term->name, ENT_QUOTES, 'UTF-8' ) ) ] = (int) $term->term_id;
		}

		foreach ( $names as $name ) {
			$key = mb_strtolower( (string) $name );
			if ( isset( $known[ $key ] ) ) {
				$result['ids'][] = $known[ $key ];
			} else {
				$result['missing'][] = (string) $name;
			}
		}
		$result['ids'] = array_values( array_unique( $result['ids'] ) );

		return $result;
	}

	/**
	 * Creates the categories a person ticked on the preview screen.
	 *
	 * The capability is checked here as well as on the screen, because the names
	 * arrive in a request. WordPress Authors cannot create categories, and this
	 * must not be a way round that.
	 *
	 * @param string[] $names
	 * @return int[] Term ids, including any that turned out to exist already.
	 */
	public static function create_categories( array $names, string $taxonomy = 'category' ): array {
		$tax = get_taxonomy( $taxonomy );
		if ( ! $tax || ! current_user_can( $tax->cap->manage_terms ) ) {
			return array();
		}

		$ids = array();
		foreach ( $names as $name ) {
			$name = trim( sanitize_text_field( (string) $name ) );
			if ( '' === $name ) {
				continue;
			}
			$term = term_exists( $name, $taxonomy );
			if ( ! $term ) {
				$term = wp_insert_term( $name, $taxonomy );
			}
			if ( ! is_wp_error( $term ) && $term ) {
				$ids[] = (int) ( is_array( $term ) ? $term['term_id'] : $term );
			}
		}
		return $ids;
	}
}
