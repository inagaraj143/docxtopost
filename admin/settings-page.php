<?php
/**
 * Admin page: Plugin settings.
 *
 * @package DocxToPost
 */

defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'docxtowp' ) );
}

$saved = false;
if ( isset( $_POST['dtpost_settings_nonce'] ) && wp_verify_nonce( sanitize_key( $_POST['dtpost_settings_nonce'] ), 'dtpost_save_settings' ) ) {
	// Save default post type.
	$public_types        = array_keys( get_post_types( array( 'public' => true ) ) );
	$new_post_type       = sanitize_key( $_POST['dtpost_default_post_type'] ?? 'post' );
	$new_post_type       = in_array( $new_post_type, $public_types, true ) ? $new_post_type : 'post';
	update_option( 'dtpost_default_post_type', $new_post_type );

	// Save content format.
	$allowed_formats = array( 'auto', 'blocks', 'classic' );
	$new_format      = sanitize_key( $_POST['dtpost_content_format'] ?? 'auto' );
	update_option( 'dtpost_content_format', in_array( $new_format, $allowed_formats, true ) ? $new_format : 'auto' );

	$new_title_case = sanitize_key( $_POST['dtpost_filename_title_case'] ?? DTPost_Title::SENTENCE );
	update_option(
		'dtpost_filename_title_case',
		in_array( $new_title_case, DTPost_Title::modes(), true ) ? $new_title_case : DTPost_Title::SENTENCE
	);
	update_option( 'dtpost_convert_embeds', isset( $_POST['dtpost_convert_embeds'] ) ? 1 : 0 );

	// Save default category.
	update_option( 'dtpost_default_category', absint( $_POST['dtpost_default_category'] ?? 1 ) );

	// Save default status.
	$allowed_statuses = array( 'publish', 'draft', 'private', 'pending' );
	$new_status       = sanitize_key( $_POST['dtpost_default_status'] ?? 'draft' );
	if ( ! in_array( $new_status, $allowed_statuses, true ) ) {
		$new_status = 'draft';
	}
	update_option( 'dtpost_default_status', $new_status );

	// Save max upload size.
	update_option( 'dtpost_max_upload_mb', absint( $_POST['dtpost_max_upload_mb'] ?? 10 ) );

	// Save allowed roles.
	update_option( 'dtpost_allowed_roles', array_map( 'sanitize_key', (array) ( $_POST['dtpost_allowed_roles'] ?? array() ) ) );
	$saved = true;
}

$permissions   = new DTPost_Permissions();
$all_roles     = $permissions->get_all_roles();
$allowed_roles = (array) get_option( 'dtpost_allowed_roles', array( 'administrator', 'editor' ) );
$categories    = get_categories( array( 'hide_empty' => false ) );

// Build public post types list.
$public_post_types   = get_post_types( array( 'public' => true ), 'objects' );
$resolved_post_type  = (string) get_option( 'dtpost_default_post_type', 'post' );
$resolved_format     = (string) get_option( 'dtpost_content_format', 'auto' );
$resolved_default_cat    = (int) get_option( 'dtpost_default_category', (int) get_option( 'default_category', 1 ) );
$resolved_default_status = (string) get_option( 'dtpost_default_status', 'draft' );
$resolved_max_mb         = (int) get_option( 'dtpost_max_upload_mb', 10 );

// Validate that the category actually exists.
$cat_exists = false;
foreach ( $categories as $cat ) {
	if ( (int) $cat->term_id === $resolved_default_cat ) {
		$cat_exists = true;
		break;
	}
}
if ( ! $cat_exists && ! empty( $categories ) ) {
	$resolved_default_cat = (int) $categories[0]->term_id;
}

$status_options = array(
	'publish' => __( 'Publish immediately', 'docxtowp' ),
	'draft'   => __( 'Save as draft', 'docxtowp' ),
	'pending' => __( 'Pending review', 'docxtowp' ),
	'private' => __( 'Private', 'docxtowp' ),
);
?>
<div class="wrap">
<h1><?php esc_html_e( 'DocxToPost: Settings', 'docxtowp' ); ?></h1>

<?php if ( $saved ) : ?>
<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Settings saved.', 'docxtowp' ); ?></p></div>
<?php endif; ?>

<form method="post" action="">
<?php wp_nonce_field( 'dtpost_save_settings', 'dtpost_settings_nonce' ); ?>

<div class="dtpost-settings-section">
	<h2><?php esc_html_e( 'Upload', 'docxtowp' ); ?></h2>
	<table class="form-table">
		<tr>
			<th><label for="dtpost_max_upload_mb"><?php esc_html_e( 'Max File Size (MB)', 'docxtowp' ); ?></label></th>
			<td>
				<input type="number" id="dtpost_max_upload_mb" name="dtpost_max_upload_mb" value="<?php echo esc_attr( $resolved_max_mb ); ?>" min="1" max="100" class="small-text">
				<p class="description"><?php esc_html_e( 'Maximum allowed .docx file size in megabytes.', 'docxtowp' ); ?></p>
			</td>
		</tr>
	</table>
</div>

<div class="dtpost-settings-section">
	<h2><?php esc_html_e( 'Post Defaults', 'docxtowp' ); ?></h2>
	<table class="form-table">
		<tr>
			<th><label for="dtpost_default_post_type"><?php esc_html_e( 'Default Post Type', 'docxtowp' ); ?></label></th>
			<td>
				<select id="dtpost_default_post_type" name="dtpost_default_post_type">
					<?php foreach ( $public_post_types as $slug => $obj ) : ?>
					<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $slug, $resolved_post_type ); ?>>
						<?php echo esc_html( $obj->labels->singular_name ); ?>
					</option>
					<?php endforeach; ?>
				</select>
				<p class="description"><?php esc_html_e( 'Select the default post type for converted documents.', 'docxtowp' ); ?></p>
			</td>
		</tr>
		<tr>
			<th><label for="dtpost_content_format"><?php esc_html_e( 'Content Format', 'docxtowp' ); ?></label></th>
			<td>
				<select id="dtpost_content_format" name="dtpost_content_format">
					<option value="auto" <?php selected( 'auto', $resolved_format ); ?>>
						<?php esc_html_e( 'Match the editor (recommended)', 'docxtowp' ); ?>
					</option>
					<option value="blocks" <?php selected( 'blocks', $resolved_format ); ?>>
						<?php esc_html_e( 'Always block editor', 'docxtowp' ); ?>
					</option>
					<option value="classic" <?php selected( 'classic', $resolved_format ); ?>>
						<?php esc_html_e( 'Always classic HTML', 'docxtowp' ); ?>
					</option>
				</select>
				<p class="description">
					<?php esc_html_e( 'Block editor output creates real paragraph, heading, list, image and table blocks you can edit individually. Classic HTML puts the whole document in one block. "Match the editor" picks whichever the post type already uses.', 'docxtowp' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><?php esc_html_e( 'Video Embeds', 'docxtowp' ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="dtpost_convert_embeds" value="1"
						<?php checked( 1, get_option( 'dtpost_convert_embeds', 1 ) ); ?>>
					<?php esc_html_e( 'Turn YouTube and Vimeo links into embedded players', 'docxtowp' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'A YouTube or Vimeo link on a line of its own becomes a playable video. A link inside a sentence stays a link, because turning "watch the demo" into a video player mid-paragraph would break the sentence around it.', 'docxtowp' ); ?>
				</p>
				<p class="description">
					<?php esc_html_e( 'Video frames copied out of YouTube or Vimeo are converted the same way. Frames from anywhere else are removed rather than trusted: WordPress strips them before saving in any case, and a document should not be able to put arbitrary third-party code on your site.', 'docxtowp' ); ?>
				</p>
			</td>
		</tr>

		<tr>
			<th><label for="dtpost_filename_title_case"><?php esc_html_e( 'Title From Filename', 'docxtowp' ); ?></label></th>
			<td>
				<?php $dtpost_title_case = DTPost_Title::mode(); ?>
				<select id="dtpost_filename_title_case" name="dtpost_filename_title_case">
					<option value="<?php echo esc_attr( DTPost_Title::SENTENCE ); ?>" <?php selected( $dtpost_title_case, DTPost_Title::SENTENCE ); ?>>
						<?php esc_html_e( 'Sentence case. Annual report for the board', 'docxtowp' ); ?>
					</option>
					<option value="<?php echo esc_attr( DTPost_Title::TITLE ); ?>" <?php selected( $dtpost_title_case, DTPost_Title::TITLE ); ?>>
						<?php esc_html_e( 'Title Case. Annual Report for the Board', 'docxtowp' ); ?>
					</option>
					<option value="<?php echo esc_attr( DTPost_Title::RAW ); ?>" <?php selected( $dtpost_title_case, DTPost_Title::RAW ); ?>>
						<?php esc_html_e( 'Leave as written, annual report for the board', 'docxtowp' ); ?>
					</option>
				</select>
				<p class="description">
					<?php esc_html_e( 'Only used when a document has no heading to take a title from. A document with a Heading 1 or 2 always keeps that heading exactly as you wrote it. Words you typed in capitals, such as NHS, are never lowercased.', 'docxtowp' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<th><label for="dtpost_default_category"><?php esc_html_e( 'Default Category', 'docxtowp' ); ?></label></th>
			<td>
				<select id="dtpost_default_category" name="dtpost_default_category">
					<?php foreach ( $categories as $cat ) : ?>
					<option value="<?php echo esc_attr( $cat->term_id ); ?>" <?php if ( (int) $cat->term_id === (int) $resolved_default_cat ) echo 'selected="selected"'; ?>>
						<?php echo esc_html( $cat->name ); ?>
					</option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th><label for="dtpost_default_status"><?php esc_html_e( 'Default Status', 'docxtowp' ); ?></label></th>
			<td>
				<select id="dtpost_default_status" name="dtpost_default_status">
					<?php foreach ( $status_options as $value => $label ) : ?>
					<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $resolved_default_status, $value ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
	</table>
</div>

<div class="dtpost-settings-section">
	<h2><?php esc_html_e( 'Permissions', 'docxtowp' ); ?></h2>
	<p class="description" style="margin-bottom:12px"><?php esc_html_e( 'Select which roles can use this plugin. Administrators always have access.', 'docxtowp' ); ?></p>
	<table class="form-table">
		<tr>
			<th><?php esc_html_e( 'Allowed Roles', 'docxtowp' ); ?></th>
			<td>
				<?php foreach ( $all_roles as $slug => $name ) : ?>
				<label class="dtpost-checkbox-label" style="margin-bottom:4px;display:block;">
					<input type="checkbox" name="dtpost_allowed_roles[]" value="<?php echo esc_attr( $slug ); ?>"
						<?php checked( in_array( $slug, $allowed_roles, true ) ); ?>
						<?php disabled( 'administrator', $slug ); ?>>
					<?php echo esc_html( $name ); ?>
					<?php if ( 'administrator' === $slug ) echo '<em style="color:#8c8f94;font-size:12px">(' . esc_html__( 'always allowed', 'docxtowp' ) . ')</em>'; ?>
				</label>
				<?php endforeach; ?>
			</td>
		</tr>
	</table>
</div>

<?php submit_button( __( 'Save Settings', 'docxtowp' ) ); ?>
</form>

<div class="dtpost-settings-section dtpost-danger-section">
	<h2><?php esc_html_e( 'Maintenance', 'docxtowp' ); ?></h2>
	<p class="description"><?php esc_html_e( 'Remove all files in the temporary upload directory.', 'docxtowp' ); ?></p>
	<button type="button" id="dtpost-clear-temp" class="button dtpost-btn-danger"><?php esc_html_e( 'Clear Temp Files', 'docxtowp' ); ?></button>
</div>
</div>
