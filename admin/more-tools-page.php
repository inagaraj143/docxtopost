<?php
/**
 * Admin page: other plugins from the same developer.
 *
 * Each card shows the plugin's real state on this site: Active, an Activate
 * link when it is installed, WordPress's own install dialog for plugins in the
 * WordPress.org directory, and a plain link out for paid ones. Nothing is
 * downloaded or installed from here except through WordPress's own installer.
 *
 * @package DocxToPost
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'get_plugins' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

$utm = array(
	'utm_source'   => 'docxtopost-plugin',
	'utm_medium'   => 'more-tools',
	'utm_campaign' => 'cross-promo',
);

/*
 * `files` lists every plugin file that counts as this product being installed.
 * DocxToWP Pro can live in either folder depending on how it was uploaded. */
$products = array(
	array(
		'name'   => 'DocxToWP Pro',
		'badge'  => 'pro',
		'icon'   => 'dashicons-media-document',
		'lead'   => __( 'This plugin, for a folder of documents rather than one at a time.', 'docxtowp' ),
		'body'   => __( 'Bulk import of up to 100 .docx and .md files, drip publishing on a schedule, Yoast and Rank Math SEO fields from templates, WebP image conversion, duplicate handling and one-click rollback of a whole import.', 'docxtowp' ),
		'files'  => array( 'docxtowp/docxtowp-pro.php', 'docxtowp-pro/docxtowp-pro.php' ),
		'url'    => dtpost_pro_url( 'more-tools' ),
		'slug'   => '',
		'swap'   => true,
	),
	array(
		'name'   => 'BackupScope',
		'badge'  => 'free',
		'icon'   => 'dashicons-backup',
		'lead'   => __( 'WordPress backups you can see inside before you need them.', 'docxtowp' ),
		'body'   => __( 'One-click backup of your files and database into a single ZIP, checked after it is made. A scan first shows what will be included and how big it is, and wp-config.php with your security keys is never put in a backup.', 'docxtowp' ),
		'files'  => array( 'backupscope/backupscope.php' ),
		'url'    => 'https://wordpress.org/plugins/backupscope/',
		'slug'   => 'backupscope',
		'swap'   => false,
	),
	array(
		'name'   => 'BackupScope Storage Insights',
		'badge'  => 'free',
		'icon'   => 'dashicons-chart-pie',
		'lead'   => __( 'See what uses your WordPress disk space.', 'docxtowp' ),
		'body'   => __( 'Media, plugins, themes, folders, database tables and the largest files, measured and shown in one report. Read-only: nothing on your site is changed or deleted.', 'docxtowp' ),
		'files'  => array( 'backupscope-storage-insights/backupscope-storage-insights.php' ),
		'url'    => 'https://wordpress.org/plugins/backupscope-storage-insights/',
		'slug'   => 'backupscope-storage-insights',
		'swap'   => false,
	),
	array(
		'name'   => 'BackupScope Pro',
		'badge'  => 'pro',
		'icon'   => 'dashicons-shield',
		'lead'   => __( 'Backups that look after themselves.', 'docxtowp' ),
		'body'   => __( 'Scheduled backups, off-site copies to Amazon S3, Cloudflare R2, Backblaze B2 or Wasabi, restore from the dashboard with a safety backup first, email notifications, and storage analytics that show what is filling your disk.', 'docxtowp' ),
		'files'  => array( 'backupscope-pro/backupscope-pro.php' ),
		'url'    => add_query_arg( $utm, 'https://backupscope.pro/pricing' ),
		'slug'   => '',
		'swap'   => false,
	),
);

$installed = get_plugins();
?>
<div class="wrap dtpost-upgrade-wrap dtpost-more-tools-wrap">

	<div class="dtpost-page-title">
		<h1><?php esc_html_e( 'More Tools', 'docxtowp' ); ?></h1>
		<?php dtpost_header_actions( 'header-more-tools' ); ?>
	</div>

	<p class="dtpost-upgrade__standfirst">
		<?php esc_html_e( 'Other WordPress plugins from the developer of DocxToPost.', 'docxtowp' ); ?>
	</p>

	<div class="dtpost-tools">
		<?php
		foreach ( $products as $product ) :
			$file = '';
			foreach ( $product['files'] as $candidate ) {
				if ( isset( $installed[ $candidate ] ) ) {
					$file = $candidate;
					break;
				}
			}
			$is_free = 'free' === $product['badge'];
			?>
		<section class="dtpost-tool">
			<div class="dtpost-tool__head">
				<span class="dashicons <?php echo esc_attr( $product['icon'] ); ?>" aria-hidden="true"></span>
				<div>
					<h2>
						<?php echo esc_html( $product['name'] ); ?>
						<span class="dtpost-tool__badge<?php echo $is_free ? ' dtpost-tool__badge--free' : ''; ?>">
							<?php echo $is_free ? esc_html__( 'Free', 'docxtowp' ) : esc_html__( 'Pro', 'docxtowp' ); ?>
						</span>
					</h2>
					<p class="dtpost-tool__lead"><?php echo esc_html( $product['lead'] ); ?></p>
				</div>
			</div>

			<p class="dtpost-tool__body"><?php echo esc_html( $product['body'] ); ?></p>

			<div class="dtpost-tool__actions">
				<?php if ( '' !== $file && is_plugin_active( $file ) ) : ?>
					<span class="dtpost-tool__active">&#10003; <?php esc_html_e( 'Active', 'docxtowp' ); ?></span>

				<?php elseif ( '' !== $file && $product['swap'] ) : ?>
					<?php
					// DocxToWP Pro shares this plugin's admin menu, so it is not
					// activated from here while this one is running: the steps
					// on the Upgrade screen swap the two in the right order.
					?>
					<span class="dtpost-tool__active dtpost-tool__active--installed"><?php esc_html_e( 'Installed', 'docxtowp' ); ?></span>
					<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=dtpost-upgrade' ) ); ?>"><?php esc_html_e( 'Activation steps', 'docxtowp' ); ?></a>

				<?php elseif ( '' !== $file && current_user_can( 'activate_plugins' ) ) : ?>
					<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( admin_url( 'plugins.php?action=activate&plugin=' . rawurlencode( $file ) ), 'activate-plugin_' . $file ) ); ?>"><?php esc_html_e( 'Activate', 'docxtowp' ); ?></a>

				<?php elseif ( '' !== $product['slug'] && current_user_can( 'install_plugins' ) ) : ?>
					<a class="button button-primary thickbox open-plugin-details-modal"
						href="<?php echo esc_url( admin_url( 'plugin-install.php?tab=plugin-information&plugin=' . rawurlencode( $product['slug'] ) . '&TB_iframe=true&width=772&height=700' ) ); ?>"
						aria-label="<?php echo esc_attr( sprintf( /* translators: %s: plugin name */ __( 'Install %s', 'docxtowp' ), $product['name'] ) ); ?>">
						<?php esc_html_e( 'Install', 'docxtowp' ); ?>
					</a>
					<a class="button" href="<?php echo esc_url( $product['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Details', 'docxtowp' ); ?></a>

				<?php else : ?>
					<a class="button<?php echo $is_free ? '' : ' button-primary'; ?>" href="<?php echo esc_url( $product['url'] ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Learn more', 'docxtowp' ); ?></a>
				<?php endif; ?>
			</div>
		</section>
		<?php endforeach; ?>
	</div>

</div>
