<?php
/**
 * Admin page: Preview + Publish, single step, WP post-editor style.
 * Supports all public post types and standard post statuses.
 *
 * @package DocxToPost
 */

defined( 'ABSPATH' ) || exit;

/* ── Success screen ─────────────────────────────────────────────────────── */
if ( isset( $_GET['dtpost_success'], $_GET['post_id'] ) ) {
	$post_id  = absint( $_GET['post_id'] );
	$post     = get_post( $post_id );
	$status   = $post ? get_post_status_object( $post->post_status ) : null;
	$pt_obj   = $post ? get_post_type_object( $post->post_type ) : null;

	// The heading has to match what actually happened. It said "Published
	// successfully!" for every outcome, including a draft, directly above a
	// chip reading "Draft".
	$type_label = $pt_obj ? $pt_obj->labels->singular_name : __( 'Post', 'docxtowp' );
	switch ( $post ? $post->post_status : '' ) {
		case 'publish':
			/* translators: %s: post type name, e.g. Post or Page */
			$headline = sprintf( __( '%s published', 'docxtowp' ), $type_label );
			break;
		case 'private':
			/* translators: %s: post type name, e.g. Post or Page */
			$headline = sprintf( __( '%s published privately', 'docxtowp' ), $type_label );
			break;
		case 'pending':
			$headline = __( 'Submitted for review', 'docxtowp' );
			break;
		case 'future':
			$headline = __( 'Scheduled', 'docxtowp' );
			break;
		case 'draft':
			$headline = __( 'Saved as a draft', 'docxtowp' );
			break;
		default:
			/* translators: %s: post type name, e.g. Post or Page */
			$headline = sprintf( __( '%s created', 'docxtowp' ), $type_label );
	}
	?>
	<div class="wrap">
		<div class="dtpost-success-wrap">
			<div class="dtpost-success-card">
				<div class="dtpost-success-icon">
					<svg width="48" height="48" viewBox="0 0 48 48" fill="none"><circle cx="24" cy="24" r="24" fill="#00a32a" opacity=".12"/><circle cx="24" cy="24" r="18" fill="#00a32a" opacity=".2"/><path d="M15 24.5l6.5 6.5L33 18" stroke="#00a32a" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
				</div>
				<h2><?php echo esc_html( $headline ); ?></h2>
				<p class="dtpost-success-title"><?php echo $post ? esc_html( get_the_title( $post ) ) : esc_html__( 'Post created', 'docxtowp' ); ?></p>
				<?php if ( $status ) : ?>
				<div class="dtpost-success-meta">
					<span class="dtpost-chip dtpost-chip-blue"><?php echo $pt_obj ? esc_html( $pt_obj->labels->singular_name ) : esc_html__( 'Post', 'docxtowp' ); ?></span>
					<span class="dtpost-chip dtpost-chip-<?php echo esc_attr( $post->post_status ); ?>"><?php echo esc_html( $status->label ); ?></span>
				</div>
				<?php endif; ?>
				<div class="dtpost-success-actions">
					<?php if ( $post && 'publish' === $post->post_status ) : ?>
					<a href="<?php echo esc_url( get_permalink( $post_id ) ); ?>" target="_blank" class="dtpost-btn dtpost-btn-primary">
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
						<?php esc_html_e( 'View', 'docxtowp' ); ?>
					</a>
					<?php elseif ( $post ) : ?>
					<?php // Nothing here previously, so a draft had no way to see the result. ?>
					<a href="<?php echo esc_url( get_preview_post_link( $post_id ) ); ?>" target="_blank" class="dtpost-btn dtpost-btn-primary">
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
						<?php esc_html_e( 'Preview', 'docxtowp' ); ?>
					</a>
					<?php endif; ?>
					<a href="<?php echo esc_url( (string) get_edit_post_link( $post_id ) ); ?>" class="dtpost-btn dtpost-btn-secondary">
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
						<?php esc_html_e( 'Edit in WordPress', 'docxtowp' ); ?>
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=docxtowp' ) ); ?>" class="dtpost-btn dtpost-btn-ghost">
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
						<?php esc_html_e( 'Upload Another', 'docxtowp' ); ?>
					</a>
				</div>
			</div>

			<?php if ( $post && current_user_can( 'delete_post', $post_id ) ) : ?>
			<div class="dtpost-followup dtpost-followup--undo">
				<span class="dtpost-followup__icon dtpost-followup__icon--danger" aria-hidden="true">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>
				</span>
				<div class="dtpost-followup__body">
					<strong><?php esc_html_e( 'Not what you expected?', 'docxtowp' ); ?></strong>
					<span><?php esc_html_e( 'Move it to the trash. You can restore it from there afterwards.', 'docxtowp' ); ?></span>
				</div>
				<button type="button" class="dtpost-btn dtpost-btn-danger" data-dtpost-trash="<?php echo esc_attr( $post_id ); ?>">
					<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/></svg>
					<?php esc_html_e( 'Move to trash', 'docxtowp' ); ?>
				</button>
			</div>
			<?php endif; ?>

			<div class="dtpost-followup dtpost-followup--pro">
				<span class="dtpost-followup__icon dtpost-followup__icon--pro" aria-hidden="true">
					<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg>
				</span>
				<div class="dtpost-followup__body">
					<strong><?php esc_html_e( 'Got a folder of these?', 'docxtowp' ); ?></strong>
					<span><?php esc_html_e( 'Pro imports up to 100 documents in one pass and can drip-publish them on a schedule.', 'docxtowp' ); ?></span>
				</div>
				<a class="dtpost-btn dtpost-btn-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=dtpost-bulk' ) ); ?>">
					<?php esc_html_e( 'See how', 'docxtowp' ); ?>
				</a>
			</div>

		</div>
	</div>
	<?php
	return;
}

/* ── No session ──────────────────────────────────────────────────────────── */
$session_key = 'dtpost_session_' . get_current_user_id();
$session     = get_transient( $session_key );

if ( ! $session ) {
	?>
	<div class="wrap"><h1><?php esc_html_e( 'DocxToPost', 'docxtowp' ); ?></h1>
	<div class="notice notice-warning inline"><p>
		<?php esc_html_e( 'No document session found.', 'docxtowp' ); ?>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=docxtowp' ) ); ?>"><?php esc_html_e( '← Upload a document first', 'docxtowp' ); ?></a>
	</p></div></div>
	<?php
	return;
}

/* ── Data ────────────────────────────────────────────────────────────────── */
$post_title        = $session['title'];
$content           = $session['content'];
$featured_image_id = absint( $session['featured_image_id'] );
$original_name     = $session['original_name'];
$current_user_id   = get_current_user_id();

// Default status.
$allowed_statuses = array( 'publish', 'draft', 'private', 'pending' );
$default_status   = (string) get_option( 'dtpost_default_status', 'draft' );
if ( ! in_array( $default_status, $allowed_statuses, true ) ) {
	$default_status = 'draft';
}

// Default post type.
$public_post_types  = get_post_types( array( 'public' => true ), 'objects' );
$default_post_type  = (string) get_option( 'dtpost_default_post_type', 'post' );
if ( ! array_key_exists( $default_post_type, $public_post_types ) ) {
	$default_post_type = 'post';
}

$default_cat = (int) get_option( 'dtpost_default_category', (int) get_option( 'default_category', 1 ) );
$categories  = get_categories( array( 'hide_empty' => false, 'orderby' => 'name' ) );
$authors     = get_users( array( 'capability' => 'publish_posts', 'fields' => array( 'ID', 'display_name' ) ) );

// Validate that the default category actually exists.
$cat_exists = false;
foreach ( $categories as $cat ) {
	if ( (int) $cat->term_id === $default_cat ) {
		$cat_exists = true;
		break;
	}
}
if ( ! $cat_exists && ! empty( $categories ) ) {
	$default_cat = (int) $categories[0]->term_id;
}

$status_options = array(
	'publish' => __( 'Published', 'docxtowp' ),
	'draft'   => __( 'Draft', 'docxtowp' ),
	'pending' => __( 'Pending Review', 'docxtowp' ),
	'private' => __( 'Private', 'docxtowp' ),
);
?>
<div class="wrap dtpost-editor-wrap">

	<!-- ── Page header ───────────────────────────────────────────────────── -->
	<div class="dtpost-page-header">
		<div class="dtpost-page-header__left">
			<h1><?php esc_html_e( 'Preview &amp; Publish', 'docxtowp' ); ?></h1>
			<span class="dtpost-file-pill">
				<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
				<?php echo esc_html( $original_name ); ?>
			</span>
		</div>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=docxtowp' ) ); ?>" class="dtpost-btn dtpost-btn-ghost dtpost-btn-sm">
			<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
			<?php esc_html_e( 'Upload New', 'docxtowp' ); ?>
		</a>
	</div>

	<?php
	// Warn about an existing post with this title. Advisory only, importing
	// the same document twice is a legitimate thing to do, so this never
	// blocks. Pro is what turns this into an actual skip/update policy.
	// Not get_page_by_title(), deprecated in WordPress 6.2.
	$duplicate_query = new WP_Query(
		array(
			'post_type'              => $default_post_type,
			'title'                  => $post_title,
			'post_status'            => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'posts_per_page'         => 1,
			'no_found_rows'          => true,
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		)
	);
	$duplicate = $duplicate_query->have_posts() ? $duplicate_query->posts[0] : null;

	if ( $duplicate instanceof WP_Post ) :
		?>
	<div class="notice notice-warning inline dtpost-duplicate-warning">
		<p>
			<?php esc_html_e( 'A post with this title already exists.', 'docxtowp' ); ?>
			<a href="<?php echo esc_url( (string) get_edit_post_link( $duplicate->ID ) ); ?>" target="_blank" rel="noopener noreferrer">
				<?php esc_html_e( 'View the existing one', 'docxtowp' ); ?>
			</a>
			<?php esc_html_e( '- publishing here creates a second post rather than replacing it.', 'docxtowp' ); ?>
		</p>
	</div>
	<?php endif; ?>

	<?php
	// Anything the parser had to leave out. Advisory, like the duplicate
	// notice above: the document still imports, this just says what is
	// missing from it so nobody finds out from the published page.
	$dtpost_warnings = array_filter( array_map( 'strval', (array) ( $session['warnings'] ?? array() ) ) );
	if ( ! empty( $dtpost_warnings ) ) :
		?>
	<div class="notice notice-warning inline dtpost-parse-warning">
		<p><strong><?php esc_html_e( 'Some of the document could not be imported.', 'docxtowp' ); ?></strong></p>
		<ul>
			<?php foreach ( $dtpost_warnings as $dtpost_warning ) : ?>
			<li><?php echo esc_html( $dtpost_warning ); ?></li>
			<?php endforeach; ?>
		</ul>
	</div>
	<?php endif; ?>

	<!-- Global error notice -->
	<div id="dtpost-notice" class="dtpost-notice dtpost-notice-error" style="display:none">
		<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
		<span id="dtpost-notice-msg"></span>
	</div>

	<!-- ── Two-column editor layout ──────────────────────────────────────── -->
	<div class="dtpost-layout">

		<!-- LEFT COLUMN -->
		<div class="dtpost-col-main">

			<!-- Title -->
			<div class="dtpost-panel dtpost-panel-title">
				<input
					type="text"
					id="dtpost-post-title"
					class="dtpost-title-field"
					value="<?php echo esc_attr( $post_title ); ?>"
					placeholder="<?php esc_attr_e( 'Add title', 'docxtowp' ); ?>"
					autocomplete="off"
				>
				<div class="dtpost-permalink-row">
					<span class="dtpost-permalink-label"><?php esc_html_e( 'Permalink:', 'docxtowp' ); ?></span>
					<span class="dtpost-permalink-base"><?php echo esc_url( trailingslashit( home_url() ) ); ?></span><span id="dtpost-slug-display" class="dtpost-permalink-slug"><?php echo esc_html( sanitize_title( $post_title ) ); ?></span><span class="dtpost-permalink-base">/</span>
					<a href="#" id="dtpost-slug-edit-btn" class="dtpost-permalink-edit"><?php esc_html_e( 'Edit', 'docxtowp' ); ?></a>
					<span id="dtpost-slug-edit-wrap" class="dtpost-slug-edit-wrap" style="display:none">
						<input type="text" id="dtpost-post-slug" class="dtpost-slug-input" value="<?php echo esc_attr( sanitize_title( $post_title ) ); ?>">
						<button type="button" id="dtpost-slug-ok" class="button button-small"><?php esc_html_e( 'OK', 'docxtowp' ); ?></button>
						<button type="button" id="dtpost-slug-cancel" class="button button-small"><?php esc_html_e( 'Cancel', 'docxtowp' ); ?></button>
					</span>
				</div>
			</div>

			<!-- TinyMCE content editor -->
			<div class="dtpost-panel dtpost-panel-editor" style="overflow: hidden;">
				<div class="dtpost-panel-header">
					<span class="dtpost-panel-title-text">
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="margin-right:4px;"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="16" y1="13" x2="8" y2="13"></line><line x1="16" y1="17" x2="8" y2="17"></line><polyline points="10 9 9 9 8 9"></polyline></svg>
						<?php esc_html_e( 'Document Content', 'docxtowp' ); ?>
					</span>
					<span class="dtpost-panel-hint"><?php esc_html_e( 'Edit or refine the parsed text here', 'docxtowp' ); ?></span>
				</div>
				<?php
				wp_editor( $content, 'dtpost_content_editor', array(
					'textarea_name' => 'dtpost_content_editor',
					'textarea_rows' => 28,
					'media_buttons' => true,
					'teeny'         => false,
					'quicktags'     => true,
					'tinymce'       => array(
						'toolbar1' => 'formatselect,bold,italic,underline,bullist,numlist,blockquote,hr,alignleft,aligncenter,alignright,link,unlink,wp_more,spellchecker,dfw,wp_adv',
						'toolbar2' => 'strikethrough,forecolor,pastetext,removeformat,charmap,outdent,indent,undo,redo',
					),
				) );
				?>
			</div>

			<!-- Excerpt -->
			<div class="dtpost-panel">
				<div class="dtpost-panel-header">
					<span class="dtpost-panel-title-text"><?php esc_html_e( 'Excerpt', 'docxtowp' ); ?></span>
					<span class="dtpost-panel-hint"><?php esc_html_e( 'Optional, shown in search results and archives', 'docxtowp' ); ?></span>
				</div>
				<textarea id="dtpost-excerpt" rows="3" class="dtpost-textarea"
					placeholder="<?php esc_attr_e( 'Write a brief summary of this post…', 'docxtowp' ); ?>"></textarea>
			</div>

		</div><!-- /.dtpost-col-main -->

		<!-- RIGHT SIDEBAR -->
		<div class="dtpost-col-sidebar">

			<!-- PUBLISH PANEL -->
			<div class="dtpost-panel dtpost-panel-publish" id="dtpost-publish-panel">
				<div class="dtpost-panel-header dtpost-panel-header-publish">
					<span class="dtpost-panel-title-text"><?php esc_html_e( 'Publish', 'docxtowp' ); ?></span>
				</div>

				<div class="dtpost-pub-body">

					<!-- Post Type, dynamic dropdown of all public post types -->
					<div class="dtpost-meta-row">
						<div class="dtpost-meta-icon">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
						</div>
						<label class="dtpost-meta-label" for="dtpost-post-type"><?php esc_html_e( 'Post Type', 'docxtowp' ); ?></label>
						<select id="dtpost-post-type" class="dtpost-meta-select">
							<?php foreach ( $public_post_types as $slug => $obj ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $slug, $default_post_type ); ?>>
								<?php echo esc_html( $obj->labels->singular_name ); ?>
							</option>
							<?php endforeach; ?>
						</select>
					</div>

					<!-- Status, all standard statuses -->
					<div class="dtpost-meta-row">
						<div class="dtpost-meta-icon">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
						</div>
						<label class="dtpost-meta-label" for="dtpost-post-status"><?php esc_html_e( 'Status', 'docxtowp' ); ?></label>
						<select id="dtpost-post-status" class="dtpost-meta-select">
							<?php foreach ( $status_options as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $default_status, $value ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>

					<!-- Author -->
					<div class="dtpost-meta-row">
						<div class="dtpost-meta-icon">
							<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
						</div>
						<label class="dtpost-meta-label" for="dtpost-post-author"><?php esc_html_e( 'Author', 'docxtowp' ); ?></label>
						<select id="dtpost-post-author" class="dtpost-meta-select">
							<?php foreach ( $authors as $a ) : ?>
							<option value="<?php echo esc_attr( $a->ID ); ?>" <?php selected( $a->ID, $current_user_id ); ?>>
								<?php echo esc_html( $a->display_name ); ?>
							</option>
							<?php endforeach; ?>
						</select>
					</div>

				</div><!-- /.dtpost-pub-body -->

				<div class="dtpost-pub-footer">
					<button type="button" id="dtpost-save-draft" class="dtpost-btn dtpost-btn-ghost dtpost-btn-full">
						<?php esc_html_e( 'Save Draft', 'docxtowp' ); ?>
					</button>
					<button type="button" id="dtpost-publish-btn" class="dtpost-btn dtpost-btn-primary dtpost-btn-full">
						<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
						<span id="dtpost-pub-label"><?php esc_html_e( 'Publish', 'docxtowp' ); ?></span>
					</button>
				</div>
			</div>

			<!-- FEATURED IMAGE -->
			<div class="dtpost-panel">
				<div class="dtpost-panel-header">
					<span class="dtpost-panel-title-text"><?php esc_html_e( 'Featured Image', 'docxtowp' ); ?></span>
				</div>
				<?php if ( $featured_image_id ) : ?>
				<div class="dtpost-feat-img-wrap">
					<?php echo wp_get_attachment_image( $featured_image_id, 'medium', false, array( 'class' => 'dtpost-feat-img' ) ); ?>
					<p class="dtpost-feat-name"><?php echo esc_html( get_the_title( $featured_image_id ) ); ?></p>
				</div>
				<?php else : ?>
				<p class="dtpost-hint-text"><?php esc_html_e( 'No image uploaded. Re-upload the document to attach a featured image.', 'docxtowp' ); ?></p>
				<?php endif; ?>
			</div>

			<!-- CATEGORIES -->
			<div class="dtpost-panel" id="dtpost-cat-panel">
				<div class="dtpost-panel-header">
					<span class="dtpost-panel-title-text"><?php esc_html_e( 'Categories', 'docxtowp' ); ?></span>
				</div>
				<div class="dtpost-cat-list">
					<?php foreach ( $categories as $cat ) : ?>
					<label class="dtpost-cat-row">
						<input type="checkbox" name="dtpost_category[]" value="<?php echo esc_attr( $cat->term_id ); ?>" <?php if ( (int) $cat->term_id === (int) $default_cat ) echo 'checked="checked"'; ?>>
						<span class="dtpost-cat-name"><?php echo esc_html( $cat->name ); ?></span>
						<span class="dtpost-cat-count"><?php echo absint( $cat->count ); ?></span>
					</label>
					<?php endforeach; ?>
				</div>
			</div>

			<!-- TAGS -->
			<div class="dtpost-panel" id="dtpost-tag-panel">
				<div class="dtpost-panel-header">
					<span class="dtpost-panel-title-text"><?php esc_html_e( 'Tags', 'docxtowp' ); ?></span>
				</div>
				<div class="dtpost-tag-input-box" id="dtpost-tag-wrap">
					<div id="dtpost-tag-pills" class="dtpost-tag-pills"></div>
					<input type="text" id="dtpost-tag-input" class="dtpost-tag-text"
						placeholder="<?php esc_attr_e( 'Add tag, press Enter…', 'docxtowp' ); ?>" autocomplete="off">
					<ul id="dtpost-tag-suggest" class="dtpost-tag-suggest" style="display:none"></ul>
				</div>
				<input type="hidden" id="dtpost-tags-hidden">
				<p class="dtpost-hint-text" style="margin-top:6px"><?php esc_html_e( 'Separate with Enter or commas', 'docxtowp' ); ?></p>
			</div>

		</div><!-- /.dtpost-col-sidebar -->
	</div><!-- /.dtpost-layout -->
</div><!-- /.wrap -->

<!-- Confirm Modal -->
<div id="dtpost-modal" class="dtpost-modal-backdrop" style="display:none">
	<div class="dtpost-modal-card">
		<div class="dtpost-modal-header">
			<h3 id="dtpost-modal-title"><?php esc_html_e( 'Confirm', 'docxtowp' ); ?></h3>
			<button type="button" id="dtpost-modal-x" class="dtpost-modal-close">
				<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
			</button>
		</div>
		<div id="dtpost-modal-body" class="dtpost-modal-body"></div>
		<div class="dtpost-modal-footer">
			<button id="dtpost-modal-ok" class="dtpost-btn dtpost-btn-primary"><?php esc_html_e( 'Confirm', 'docxtowp' ); ?></button>
			<button id="dtpost-modal-cancel" class="dtpost-btn dtpost-btn-ghost"><?php esc_html_e( 'Cancel', 'docxtowp' ); ?></button>
		</div>
	</div>
</div>
