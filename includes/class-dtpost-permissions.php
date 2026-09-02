<?php
/**
 * Handles capability and role checks for DocxToPost.
 *
 * @package DocxToPost
 */

defined( 'ABSPATH' ) || exit;

class DTPost_Permissions {

	/**
	 * Checks if the current user is allowed to use the plugin.
	 */
	public function current_user_can_use(): bool {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		$allowed_roles = (array) get_option( 'dtpost_allowed_roles', array( 'administrator', 'editor' ) );
		$user          = wp_get_current_user();

		foreach ( $user->roles as $role ) {
			if ( in_array( $role, $allowed_roles, true ) ) {
				return current_user_can( 'upload_files' );
			}
		}

		return false;
	}

	/**
	 * Checks whether the current user may create a post of this type.
	 *
	 * current_user_can_use() only proves the user may reach the plugin at
	 * all. It says nothing about the post type they asked for, so without
	 * this an Author added to the allowed roles could create Pages.
	 */
	public function can_create( string $post_type ): bool {
		$pto = get_post_type_object( $post_type );
		if ( ! $pto ) {
			return false;
		}

		$cap = $pto->cap->create_posts ?? 'edit_posts';
		return current_user_can( $cap );
	}

	/**
	 * Checks whether the current user may publish a post of this type,
	 * rather than only save it as a draft.
	 */
	public function can_publish( string $post_type ): bool {
		$pto = get_post_type_object( $post_type );
		if ( ! $pto ) {
			return false;
		}

		$cap = $pto->cap->publish_posts ?? 'publish_posts';
		return current_user_can( $cap );
	}

	/**
	 * Checks whether the current user may attribute a post to someone else.
	 */
	public function can_set_author( string $post_type ): bool {
		$pto = get_post_type_object( $post_type );
		if ( ! $pto ) {
			return false;
		}

		$cap = $pto->cap->edit_others_posts ?? 'edit_others_posts';
		return current_user_can( $cap );
	}

	/**
	 * Returns all registered WordPress roles as an array.
	 *
	 * @return array<string, string> role_slug => display_name
	 */
	public function get_all_roles(): array {
		global $wp_roles;
		$roles  = array();
		foreach ( $wp_roles->roles as $slug => $data ) {
			$roles[ $slug ] = translate_user_role( $data['name'] );
		}
		return $roles;
	}
}

