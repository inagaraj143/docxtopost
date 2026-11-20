<?php
/**
 * Handles post creation for DocxToPost.
 *
 * Supports all public WordPress post types and standard post statuses.
 *
 * @package DocxToPost
 */

defined( 'ABSPATH' ) || exit;

class DTPost_Publisher {

	/**
	 * The allowed post statuses.
	 *
	 * @var array<string>
	 */
	private array $allowed_statuses = array( 'publish', 'draft', 'private', 'pending' );

	/**
	 * Creates a WordPress post from provided data.
	 *
	 * Supports all registered public post types and standard statuses.
	 *
	 * @param array<string, mixed> $data
	 * @return int|WP_Error
	 */
	public function publish( array $data ): int|WP_Error {

		// Resolve post type, allow any registered public post type.
		$requested_type   = sanitize_key( $data['post_type'] ?? 'post' );
		$public_types     = array_keys( get_post_types( array( 'public' => true ) ) );
		$post_type        = in_array( $requested_type, $public_types, true ) ? $requested_type : 'post';

		// Resolve post status: allow publish, draft, private, pending.
		$requested_status = sanitize_key( $data['status'] ?? 'draft' );
		$status           = in_array( $requested_status, $this->allowed_statuses, true )
			? $requested_status
			: 'draft';

		// Sanitise first, then add block delimiters. Doing it in this order
		// keeps wp_kses_post() working on ordinary HTML, which is what it is
		// built for, and leaves the delimiters we generate untouched.
		$content = wp_kses_post( $data['content'] ?? '' );
		$meta    = array();

		if ( function_exists( 'dtpost_use_block_format' ) && dtpost_use_block_format( $post_type ) ) {
			// Footnotes become core's Footnotes block where this post type has
			// it. Otherwise, and in the classic editor, they stay the numbered
			// list the parser made, which links both ways on its own.
			$notes = DTPost_Footnotes::core_available( $post_type ) ? DTPost_Footnotes::to_core( $content ) : null;
			if ( $notes ) {
				$content           = $notes['content'];
				$meta['footnotes'] = DTPost_Footnotes::meta_value( $notes['notes'] );
			}

			$content = DTPost_Blocks::serialize( $content );

			if ( $notes ) {
				$content .= DTPost_Footnotes::block_markup();
			}
		}

		// comment_status is deliberately absent: WordPress fills it from the
		// site's default_comment_status option. Forcing 'open' turned comments
		// back on for sites that had switched them off.
		$post_arr = array(
			'post_type'    => $post_type,
			'post_title'   => sanitize_text_field( $data['title']   ?? '' ),
			'post_content' => $content,
			'post_excerpt' => sanitize_textarea_field( $data['excerpt'] ?? '' ),
			'post_status'  => $status,
			'post_author'  => absint( $data['author'] ?? get_current_user_id() ),
			'post_name'    => sanitize_title( $data['slug'] ?? $data['title'] ?? '' ),
		);

		// With the post, not after it: the Footnotes block reads its notes from
		// this meta, and WordPress writes meta_input before its save hooks run.
		if ( $meta ) {
			$post_arr['meta_input'] = $meta;
		}

		// Categories the document named and the preview screen marked as new.
		// DTPost_Details checks the user may create categories at all.
		if ( ! empty( $data['new_categories'] ) && is_object_in_taxonomy( $post_type, 'category' ) ) {
			$data['category'] = array_merge(
				(array) ( $data['category'] ?? array() ),
				DTPost_Details::create_categories( (array) $data['new_categories'] )
			);
		}

		// Categories (only applies to post types that support categories).
		if ( ! empty( $data['category'] ) ) {
			$post_arr['post_category'] = array_map( 'absint', (array) $data['category'] );
		}

		$post_id = wp_insert_post( $post_arr, true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Tags (only applies to post types that support tags).
		if ( ! empty( $data['tags'] ) ) {
			$tag_names = array_filter( array_map( 'trim', explode( ',', $data['tags'] ) ) );
			if ( ! empty( $tag_names ) ) {
				wp_set_object_terms( $post_id, $tag_names, 'post_tag' );
			}
		}

		// Featured image.
		$img_id = absint( $data['featured_image_id'] ?? 0 );
		if ( $img_id > 0 ) {
			set_post_thumbnail( $post_id, $img_id );
		}

		// No wp_publish_post() call here: wp_insert_post() has already set the
		// status and fired the transition hooks, and core early-returns from
		// wp_publish_post() for a post that is already published.

		return $post_id;
	}
}
