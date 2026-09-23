<?php
/**
 * Parses .docx files into clean HTML for DocxToPost.
 *
 * Handles: headings, paragraphs, bold/italic/underline, lists, tables,
 * hyperlinks, and — critically — inline images (<w:drawing>/<w:pict>).
 *
 * Image pipeline (ZipArchive path):
 *   1. parse_run()  detects <w:drawing> and emits  <img data-docx-img="image1.jpg" alt="">
 *   2. reattach_images() opens the zip, uploads each word/media/* file to WP Media,
 *      then replaces data-docx-img="..." src attributes with real WP URLs.
 *
 * Mammoth path: Mammoth emits <img src="data:…base64…"> which reattach_images() handles.
 *
 * @package DocxToPost
 */

defined( 'ABSPATH' ) || exit;

class DTPost_Parser {

	const W_NS   = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
	const R_NS   = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
	const DRAW_NS= 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing';
	const PIC_NS = 'http://schemas.openxmlformats.org/drawingml/2006/picture';
	const BLIP_NS= 'http://schemas.openxmlformats.org/drawingml/2006/main';
	const RELSNS = 'http://schemas.openxmlformats.org/package/2006/relationships';

	/** rId → filename map built from word/_rels/document.xml.rels */
	private array $image_rids = [];

	/**
	 * styleId → heading level (1–6), built from word/styles.xml.
	 *
	 * A paragraph's <w:pStyle> carries the style's *ID*, and the ID is not
	 * stable: English Word writes "Heading1", German Word writes
	 * "berschrift1", French "Titre1", Spanish "Ttulo1"; Word also mints
	 * "Heading11" when a heading style is copied between documents; and a
	 * custom style based on a heading has whatever ID its author gave it.
	 * Matching the ID alone — which is all this parser did until 1.2.1 —
	 * turned every one of those into a plain paragraph, in a document whose
	 * author had applied genuine Heading styles throughout.
	 *
	 * styles.xml has the two signals that are stable: the style's canonical
	 * <w:name> ("heading 1", kept in English by every Word locale) and its
	 * <w:outlineLvl> (0–5), plus a <w:basedOn> chain through which a custom
	 * style inherits both. See build_heading_style_map().
	 *
	 * @var array<string, int>
	 */
	private array $heading_styles = [];

	/** Which heading supplied the title: "h1", "h2", or "" for the filename. */
	private string $title_tag = '';

	/**
	 * numId → 'ol'|'ul', built from word/numbering.xml.
	 *
	 * Word gives bulleted and numbered lists the same paragraph style
	 * (ListParagraph); only numbering.xml knows which is which.
	 */
	private array $num_formats = [];

	// =========================================================================
	// Public entry point
	// =========================================================================

	/**
	 * @return array{title:string,content:string}|WP_Error
	 */
	public function parse( string $file_path, string $original_name = '' ): array|WP_Error {
		if ( ! file_exists( $file_path ) ) {
			return new WP_Error( 'dtpost_missing_file', __( 'Document file not found.', 'docxtowp' ) );
		}

		if ( class_exists( '\Mammoth\Mammoth' ) ) {
			return $this->parse_with_mammoth( $file_path, $original_name );
		}

		return $this->parse_with_ziparchive( $file_path, $original_name );
	}

	// =========================================================================
	// Mammoth path
	// =========================================================================

	private function parse_with_mammoth( string $file_path, string $original_name ): array|WP_Error {
		try {
			$mammoth = new \Mammoth\Mammoth();
			$result  = $mammoth->convertToHtml( $file_path );
			$html    = $result->getValue();

			if ( empty( trim( $html ) ) ) {
				return new WP_Error( 'dtpost_empty', __( 'Document appears to be empty.', 'docxtowp' ) );
			}

			$html    = $this->clean_html( $html );
			$title   = $this->extract_title( $html, $file_path, $original_name );
			$content = $this->remove_title_heading( $html );

			// Process and upload any base64-encoded images from Mammoth output.
			$content = $this->reattach_images( $content, $file_path );
			$content = $this->finalise_alignment( $content );

			return compact( 'title', 'content' );
		} catch ( \Throwable $e ) {
			return new WP_Error( 'dtpost_mammoth', $e->getMessage() );
		}
	}

	// =========================================================================
	// ZipArchive path
	// =========================================================================

	private function parse_with_ziparchive( string $file_path, string $original_name ): array|WP_Error {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new WP_Error( 'dtpost_no_zip', __( 'ZipArchive PHP extension is not available.', 'docxtowp' ) );
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $file_path ) ) {
			return new WP_Error( 'dtpost_corrupt', __( 'Could not open document. File may be corrupted.', 'docxtowp' ) );
		}

		$document_xml  = $zip->getFromName( 'word/document.xml' );
		$doc_rels_xml  = $zip->getFromName( 'word/_rels/document.xml.rels' );
		$numbering_xml = $zip->getFromName( 'word/numbering.xml' );
		$styles_xml    = $zip->getFromName( 'word/styles.xml' );
		$zip->close();

		if ( false === $document_xml ) {
			return new WP_Error( 'dtpost_invalid_docx', __( 'Invalid .docx file structure.', 'docxtowp' ) );
		}

		// Build maps from rels file.
		$link_map             = $this->build_link_map( $doc_rels_xml ?: '' );
		$this->image_rids     = $this->build_image_rid_map( $doc_rels_xml ?: '' );
		$this->heading_styles = $this->build_heading_style_map( $styles_xml ?: '' );
		$this->num_formats = $this->build_num_format_map( $numbering_xml ?: '' );

		$html = $this->xml_to_html( $document_xml, $link_map );

		if ( empty( trim( $html ) ) ) {
			return new WP_Error( 'dtpost_empty', __( 'Document appears to be empty or could not be parsed.', 'docxtowp' ) );
		}

		$html    = $this->clean_html( $html );
		$title   = $this->extract_title( $html, $file_path, $original_name );
		$content = $this->remove_title_heading( $html );

		// Upload all embedded images from the DOCX to the WP Media Library.
		$content = $this->reattach_images( $content, $file_path );
		$content = $this->finalise_alignment( $content );

		return compact( 'title', 'content' );
	}

	// =========================================================================
	// XML → HTML
	// =========================================================================

	private function xml_to_html( string $xml_str, array $link_map ): string {
		libxml_use_internal_errors( true );
		$dom = new DOMDocument();
		if ( ! $dom->loadXML( $xml_str, LIBXML_NONET ) ) {
			// Retry in recovery mode. Recovery is the DOMDocument::$recover
			// property — there is no LIBXML_RECOVER constant, and referencing
			// one is a fatal Error on PHP 8.
			$dom->recover = true;
			$dom->loadXML( $xml_str, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_NONET );
		}
		libxml_clear_errors();

		$body_list = $dom->getElementsByTagNameNS( self::W_NS, 'body' );
		if ( 0 === $body_list->length ) { return ''; }

		$body = $body_list->item( 0 );
		$html = '';

		// Stack of currently open list tags, innermost last. Its depth is the
		// nesting level, so a <w:ilvl> of 2 means a stack three deep.
		$stack = [];

		foreach ( $body->childNodes as $node ) {
			if ( XML_ELEMENT_NODE !== $node->nodeType ) { continue; }
			$local = $node->localName;

			if ( 'p' === $local ) {
				[ $tag, $inner, $attr ] = $this->parse_paragraph( $node, $link_map );

				if ( 'li' === $tag ) {
					$info   = $this->get_list_info( $node );
					$target = $info['level'] + 1;

					if ( empty( $stack ) ) {
						$html   .= '<' . $info['tag'] . '>' . "\n";
						$stack[] = $info['tag'];
					} elseif ( $target > count( $stack ) ) {
						// Going deeper. The parent <li> stays open so the
						// nested list sits inside it, which is the only
						// nesting HTML actually allows.
						while ( count( $stack ) < $target ) {
							$html   .= '<' . $info['tag'] . '>' . "\n";
							$stack[] = $info['tag'];
						}
					} else {
						$html .= '</li>' . "\n";
						while ( count( $stack ) > $target ) {
							$html .= '</' . array_pop( $stack ) . '>' . "\n";
							$html .= '</li>' . "\n";
						}
						// A bulleted list turning into a numbered one (or the
						// reverse) at the same level needs a new list element.
						if ( $stack[ $target - 1 ] !== $info['tag'] ) {
							$html   .= '</' . array_pop( $stack ) . '>' . "\n";
							$html   .= '<' . $info['tag'] . '>' . "\n";
							$stack[] = $info['tag'];
						}
					}

					// Left open — the next item, or close_lists(), ends it.
					$html .= '<li>' . $inner;
				} else {
					$html .= $this->close_lists( $stack );

					// Images produce non-text inner — allow empty text inner for img-only paragraphs.
					if ( $tag && ( '' !== trim( wp_strip_all_tags( $inner ) ) || '' !== trim( $inner ) ) ) {
						$html .= '<' . $tag . $attr . '>' . $inner . '</' . $tag . '>' . "\n";
					}
				}

			} elseif ( 'tbl' === $local ) {
				$html .= $this->close_lists( $stack );
				$html .= $this->parse_table( $node, $link_map );
			}
		}

		$html .= $this->close_lists( $stack );
		return $html;
	}

	/**
	 * Closes every open list, innermost first, along with the <li> each one
	 * was nested inside. Empties the stack.
	 *
	 * @param array<int,string> $stack Open list tags, passed by reference.
	 */
	private function close_lists( array &$stack ): string {
		$html = '';
		while ( ! empty( $stack ) ) {
			$html .= '</li>' . "\n";
			$html .= '</' . array_pop( $stack ) . '>' . "\n";
		}
		return $html;
	}

	/**
	 * Resolves a list paragraph's nesting level and whether it is ordered.
	 *
	 * @return array{level:int,tag:string}
	 */
	private function get_list_info( DOMElement $p ): array {
		$level = 0;
		$tag   = '';

		$pPr = $p->getElementsByTagNameNS( self::W_NS, 'pPr' );
		if ( $pPr->length > 0 ) {
			$numPr = $pPr->item( 0 )->getElementsByTagNameNS( self::W_NS, 'numPr' );
			if ( $numPr->length > 0 ) {
				$ilvl = $numPr->item( 0 )->getElementsByTagNameNS( self::W_NS, 'ilvl' );
				if ( $ilvl->length > 0 ) {
					$level = (int) $this->w_val( $ilvl->item( 0 ) );
				}

				$num_id = $numPr->item( 0 )->getElementsByTagNameNS( self::W_NS, 'numId' );
				if ( $num_id->length > 0 ) {
					$id = $this->w_val( $num_id->item( 0 ) );
					if ( '' !== $id && isset( $this->num_formats[ $id ] ) ) {
						$levels = $this->num_formats[ $id ];
						$tag    = $levels[ $level ] ?? ( $levels[0] ?? '' );
					}
				}
			}
		}

		// No numbering.xml entry to go on — guess from the style name.
		if ( '' === $tag ) {
			$tag = $this->detect_list_type( $p );
		}

		// Word allows nine levels; anything beyond that is malformed.
		return [
			'level' => max( 0, min( $level, 8 ) ),
			'tag'   => $tag,
		];
	}

	/**
	 * Reads a paragraph's alignment, if it is anything other than the default.
	 *
	 * @return string One of 'center', 'right', 'justify', or '' for left.
	 */
	private function get_paragraph_alignment( DOMElement $p ): string {
		$pPr = $p->getElementsByTagNameNS( self::W_NS, 'pPr' );
		if ( 0 === $pPr->length ) {
			return '';
		}

		$jc = $pPr->item( 0 )->getElementsByTagNameNS( self::W_NS, 'jc' );
		if ( 0 === $jc->length ) {
			return '';
		}

		// Word writes 'both' for justified, and 'start'/'end' in newer files
		// where older ones say 'left'/'right'.
		switch ( strtolower( $this->w_val( $jc->item( 0 ) ) ) ) {
			case 'center':
				return 'center';
			case 'right':
			case 'end':
				return 'right';
			case 'both':
			case 'distribute':
				return 'justify';
			default:
				return '';
		}
	}

	/**
	 * Converts the alignment placeholder into the class WordPress themes style.
	 *
	 * Runs after clean_html(), which strips every class attribute in order to
	 * discard Word's own.
	 */
	private function finalise_alignment( string $html ): string {
		return (string) preg_replace(
			'/ data-dtpost-align="(center|right|justify)"/i',
			' class="has-text-align-$1"',
			$html
		);
	}

	/**
	 * Reads a w:val attribute, whether or not the document declares the
	 * wordprocessingml namespace properly.
	 */
	private function w_val( DOMElement $el ): string {
		$v = $el->getAttributeNS( self::W_NS, 'val' );
		if ( '' === $v ) {
			$v = $el->getAttribute( 'w:val' );
		}
		return $v;
	}

	// ── Paragraph ──────────────────────────────────────────────────────────────

	/**
	 * @return array{0:string,1:string,2:string} tag, inner HTML, attributes
	 */
	private function parse_paragraph( DOMElement $p, array $link_map ): array {
		$style = $this->get_paragraph_style( $p );
		$inner = $this->get_paragraph_inner( $p, $link_map );

		// If inner contains an <img> (image-only paragraph) keep it even without text.
		$has_img  = str_contains( $inner, '<img' );
		$has_text = '' !== trim( wp_strip_all_tags( $inner ) );

		if ( ! $has_img && ! $has_text ) {
			return [ '', '', '' ];
		}

		// Alignment rides along as a data attribute rather than a class,
		// because clean_html() strips class and style to get rid of Word's
		// own. finalise_alignment() turns it into a real class afterwards.
		$attr = '';
		$align = $this->get_paragraph_alignment( $p );
		if ( '' !== $align ) {
			$attr = ' data-dtpost-align="' . esc_attr( $align ) . '"';
		}

		$level = $this->heading_level( $p );
		if ( $level > 0 ) {
			return [ 'h' . $level, $inner, $attr ];
		}

		// Word's Quote and Intense Quote styles, plus the block-indent style
		// older templates use for the same thing.
		if ( preg_match( '/^(intense)?quote$/i', $style ) || 'blocktext' === strtolower( $style ) ) {
			return [ 'blockquote', '<p>' . $inner . '</p>', $attr ];
		}

		if ( preg_match( '/^(list|listparagraph|listbullet|listnumber|listcontinue)/i', $style ) ) {
			return [ 'li', $inner, '' ];
		}

		$pPr = $p->getElementsByTagNameNS( self::W_NS, 'pPr' );
		if ( $pPr->length > 0 && $pPr->item(0)->getElementsByTagNameNS( self::W_NS, 'numPr' )->length > 0 ) {
			return [ 'li', $inner, '' ];
		}

		// Image-only paragraph — wrap in <p> so it renders properly.
		return [ 'p', $inner, $attr ];
	}

	// ── Paragraph inner (runs + drawings) ─────────────────────────────────────

	private function get_paragraph_inner( DOMElement $p, array $link_map ): string {
		$html = '';
		foreach ( $p->childNodes as $child ) {
			if ( XML_ELEMENT_NODE !== $child->nodeType ) { continue; }
			$local = $child->localName;

			if ( 'r' === $local ) {
				$html .= $this->parse_run( $child );
			} elseif ( 'hyperlink' === $local ) {
				$rId  = $child->getAttributeNS( self::R_NS, 'id' );
				if ( '' === $rId ) { $rId = $child->getAttribute( 'r:id' ); }
				$url  = $link_map[ $rId ] ?? '#';
				$text = '';
				foreach ( $child->childNodes as $run ) {
					if ( XML_ELEMENT_NODE === $run->nodeType && 'r' === $run->localName ) {
						$text .= $this->parse_run( $run );
					}
				}
				$html .= '<a href="' . esc_url( $url ) . '">' . $text . '</a>';
			} elseif ( 'ins' === $local ) {
				foreach ( $child->childNodes as $run ) {
					if ( XML_ELEMENT_NODE === $run->nodeType && 'r' === $run->localName ) {
						$html .= $this->parse_run( $run );
					}
				}
			}
			// 'del' silently skipped.
		}
		return $html;
	}

	// ── Run (text + inline images) ─────────────────────────────────────────────

	private function parse_run( DOMElement $r ): string {
		$text   = '';
		$bold   = false;
		$italic = false;
		$under  = false;
		$strike = false;
		$vert   = '';
		$img_html = '';

		foreach ( $r->childNodes as $child ) {
			if ( XML_ELEMENT_NODE !== $child->nodeType ) { continue; }
			$local = $child->localName;

			if ( 'rPr' === $local ) {
				$b = $child->getElementsByTagNameNS( self::W_NS, 'b' );
				if ( $b->length > 0 ) {
					$v    = $b->item(0)->getAttributeNS( self::W_NS, 'val' );
					$bold = ( '' === $v || 'true' === $v || '1' === $v );
				}
				$i = $child->getElementsByTagNameNS( self::W_NS, 'i' );
				if ( $i->length > 0 ) {
					$v      = $i->item(0)->getAttributeNS( self::W_NS, 'val' );
					$italic = ( '' === $v || 'true' === $v || '1' === $v );
				}
				$u = $child->getElementsByTagNameNS( self::W_NS, 'u' );
				if ( $u->length > 0 ) {
					$uv    = $this->w_val( $u->item(0) );
					$under = ( '' !== $uv && 'none' !== $uv );
				}
				$s = $child->getElementsByTagNameNS( self::W_NS, 'strike' );
				if ( $s->length > 0 ) {
					$v      = $this->w_val( $s->item(0) );
					$strike = ( '' === $v || 'true' === $v || '1' === $v );
				}
				// Superscript and subscript — footnote markers, ™, chemical
				// and mathematical notation all depend on these.
				$va = $child->getElementsByTagNameNS( self::W_NS, 'vertAlign' );
				if ( $va->length > 0 ) {
					$v = $this->w_val( $va->item(0) );
					if ( 'superscript' === $v ) {
						$vert = 'sup';
					} elseif ( 'subscript' === $v ) {
						$vert = 'sub';
					}
				}
			} elseif ( 't' === $local ) {
				$text .= esc_html( $child->nodeValue );
			} elseif ( 'br' === $local ) {
				$text .= '<br>';
			} elseif ( 'tab' === $local ) {
				$text .= '&nbsp;&nbsp;&nbsp;&nbsp;';
			} elseif ( 'drawing' === $local ) {
				// ── INLINE IMAGE (modern docx) ────────────────────────────────
				$img_html .= $this->parse_drawing( $child );
			} elseif ( 'pict' === $local ) {
				// ── INLINE IMAGE (legacy VML) ─────────────────────────────────
				$img_html .= $this->parse_pict( $child );
			}
		}

		// Apply formatting to text, innermost first.
		if ( '' !== $text ) {
			if ( '' !== $vert ) { $text = '<' . $vert . '>' . $text . '</' . $vert . '>'; }
			if ( $strike ) { $text = '<s>' . $text . '</s>'; }
			if ( $under  ) { $text = '<u>' . $text . '</u>'; }
			if ( $italic ) { $text = '<em>' . $text . '</em>'; }
			if ( $bold   ) { $text = '<strong>' . $text . '</strong>'; }
		}

		return $text . $img_html;
	}

	// ── Drawing (modern <w:drawing> / DrawingML) ───────────────────────────────

	/**
	 * Extracts the rId from a <w:drawing> element and emits a placeholder <img>.
	 * The placeholder uses data-docx-img="filename" which reattach_images() replaces.
	 */
	private function parse_drawing( DOMElement $drawing ): string {
		// <wp:inline> or <wp:anchor> → <a:graphic> → <a:graphicData> → <pic:pic> → <pic:blipFill> → <a:blip r:embed="rIdX">
		$blips = $drawing->getElementsByTagNameNS( self::BLIP_NS, 'blip' );
		if ( 0 === $blips->length ) {
			return '';
		}

		$blip = $blips->item( 0 );
		$rId  = $blip->getAttributeNS( self::R_NS, 'embed' );
		if ( '' === $rId ) {
			$rId = $blip->getAttribute( 'r:embed' );
		}

		if ( '' === $rId || ! isset( $this->image_rids[ $rId ] ) ) {
			return '';
		}

		$filename = $this->image_rids[ $rId ];

		// Try to get description from <wp:docPr descr="..."> for alt text.
		$ns_wp    = 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing';
		$docPrs   = $drawing->getElementsByTagNameNS( $ns_wp, 'docPr' );
		$alt      = '';
		if ( $docPrs->length > 0 ) {
			$alt = $docPrs->item(0)->getAttribute( 'descr' );
		}

		return '<img data-docx-img="' . esc_attr( $filename ) . '" alt="' . esc_attr( $alt ) . '">';
	}

	// ── Pict (legacy VML) ──────────────────────────────────────────────────────

	private function parse_pict( DOMElement $pict ): string {
		// <v:shape> → <v:imagedata r:id="rIdX">
		$ns_v = 'urn:schemas-microsoft-com:vml';
		$imgs = $pict->getElementsByTagNameNS( $ns_v, 'imagedata' );
		if ( 0 === $imgs->length ) {
			return '';
		}

		$imgdata = $imgs->item( 0 );
		$rId     = $imgdata->getAttributeNS( self::R_NS, 'id' );
		if ( '' === $rId ) { $rId = $imgdata->getAttribute( 'r:id' ); }

		if ( '' === $rId || ! isset( $this->image_rids[ $rId ] ) ) {
			return '';
		}

		$filename = $this->image_rids[ $rId ];
		return '<img data-docx-img="' . esc_attr( $filename ) . '" alt="">';
	}

	// ── Table ──────────────────────────────────────────────────────────────────

	private function parse_table( DOMElement $tbl, array $link_map ): string {
		$rows = $tbl->getElementsByTagNameNS( self::W_NS, 'tr' );

		$head = '';
		$body = '';

		foreach ( $rows as $row ) {
			$is_header = $this->is_header_row( $row );
			$cell_tag  = $is_header ? 'th' : 'td';

			$row_html = '<tr>';
			$cells    = $row->getElementsByTagNameNS( self::W_NS, 'tc' );
			foreach ( $cells as $cell ) {
				$cell_html = '';
				$paras     = $cell->getElementsByTagNameNS( self::W_NS, 'p' );
				foreach ( $paras as $cp ) {
					$inner = $this->get_paragraph_inner( $cp, $link_map );
					if ( '' !== trim( $inner ) ) {
						$cell_html .= $inner . ' ';
					}
				}
				$row_html .= '<' . $cell_tag . '>' . trim( $cell_html ) . '</' . $cell_tag . '>';
			}
			$row_html .= '</tr>' . "\n";

			// Only a leading run of header rows becomes <thead>; a repeated
			// header partway down a table is Word's page-break marker, not a
			// second heading.
			if ( $is_header && '' === $body ) {
				$head .= $row_html;
			} else {
				$body .= $row_html;
			}
		}

		$html = '<table>' . "\n";
		if ( '' !== $head ) {
			$html .= '<thead>' . "\n" . $head . '</thead>' . "\n";
		}
		if ( '' !== $body ) {
			$html .= '<tbody>' . "\n" . $body . '</tbody>' . "\n";
		}
		$html .= '</table>' . "\n";

		return $html;
	}

	/**
	 * A row is a header row when Word marks it to repeat across pages
	 * (<w:tblHeader/>), which is how "Header Row" in the table designer is
	 * stored.
	 */
	private function is_header_row( DOMElement $row ): bool {
		$trPr = $row->getElementsByTagNameNS( self::W_NS, 'trPr' );
		if ( 0 === $trPr->length ) {
			return false;
		}

		$flag = $trPr->item( 0 )->getElementsByTagNameNS( self::W_NS, 'tblHeader' );
		if ( 0 === $flag->length ) {
			return false;
		}

		// Present with no w:val means on; w:val="false"/"0" turns it off.
		$v = $this->w_val( $flag->item( 0 ) );
		return ( '' === $v || 'true' === $v || '1' === $v || 'on' === $v );
	}

	// =========================================================================
	// Image pipeline: upload to WP Media + replace placeholders
	// =========================================================================

	/**
	 * Main image processing function — called after HTML generation.
	 *
	 * Steps:
	 *   A. Find all  data-docx-img="filename"  placeholders emitted by parse_drawing/parse_pict.
	 *   B. Open the zip, find matching word/media/{filename} entries, upload each to WP Media.
	 *   C. Replace placeholder with real <img src="https://..."> tag.
	 *   D. Also handle any base64 src="data:..." images (Mammoth output).
	 */
	private function reattach_images( string $html, string $file_path ): string {
		// DTPost_Image::load_wp_upload_functions() loads any required WP admin helpers.
		$image_handler = new DTPost_Image();

		// ── A. Collect all placeholder filenames from the HTML ──────────────
		// filename => alt text. Word's own image description (<wp:docPr descr>)
		// is already on the placeholder tag; keeping it here lets us put it on
		// the Media Library attachment as well as in the post content.
		$placeholders = [];
		if ( preg_match_all( '/<img[^>]*data-docx-img="([^"]+)"[^>]*>/i', $html, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $m ) {
				$filename = $m[1];
				if ( ! empty( $placeholders[ $filename ] ) ) {
					continue;
				}
				$alt = '';
				if ( preg_match( '/\salt="([^"]*)"/i', $m[0], $alt_match ) ) {
					$alt = $alt_match[1];
				}
				$placeholders[ $filename ] = $alt;
			}
		}

		// ── B & C. Open zip, upload, replace ────────────────────────────────
		if ( ! empty( $placeholders ) && class_exists( 'ZipArchive' ) ) {
			$zip = new ZipArchive();
			if ( true === $zip->open( $file_path ) ) {
				foreach ( $placeholders as $filename => $alt ) {
					// The blob is at word/media/{filename} inside the zip.
					$blob = $zip->getFromName( 'word/media/' . $filename );
					if ( false === $blob ) {
						// Try case-insensitive search through all entries.
						for ( $i = 0; $i < $zip->numFiles; $i++ ) {
							$entry = $zip->getNameIndex( $i );
							if ( strtolower( $entry ) === strtolower( 'word/media/' . $filename ) ) {
								$blob = $zip->getFromIndex( $i );
								break;
							}
						}
					}

					if ( false === $blob || '' === $blob ) {
						continue;
					}

					$attach_id = $image_handler->upload_blob_to_media( $blob, $filename );
					if ( ! $attach_id ) {
						continue;
					}

					// Carry Word's image description onto the attachment, so the
					// Media Library entry has alt text and not just this post.
					if ( '' !== $alt ) {
						update_post_meta(
							$attach_id,
							'_wp_attachment_image_alt',
							sanitize_text_field( wp_specialchars_decode( $alt, ENT_QUOTES ) )
						);
					}

					$url = wp_get_attachment_url( $attach_id );
					if ( ! $url ) {
						continue;
					}

					// Replace the placeholder attribute with a real src URL.
					// The <img> tag already has all other attributes set by
					// parse_drawing. The sizing style goes on here rather than
					// there because clean_html() — which runs before this —
					// strips every style attribute to get rid of Word's own.
					$html = preg_replace(
						'/data-docx-img="' . preg_quote( $filename, '/' ) . '"/i',
						'src="' . esc_url( $url ) . '" style="max-width:100%;height:auto"',
						$html
					);
				}
				$zip->close();
			}
		}

		// Remove any leftover data-docx-img placeholders that couldn't be resolved.
		$html = preg_replace( '/<img[^>]+data-docx-img="[^"]*"[^>]*>/i', '', $html );

		// ── D. Base64 images (Mammoth output) ────────────────────────────────
		$html = preg_replace_callback(
			'/<img[^>]+src="data:([^;]+);base64,([^"]+)"([^>]*)>/i',
			function ( array $m ) use ( $image_handler ): string {
				$mime      = $m[1];
				$ext       = explode( '/', $mime )[1] ?? 'jpg';
				$ext       = preg_replace( '/[^a-z0-9]/', '', strtolower( $ext ) );
				$blob      = base64_decode( $m[2] );
				$attach_id = $image_handler->upload_blob_to_media(
					$blob,
					'docx-img-' . wp_generate_password( 8, false ) . '.' . $ext
				);
				if ( ! $attach_id ) {
					return '';
				}
				$url = wp_get_attachment_url( $attach_id );
				return '<img src="' . esc_url( $url ) . '" alt="" style="max-width:100%;height:auto">';
			},
			$html
		);

		return $html;
	}

	// =========================================================================
	// Relationship maps
	// =========================================================================

	/**
	 * Build rId → URL map for hyperlinks.
	 * @return array<string,string>
	 */
	private function build_link_map( string $rels_xml ): array {
		$map = [];
		if ( empty( $rels_xml ) ) { return $map; }
		libxml_use_internal_errors( true );
		$dom = new DOMDocument();
		$dom->loadXML( $rels_xml );
		libxml_clear_errors();
		foreach ( $dom->getElementsByTagName( 'Relationship' ) as $rel ) {
			if ( str_contains( (string) $rel->getAttribute( 'Type' ), 'hyperlink' ) ) {
				$map[ $rel->getAttribute( 'Id' ) ] = $rel->getAttribute( 'Target' );
			}
		}
		return $map;
	}

	/**
	 * Build rId → filename map for images.
	 * Parses word/_rels/document.xml.rels and finds entries whose Target is media/*.
	 *
	 * @return array<string,string>  rId => bare filename (e.g. "image1.jpeg")
	 */
	private function build_image_rid_map( string $rels_xml ): array {
		$map = [];
		if ( empty( $rels_xml ) ) { return $map; }
		libxml_use_internal_errors( true );
		$dom = new DOMDocument();
		$dom->loadXML( $rels_xml );
		libxml_clear_errors();
		foreach ( $dom->getElementsByTagName( 'Relationship' ) as $rel ) {
			$type   = (string) $rel->getAttribute( 'Type' );
			$target = (string) $rel->getAttribute( 'Target' );
			$id     = (string) $rel->getAttribute( 'Id' );

			// image relationships look like: .../image or .../hdphoto
			if ( preg_match( '#/image$|/hdphoto$#i', $type ) ) {
				// Target is typically "media/image1.jpeg" (relative to word/)
				$filename = basename( $target );
				$map[ $id ] = $filename;
			}
		}
		return $map;
	}

	// =========================================================================
	// Helpers
	// =========================================================================

	private function get_paragraph_style( DOMElement $p ): string {
		$pPr = $p->getElementsByTagNameNS( self::W_NS, 'pPr' );
		if ( 0 === $pPr->length ) { return ''; }
		$pStyle = $pPr->item(0)->getElementsByTagNameNS( self::W_NS, 'pStyle' );
		if ( 0 === $pStyle->length ) { return ''; }
		$val = $pStyle->item(0)->getAttributeNS( self::W_NS, 'val' );
		if ( '' === $val ) { $val = $pStyle->item(0)->getAttribute( 'w:val' ); }
		return (string) $val;
	}

	/**
	 * The heading level of a paragraph, 1–6, or 0 for body text.
	 *
	 * Three signals, strongest first:
	 *   1. the paragraph's style resolved through styles.xml (name, outline
	 *      level, basedOn chain) — see build_heading_style_map()
	 *   2. the style ID itself looking like "Heading3", which is what the
	 *      parser matched before 1.2.1 and still covers a document with no
	 *      styles part at all
	 *   3. an outline level set directly on the paragraph, which is how Word
	 *      records "this is a heading" when someone sets it from Paragraph →
	 *      Outline level without applying a style
	 */
	private function heading_level( DOMElement $p ): int {
		$style = $this->get_paragraph_style( $p );

		if ( '' !== $style && isset( $this->heading_styles[ $style ] ) ) {
			return $this->heading_styles[ $style ];
		}

		if ( preg_match( '/^heading[\s_\-]?([1-6])$/i', $style, $m ) ) {
			return (int) $m[1];
		}

		$pPr = $p->getElementsByTagNameNS( self::W_NS, 'pPr' );
		if ( $pPr->length > 0 ) {
			$lvl = $pPr->item( 0 )->getElementsByTagNameNS( self::W_NS, 'outlineLvl' );
			if ( $lvl->length > 0 ) {
				return $this->level_from_outline( $this->w_attr( $lvl->item( 0 ), 'val' ) );
			}
		}

		return 0;
	}

	/**
	 * Builds styleId → heading level from word/styles.xml.
	 *
	 * For every paragraph style, the level comes from the first of:
	 *   - its canonical name, "heading N" — the name Word keeps in English
	 *     whatever the UI language, and the reason "berschrift1" is still
	 *     recognisable as Heading 1
	 *   - its own outline level, 0–5 (9 means "body text" and is honoured as
	 *     an explicit opt-out: the built-in "TOC Heading" style is based on
	 *     Heading 1 and sets 9 precisely so it is not treated as one)
	 *   - its ID looking like "HeadingN"
	 *   - the style it is based on, resolved the same way, so a custom
	 *     "Chapter title" based on Heading 1 imports as an <h1>
	 *
	 * @return array<string, int>
	 */
	private function build_heading_style_map( string $styles_xml ): array {
		if ( '' === trim( $styles_xml ) ) {
			return [];
		}

		libxml_use_internal_errors( true );
		$dom          = new DOMDocument();
		$dom->recover = true;
		$loaded       = $dom->loadXML( $styles_xml, LIBXML_NOERROR | LIBXML_NOWARNING );
		libxml_clear_errors();
		if ( ! $loaded ) {
			return [];
		}

		$levels   = [];   // styleId → 1–6, or 0 when the style opts out
		$based_on = [];   // styleId → parent styleId, for the inheritance pass

		foreach ( $dom->getElementsByTagNameNS( self::W_NS, 'style' ) as $style ) {
			if ( 'paragraph' !== $this->w_attr( $style, 'type' ) ) {
				continue;
			}
			$id = $this->w_attr( $style, 'styleId' );
			if ( '' === $id ) {
				continue;
			}

			$name = '';
			$n    = $style->getElementsByTagNameNS( self::W_NS, 'name' );
			if ( $n->length > 0 ) {
				$name = $this->w_attr( $n->item( 0 ), 'val' );
			}

			$outline = null;
			$pPr     = $style->getElementsByTagNameNS( self::W_NS, 'pPr' );
			if ( $pPr->length > 0 ) {
				$lvl = $pPr->item( 0 )->getElementsByTagNameNS( self::W_NS, 'outlineLvl' );
				if ( $lvl->length > 0 ) {
					$outline = $this->w_attr( $lvl->item( 0 ), 'val' );
				}
			}

			$b = $style->getElementsByTagNameNS( self::W_NS, 'basedOn' );
			if ( $b->length > 0 ) {
				$based_on[ $id ] = $this->w_attr( $b->item( 0 ), 'val' );
			}

			if ( preg_match( '/^heading\s*([1-6])$/i', trim( $name ), $m ) ) {
				$levels[ $id ] = (int) $m[1];
			} elseif ( null !== $outline && '' !== $outline ) {
				// Explicit on the style, including an explicit 9 = "not a
				// heading", which must win over anything inherited.
				$levels[ $id ] = $this->level_from_outline( $outline );
			} elseif ( preg_match( '/^heading[\s_\-]?([1-6])$/i', $id, $m ) ) {
				$levels[ $id ] = (int) $m[1];
			}
		}

		// Inherit through basedOn. Chains are short; six passes covers any
		// sane document and a cycle cannot loop forever.
		for ( $pass = 0; $pass < 6; $pass++ ) {
			$changed = false;
			foreach ( $based_on as $id => $parent ) {
				if ( isset( $levels[ $id ] ) || ! isset( $levels[ $parent ] ) ) {
					continue;
				}
				$levels[ $id ] = $levels[ $parent ];
				$changed       = true;
			}
			if ( ! $changed ) {
				break;
			}
		}

		// Only real heading levels leave this method. A 0 (explicit opt-out)
		// is dropped so heading_level() falls through to its later checks.
		return array_filter( $levels, static fn( int $l ): bool => $l >= 1 && $l <= 6 );
	}

	/** Word outline level (0–5 = Heading 1–6, 9 = body text) → heading level or 0. */
	private function level_from_outline( string $val ): int {
		if ( '' === $val || ! is_numeric( $val ) ) {
			return 0;
		}
		$n = (int) $val;
		return ( $n >= 0 && $n <= 5 ) ? $n + 1 : 0;
	}

	/** Reads a w:-namespaced attribute, tolerating a non-namespaced fallback. */
	private function w_attr( DOMElement $el, string $name ): string {
		$val = $el->getAttributeNS( self::W_NS, $name );
		if ( '' === $val ) {
			$val = $el->getAttribute( 'w:' . $name );
		}
		return (string) $val;
	}

	private function detect_list_type( DOMElement $p ): string {
		return preg_match( '/number|num|ordered/i', $this->get_paragraph_style( $p ) ) ? 'ol' : 'ul';
	}

	/**
	 * Builds numId → [ level => 'ol'|'ul' ] from word/numbering.xml.
	 *
	 * Word gives bulleted and numbered lists the same paragraph style —
	 * ListParagraph — so the style name cannot tell them apart. Only
	 * numbering.xml can: numId → abstractNumId → the level's w:numFmt.
	 *
	 * @return array<string,array<int,string>>
	 */
	private function build_num_format_map( string $xml ): array {
		if ( '' === trim( $xml ) ) {
			return [];
		}

		libxml_use_internal_errors( true );
		$dom = new DOMDocument();
		if ( ! $dom->loadXML( $xml, LIBXML_NONET ) ) {
			libxml_clear_errors();
			return [];
		}
		libxml_clear_errors();

		// abstractNumId → [ level => tag ]
		$abstract = [];
		foreach ( $dom->getElementsByTagNameNS( self::W_NS, 'abstractNum' ) as $an ) {
			$aid = $an->getAttributeNS( self::W_NS, 'abstractNumId' );
			if ( '' === $aid ) {
				$aid = $an->getAttribute( 'w:abstractNumId' );
			}
			if ( '' === $aid ) {
				continue;
			}

			$levels = [];
			foreach ( $an->getElementsByTagNameNS( self::W_NS, 'lvl' ) as $lvl ) {
				$ilvl = $lvl->getAttributeNS( self::W_NS, 'ilvl' );
				if ( '' === $ilvl ) {
					$ilvl = $lvl->getAttribute( 'w:ilvl' );
				}

				$fmt_nodes = $lvl->getElementsByTagNameNS( self::W_NS, 'numFmt' );
				if ( 0 === $fmt_nodes->length ) {
					continue;
				}

				$fmt = $this->w_val( $fmt_nodes->item( 0 ) );
				// Everything that is not a bullet counts as ordered: decimal,
				// lowerLetter, upperRoman and the rest all render as <ol>.
				$levels[ (int) $ilvl ] = ( 'bullet' === $fmt || 'none' === $fmt || '' === $fmt ) ? 'ul' : 'ol';
			}

			$abstract[ $aid ] = $levels;
		}

		// numId → abstractNumId → the level map above.
		$map = [];
		foreach ( $dom->getElementsByTagNameNS( self::W_NS, 'num' ) as $num ) {
			$nid = $num->getAttributeNS( self::W_NS, 'numId' );
			if ( '' === $nid ) {
				$nid = $num->getAttribute( 'w:numId' );
			}
			if ( '' === $nid ) {
				continue;
			}

			$ref = $num->getElementsByTagNameNS( self::W_NS, 'abstractNumId' );
			if ( 0 === $ref->length ) {
				continue;
			}

			$aid = $this->w_val( $ref->item( 0 ) );
			if ( isset( $abstract[ $aid ] ) ) {
				$map[ $nid ] = $abstract[ $aid ];
			}
		}

		return $map;
	}

	private function clean_html( string $html ): string {
		$html = preg_replace( '/<\/?[a-z]+:[^>]*>/i', '', $html );
		$html = preg_replace( '/\s+(style|class|lang|dir|xml:lang)="[^"]*"/i', '', $html );
		// Don't collapse whitespace — it can eat spaces between inline elements.
		$html = preg_replace( '/<(p|li|h[1-6])>\s*<\/\1>/i', '', $html );
		return trim( $html );
	}

	/**
	 * The post title, and a note of where it came from.
	 *
	 * Sets $title_tag to the heading the title was taken from, so
	 * remove_title_heading() can take that exact heading out of the body.
	 * Before 1.2.1 the title could come from an <h2> while only an <h1> was
	 * ever removed, so a document whose top heading was Heading 2 — common,
	 * since plenty of people reserve Heading 1 for the page title — had its
	 * heading repeated as the first line of the post.
	 */
	private function extract_title( string $html, string $file_path, string $original_name ): string {
		foreach ( [ 'h1', 'h2' ] as $tag ) {
			if ( preg_match( '/<' . $tag . '[^>]*>(.*?)<\/' . $tag . '>/is', $html, $m ) ) {
				$t = trim( wp_strip_all_tags( $m[1] ) );
				if ( '' !== $t ) {
					$this->title_tag = $tag;
					return $t;
				}
			}
		}

		$this->title_tag = '';

		// Capitalisation is DTPost_Title's business — see Settings → Content
		// Format. A document that has a heading keeps that heading exactly as
		// it was written; only a filename-derived title is ever restyled.
		$title = DTPost_Title::from_filename( $original_name );
		if ( '' !== $title ) {
			return $title;
		}

		$basename = pathinfo( $file_path, PATHINFO_FILENAME );
		if ( preg_match( '/^[0-9a-f\-]{32,36}$/i', $basename ) ) {
			return __( 'Untitled Document', 'docxtowp' );
		}

		$title = DTPost_Title::from_filename( $basename );
		return '' !== $title ? $title : __( 'Untitled Document', 'docxtowp' );
	}

	/**
	 * Removes the heading the title was taken from, so it is not repeated as
	 * the first line of the post. No-op when the title came from the filename.
	 */
	private function remove_title_heading( string $html ): string {
		$tag = $this->title_tag;
		if ( '' === $tag ) {
			return $html;
		}

		return (string) preg_replace( '/<' . $tag . '[^>]*>.*?<\/' . $tag . '>\s*/is', '', $html, 1 );
	}
}

