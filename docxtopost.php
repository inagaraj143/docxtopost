<?php
/**
 * Plugin Name:       DocxToPost – Convert DOCX Files to WP Posts, Pages & Custom Post Types
 * Plugin URI:        https://docxtowp.com
 * Description:       Convert .docx files into WordPress posts with preserved formatting. Upload, preview, and publish — no copy-paste needed.
 * Version:           1.1.1
 * Author:            Nagaraj
 * Author URI:        https://twitter.com/Nagaraj_Dev143
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       docxtowp
 * Domain Path:       /languages
 * Requires PHP:      8.0
 * Requires at least: 6.0
 * Tested up to:      7.1
 */

if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

// ── Conflict Check: Ensure only one version of DocxToPost is active ────────────
if (defined('DTPOST_VERSION')) {
	add_action(
		'admin_notices',
		function () {
			echo '<div class="notice notice-error is-dismissible"><p><strong>DocxToPost Conflict:</strong> Another version of DocxToPost (likely the Pro version) is already active. Please deactivate it before activating the Free version to avoid conflicts.</p></div>';
		}
	);
	return;
}

define('DTPOST_VERSION', '1.1.1');
define('DTPOST_PLUGIN_FILE', __FILE__);
define('DTPOST_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DTPOST_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Get the plugin's temp directory path using wp_upload_dir().
 *
 * @return string Absolute path to the dtpost-temp directory.
 */
function dtpost_get_temp_dir(): string
{
	$upload_dir = wp_upload_dir();
	return trailingslashit($upload_dir['basedir']) . 'dtpost-temp/';
}

/**
 * Get the plugin's temp directory URL using wp_upload_dir().
 *
 * @return string URL to the dtpost-temp directory.
 */
function dtpost_get_temp_url(): string
{
	$upload_dir = wp_upload_dir();
	return trailingslashit($upload_dir['baseurl']) . 'dtpost-temp/';
}

// Autoload Composer dependencies.
if (file_exists(DTPOST_PLUGIN_DIR . 'vendor/autoload.php')) {
	require_once DTPOST_PLUGIN_DIR . 'vendor/autoload.php';
}

// Core includes.
require_once DTPOST_PLUGIN_DIR . 'includes/class-dtpost-permissions.php';
require_once DTPOST_PLUGIN_DIR . 'includes/class-dtpost-image.php';
require_once DTPOST_PLUGIN_DIR . 'includes/class-dtpost-parser.php';
require_once DTPOST_PLUGIN_DIR . 'includes/class-dtpost-blocks.php';
require_once DTPOST_PLUGIN_DIR . 'includes/class-dtpost-publisher.php';

// Activation / deactivation / uninstall.
register_activation_hook(DTPOST_PLUGIN_FILE, 'dtpost_activate');
register_deactivation_hook(DTPOST_PLUGIN_FILE, 'dtpost_deactivate');

/**
 * Plugin activation: create temp dir and default options.
 */
function dtpost_activate(): void
{
	$temp_dir = dtpost_get_temp_dir();

	if (!file_exists($temp_dir)) {
		wp_mkdir_p($temp_dir);
		// Protect the temp directory from direct access.
		file_put_contents($temp_dir . '.htaccess', 'deny from all'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		file_put_contents($temp_dir . 'index.php', '<?php // Silence is golden.'); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
	}

	$defaults = array(
		'dtpost_default_post_type' => 'post',
		'dtpost_default_category' => 1,
		'dtpost_default_status' => 'draft',
		'dtpost_max_upload_mb' => 10,
		'dtpost_allowed_roles' => array('administrator', 'editor'),
		'dtpost_content_format' => 'auto',
	);

	foreach ($defaults as $key => $value) {
		if (false === get_option($key)) {
			add_option($key, $value);
		}
	}

	// Schedule the temp-file cleanup here rather than on every page load.
	if (!wp_next_scheduled('dtpost_cleanup_cron')) {
		wp_schedule_event(time(), 'daily', 'dtpost_cleanup_cron');
	}
}

/**
 * Plugin deactivation: clean up temp files.
 */
function dtpost_deactivate(): void
{
	dtpost_clean_temp_dir();
	wp_clear_scheduled_hook('dtpost_cleanup_cron');
}

/**
 * Whether imported content should be written as block markup.
 *
 * 'auto' follows whatever editor the post type actually uses, which is the
 * right answer for both a stock site and one running Classic Editor. The two
 * explicit values exist because that detection cannot know about a site whose
 * authors have simply decided one way or the other.
 */
function dtpost_use_block_format(string $post_type): bool
{
	$format = (string) get_option('dtpost_content_format', 'auto');

	if ('blocks' === $format) {
		return true;
	}
	if ('classic' === $format) {
		return false;
	}

	if (!function_exists('use_block_editor_for_post_type')) {
		require_once ABSPATH . 'wp-admin/includes/post.php';
	}

	return function_exists('use_block_editor_for_post_type')
		? use_block_editor_for_post_type($post_type)
		: true;
}

/**
 * Builds a link to the Pro site, tagged with where in the plugin it was clicked.
 *
 * Without the campaign parameters there is no way to tell a sale that came
 * from the plugin from one that came from search, and therefore no way to
 * know which of these placements is worth keeping.
 *
 * @param string $placement Short slug for the spot the link sits in.
 */
function dtpost_pro_url(string $placement = 'plugin'): string
{
	return add_query_arg(
		array(
			'utm_source' => 'wporg-plugin',
			'utm_medium' => sanitize_key($placement),
			'utm_campaign' => 'free-to-pro',
		),
		'https://docxtowp.com/'
	);
}

/**
 * Renders the Pro feature card.
 *
 * This is an informational card on the plugin's own screens — it describes
 * what Pro does and links out. It deliberately does not render a disabled
 * copy of any Pro feature: shipping locked functionality is what the Plugin
 * Directory's guideline 5 prohibits, whereas advertising is allowed.
 *
 * The card carries no dismiss control because it is part of the page rather
 * than a notice interrupting it. The guideline 11 requirement to be
 * dismissible applies to admin notices and dashboard widgets — see
 * dtpost_admin_notices(), where it is honoured.
 *
 * @param string $placement Campaign slug for the CTA link.
 */
/**
 * The date the one-time purchase stops being sold, 23:59:59 UTC.
 *
 * Announced publicly on docxtowp.com, so this is a real deadline rather than
 * manufactured urgency. Do not move it. A date that slips quietly is the same
 * deceptive pattern as a countdown that restarts.
 */
define('DTPOST_LIFETIME_DEADLINE', '2026-09-30T23:59:59+00:00');

/**
 * Seconds until the deadline. Negative once it has passed.
 *
 * The gate uses this rather than a day count. ceil() on a small negative
 * fraction returns 0, not -1, so "days left" is still zero for the first
 * 24 hours AFTER the deadline, and a day-based check kept the notice up for
 * a whole extra day advertising an offer that had closed.
 */
function dtpost_lifetime_seconds_left(): int
{
    return strtotime(DTPOST_LIFETIME_DEADLINE) - time();
}

/**
 * Whole days remaining, for display only. Never used to decide visibility.
 */
function dtpost_lifetime_days_left(): int
{
    return (int) ceil(dtpost_lifetime_seconds_left() / DAY_IN_SECONDS);
}

/**
 * Tells free users that the one-time purchase is ending.
 *
 * ── Why this is safe to ship in a frozen release ──────────────────────────
 *
 * A plugin release sits on someone's site until they update, and directory
 * updates take days or weeks to propagate. Plenty of installs never update at
 * all. So a hardcoded "29 days left" would be wrong the day after release, and
 * even a hardcoded date would still be advertising a dead offer in December on
 * every site that stayed on this version.
 *
 * The day count is therefore computed from the server clock against a fixed
 * deadline, and the whole notice removes itself the moment that deadline
 * passes. No outbound request, nothing to maintain, and an install still
 * running this version in 2027 shows nothing at all.
 *
 * That self-expiry is the entire reason this belongs in a free plugin. Without
 * it, it would be an advertisement with no end date.
 *
 * ── Guideline 11 ──────────────────────────────────────────────────────────
 *
 * "Upgrade prompts, notices, alerts, and the like must be limited in scope and
 * used sparingly." So it is dismissible, the dismissal is remembered per user,
 * and it renders only on this plugin's own screens. It is never a site-wide
 * admin notice and never appears on the dashboard, where the user did not come
 * looking for this plugin.
 */
function dtpost_render_lifetime_notice(): void
{
    $remaining = dtpost_lifetime_seconds_left();

    // Past the deadline this is a no-op, on every install, forever.
    // Compared in seconds, not days: see dtpost_lifetime_seconds_left().
    if ($remaining <= 0) {
        return;
    }

    if (dtpost_notice_dismissed('lifetime-ending')) {
        return;
    }

    $when = wp_date('F j', strtotime(DTPOST_LIFETIME_DEADLINE));

    $days = dtpost_lifetime_days_left();

    if ($remaining <= DAY_IN_SECONDS) {
        $countdown = __('last day', 'docxtowp');
    } else {
        $countdown = sprintf(
            /* translators: %d: number of days remaining */
            _n('%d day left', '%d days left', $days, 'docxtowp'),
            $days
        );
    }
    ?>
    <div class="dtpost-deadline" data-dtpost-notice="lifetime-ending">
        <button type="button" class="dtpost-deadline__dismiss"
            aria-label="<?php esc_attr_e('Dismiss this notice', 'docxtowp'); ?>">&times;</button>

        <p class="dtpost-deadline__head">
            <?php
            printf(
                /* translators: 1: date such as "September 30", 2: countdown such as "29 days left" */
                esc_html__('Lifetime access to Pro ends %1$s (%2$s)', 'docxtowp'),
                esc_html($when),
                esc_html($countdown)
            );
            ?>
        </p>

        <p class="dtpost-deadline__body">
            <?php
            esc_html_e(
                'From October 1, DocxToWP Pro moves to annual licences. Buy before then and you keep lifetime access with free updates, forever. No renewals.',
                'docxtowp'
            );
            ?>
        </p>

        <p class="dtpost-deadline__foot">
            <a class="dtpost-deadline__link" href="<?php echo esc_url(dtpost_pro_url('deadline-notice')); ?>"
                target="_blank" rel="noopener noreferrer">
                <?php esc_html_e('See what Pro costs', 'docxtowp'); ?>
            </a>
            <span class="dtpost-deadline__note">
                <?php esc_html_e('This free plugin is not affected and stays free.', 'docxtowp'); ?>
            </span>
        </p>
    </div>
    <?php
}

function dtpost_render_pro_card(string $placement = 'upload-sidebar'): void
{
	// Three, matching the three pillars on the Upgrade page. Rollback used to
	// be a fourth here; it is a reassurance rather than a reason to buy, and
	// it belongs on the page that has room to explain it.
	$features = array(
		array(
			'title' => __('Bulk import', 'docxtowp'),
			'desc' => __('Up to 100 documents in one pass, with one-click rollback of the whole job.', 'docxtowp'),
		),
		array(
			'title' => __('Drip publishing', 'docxtowp'),
			'desc' => __('Schedule a whole batch — one post per weekday from Monday 09:00.', 'docxtowp'),
		),
		array(
			'title' => __('SEO automation', 'docxtowp'),
			'desc' => __('Titles, descriptions and focus keyphrases for Yoast and Rank Math, from templates.', 'docxtowp'),
		),
	);
	?>
	<div class="dtpost-pro-card">
		<div class="dtpost-pro-card__head">
			<span class="dtpost-pro-card__badge"><?php esc_html_e('Pro', 'docxtowp'); ?></span>
			<h3><?php esc_html_e('Importing more than one document?', 'docxtowp'); ?></h3>
		</div>

		<ul class="dtpost-pro-card__list">
			<?php foreach ($features as $feature) : ?>
			<li>
				<strong><?php echo esc_html($feature['title']); ?></strong>
				<span><?php echo esc_html($feature['desc']); ?></span>
			</li>
			<?php endforeach; ?>
		</ul>

		<a class="dtpost-pro-card__cta" href="<?php echo esc_url(dtpost_pro_url($placement)); ?>" target="_blank" rel="noopener noreferrer">
			<?php esc_html_e('See what Pro adds', 'docxtowp'); ?>
		</a>
	</div>
	<?php
}

/**
 * Records an exception in the PHP error log without showing it to the browser.
 *
 * The message, file path and line number are useful to whoever runs the site
 * and are an information disclosure to everyone else, so they go to the log
 * and the user gets a generic message.
 */
function dtpost_log_exception(\Throwable $e): void
{
	if (defined('WP_DEBUG') && WP_DEBUG) {
		error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			sprintf(
				'DocxToPost: %s in %s:%d',
				$e->getMessage(),
				$e->getFile(),
				$e->getLine()
			)
		);
	}
}

/**
 * Remove all files older than 24 hours from temp dir.
 */
function dtpost_clean_temp_dir(): void
{
	$temp_dir = dtpost_get_temp_dir();
	if (!is_dir($temp_dir)) {
		return;
	}
	$files = glob($temp_dir . '*');
	if (!$files) {
		return;
	}
	$cutoff = time() - DAY_IN_SECONDS;
	foreach ($files as $file) {
		if (is_file($file) && filemtime($file) < $cutoff) {
			wp_delete_file($file);
		}
	}
}

// Bootstrap admin.
add_action('plugins_loaded', 'dtpost_load_textdomain');
add_action('admin_menu', 'dtpost_register_admin_menu');
add_action('admin_enqueue_scripts', 'dtpost_enqueue_admin_assets');

/**
 * Load plugin text domain.
 */
function dtpost_load_textdomain(): void
{
	load_plugin_textdomain('docxtowp', false, dirname(plugin_basename(DTPOST_PLUGIN_FILE)) . '/languages');
}

// AJAX handlers.
add_action('wp_ajax_dtpost_upload_docx', 'dtpost_ajax_upload_docx');
add_action('wp_ajax_dtpost_publish_post', 'dtpost_ajax_publish_post');
add_action('wp_ajax_dtpost_tag_search', 'dtpost_ajax_tag_search');
add_action('wp_ajax_dtpost_clear_temp', 'dtpost_ajax_clear_temp');
add_action('wp_ajax_dtpost_dismiss_notice', 'dtpost_ajax_dismiss_notice');
add_action('wp_ajax_dtpost_trash_post', 'dtpost_ajax_trash_post');

/**
 * AJAX: move a just-imported post to the trash.
 *
 * The point is that a first import feels reversible. It trashes rather than
 * deletes, so the post is still recoverable from the Trash afterwards.
 */
function dtpost_ajax_trash_post(): void
{
	check_ajax_referer('dtpost_nonce', 'nonce');

	$post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
	if (!$post_id) {
		wp_send_json_error(array('message' => __('No post specified.', 'docxtowp')));
	}

	// delete_post is the capability WordPress itself checks for trashing, and
	// it is per-post, so this covers "someone else's post" as well as "wrong
	// role" without a second check.
	if (!current_user_can('delete_post', $post_id)) {
		wp_send_json_error(array('message' => __('You are not allowed to remove this post.', 'docxtowp')));
	}

	if (!wp_trash_post($post_id)) {
		wp_send_json_error(array('message' => __('Could not move the post to the trash.', 'docxtowp')));
	}

	// The conversion no longer stands, so it should not count toward the
	// thresholds that decide when to show a notice.
	$user_id = get_current_user_id();
	$count = dtpost_conversion_count();
	if ($count > 0) {
		update_user_meta($user_id, 'dtpost_conversion_count', $count - 1);
	}

	wp_send_json_success(array('redirect' => admin_url('admin.php?page=docxtowp')));
}

// Contextual notices on the plugin's own screens.
add_action('admin_notices', 'dtpost_admin_notices');

// Site Health test, so "why won't my document upload" is self-serve.
add_filter('site_status_tests', 'dtpost_register_site_health_test');

/**
 * Registers a Site Health test covering everything an import depends on.
 *
 * @param array<string,array<string,mixed>> $tests
 * @return array<string,array<string,mixed>>
 */
function dtpost_register_site_health_test(array $tests): array
{
	$tests['direct']['dtpost_requirements'] = array(
		'label' => __('DocxToPost can convert documents', 'docxtowp'),
		'test' => 'dtpost_site_health_check',
	);

	return $tests;
}

/**
 * Checks the three things an import actually needs, and reports what is wrong.
 *
 * @return array<string,mixed>
 */
function dtpost_site_health_check(): array
{
	$problems = array();

	if (!class_exists('ZipArchive')) {
		$problems[] = __('The ZipArchive PHP extension is not installed. A .docx file is a zip archive, so nothing can be read without it — ask your host to enable it.', 'docxtowp');
	}

	if (!class_exists('DOMDocument')) {
		$problems[] = __('The DOM PHP extension is not installed. Ask your host to enable it.', 'docxtowp');
	}

	$temp_dir = dtpost_get_temp_dir();
	$parent = dirname(untrailingslashit($temp_dir));
	if ((is_dir($temp_dir) && !wp_is_writable($temp_dir)) || (!is_dir($temp_dir) && !wp_is_writable($parent))) {
		$problems[] = sprintf(
			/* translators: %s: filesystem path */
			__('The upload directory is not writable (%s). Uploads will fail until it is.', 'docxtowp'),
			$temp_dir
		);
	}

	$setting_mb = (int) get_option('dtpost_max_upload_mb', 10);
	$server_bytes = wp_max_upload_size();
	if ($server_bytes > 0 && $setting_mb * MB_IN_BYTES > $server_bytes) {
		$problems[] = sprintf(
			/* translators: 1: plugin setting in MB, 2: server limit, formatted */
			__('Your file size limit is set to %1$dMB, but this server will not accept an upload larger than %2$s. The lower number is the one that applies — raising the setting alone will not help.', 'docxtowp'),
			$setting_mb,
			size_format($server_bytes)
		);
	}

	if (empty($problems)) {
		return array(
			'label' => __('DocxToPost can convert documents', 'docxtowp'),
			'status' => 'good',
			'badge' => array('label' => __('Performance', 'docxtowp'), 'color' => 'blue'),
			'description' => '<p>' . esc_html__('The extensions DocxToPost needs are installed, the upload directory is writable, and your file size limit is within what this server accepts.', 'docxtowp') . '</p>',
			'test' => 'dtpost_requirements',
		);
	}

	$list = '';
	foreach ($problems as $problem) {
		$list .= '<li>' . esc_html($problem) . '</li>';
	}

	return array(
		'label' => __('DocxToPost cannot convert documents yet', 'docxtowp'),
		'status' => 'critical',
		'badge' => array('label' => __('Performance', 'docxtowp'), 'color' => 'blue'),
		'description' => '<ul>' . $list . '</ul>',
		'test' => 'dtpost_requirements',
	);
}

// "Go Pro" link on the plugin's row on the Plugins screen.
add_filter('plugin_action_links_' . plugin_basename(DTPOST_PLUGIN_FILE), 'dtpost_plugin_action_links');

/**
 * Adds a Go Pro link to the plugin's row on the Plugins screen.
 *
 * @param array<int|string,string> $links Existing action links.
 * @return array<int|string,string>
 */
function dtpost_plugin_action_links(array $links): array
{
	$links[] = sprintf(
		'<a href="%s" target="_blank" rel="noopener noreferrer" style="color:#2271b1;font-weight:600">%s</a>',
		esc_url(dtpost_pro_url('plugins-row')),
		esc_html__('Go Pro', 'docxtowp')
	);

	return $links;
}

/**
 * The notices this plugin can show, in the order they are considered.
 *
 * Each is shown once, to one user, after a threshold of real use, and only on
 * this plugin's own screens. At most one appears at a time.
 *
 * @return array<string,int> notice key => documents converted before it shows
 */
function dtpost_notice_thresholds(): array
{
	// Ordered by threshold, highest first: the most-earned message wins.
	// Sorting the other way round would let someone who ignores the review
	// nudge at 5 never reach the drip message at 10, since an undismissed
	// notice simply reappears on the next page load.
	return array(
		'drip' => 10,
		'review' => 5,
		'bulk' => 3,
	);
}

/**
 * How many documents this user has converted.
 */
function dtpost_conversion_count(): int
{
	return (int) get_user_meta(get_current_user_id(), 'dtpost_conversion_count', true);
}

/**
 * Whether this user has dismissed a given notice.
 */
function dtpost_notice_dismissed(string $key): bool
{
	$dismissed = (array) get_user_meta(get_current_user_id(), 'dtpost_dismissed_notices', true);
	return in_array($key, $dismissed, true);
}

/**
 * AJAX: remember that this user has dismissed a notice.
 */
function dtpost_ajax_dismiss_notice(): void
{
	check_ajax_referer('dtpost_nonce', 'nonce');

	if (!is_user_logged_in()) {
		wp_send_json_error(array('message' => __('Permission denied.', 'docxtowp')));
	}

	$key = sanitize_key($_POST['key'] ?? '');
	if (!array_key_exists($key, dtpost_notice_thresholds())) {
		wp_send_json_error(array('message' => __('Unknown notice.', 'docxtowp')));
	}

	$user_id = get_current_user_id();
	$dismissed = (array) get_user_meta($user_id, 'dtpost_dismissed_notices', true);
	$dismissed[] = $key;

	update_user_meta($user_id, 'dtpost_dismissed_notices', array_values(array_unique($dismissed)));
	wp_send_json_success();
}

/**
 * Shows at most one contextual notice, on this plugin's screens only.
 *
 * Two rules keep this inside guideline 11, which asks that upgrade prompts be
 * limited in scope and used sparingly: nothing renders anywhere except this
 * plugin's own pages, and every notice is dismissible and stays dismissed.
 * The thresholds mean a notice only ever appears to someone who has already
 * hit the problem it describes.
 */
function dtpost_admin_notices(): void
{
	$page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ('docxtowp' !== $page && strpos($page, 'dtpost-') !== 0) {
		return;
	}

	$permissions = new DTPost_Permissions();
	if (!$permissions->current_user_can_use()) {
		return;
	}

	$count = dtpost_conversion_count();

	foreach (dtpost_notice_thresholds() as $key => $threshold) {
		if ($count < $threshold || dtpost_notice_dismissed($key)) {
			continue;
		}

		dtpost_render_notice($key, $count);
		return; // One at a time.
	}
}

/**
 * Renders one contextual notice.
 */
function dtpost_render_notice(string $key, int $count): void
{
	$review_url = 'https://wordpress.org/support/plugin/docxtowp/reviews/#new-post';

	switch ($key) {
		case 'review':
			$body = sprintf(
				/* translators: %d: number of documents the user has converted */
				esc_html__('You have converted %d documents with DocxToPost. If it has saved you some copy-pasting, a short review helps other people find it.', 'docxtowp'),
				$count
			);
			$cta = sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer" class="button button-primary">%s</a>',
				esc_url($review_url),
				esc_html__('Leave a review', 'docxtowp')
			);
			break;

		case 'drip':
			$body = sprintf(
				/* translators: %d: number of documents the user has converted */
				esc_html__('That is %d documents, one at a time. Pro imports up to 100 in a single pass and can drip-publish them on a schedule — one post per weekday, for example.', 'docxtowp'),
				$count
			);
			$cta = sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer" class="button button-primary">%s</a>',
				esc_url(dtpost_pro_url('notice-drip')),
				esc_html__('See how it works', 'docxtowp')
			);
			break;

		case 'bulk':
		default:
			$body = sprintf(
				/* translators: %d: number of documents the user has converted */
				esc_html__('%d documents so far, one upload at a time. Pro does up to 100 in one pass.', 'docxtowp'),
				$count
			);
			$cta = sprintf(
				'<a href="%s" target="_blank" rel="noopener noreferrer" class="button">%s</a>',
				esc_url(dtpost_pro_url('notice-bulk')),
				esc_html__('See bulk import', 'docxtowp')
			);
			break;
	}
	?>
	<?php // Not .dtpost-notice — that class belongs to the inline error box on the preview screen. ?>
	<div class="notice notice-info is-dismissible dtpost-usage-notice" data-dtpost-notice="<?php echo esc_attr($key); ?>">
		<p><?php echo wp_kses_post($body); ?></p>
		<p><?php echo wp_kses_post($cta); ?></p>
	</div>
	<?php
}

// Daily cron to clean temp dir. Scheduled in dtpost_activate(), not here —
// a wp_next_scheduled() call at file scope runs on every request, front end
// included, for something that needs to happen once.
add_action('dtpost_cleanup_cron', 'dtpost_clean_temp_dir');

/**
 * Register admin menu pages.
 */
function dtpost_register_admin_menu(): void
{
	$permissions = new DTPost_Permissions();
	if (!$permissions->current_user_can_use()) {
		return;
	}

	add_menu_page(
		__('DocxToPost', 'docxtowp'),
		__('DocxToPost', 'docxtowp'),
		'upload_files',
		'docxtowp',
		'dtpost_render_upload_page',
		'dashicons-media-document',
		30
	);

	add_submenu_page(
		'docxtowp',
		__('Upload Document', 'docxtowp'),
		__('Upload Document', 'docxtowp'),
		'upload_files',
		'docxtowp',
		'dtpost_render_upload_page'
	);

	add_submenu_page(
		'docxtowp',
		__('Preview', 'docxtowp'),
		__('Preview', 'docxtowp'),
		'upload_files',
		'dtpost-preview',
		'dtpost_render_preview_page'
	);

	// Named for the feature rather than filed under a generic "Upgrade",
	// because "Bulk Import" is what someone with a folder of documents is
	// actually looking for. The "(Pro)" suffix is what stops that being a
	// promise the free plugin does not keep — see admin/bulk-page.php.
	add_submenu_page(
		'docxtowp',
		__('Bulk Import', 'docxtowp'),
		__('Bulk Import (Pro)', 'docxtowp'),
		'upload_files',
		'dtpost-bulk',
		'dtpost_render_bulk_page'
	);

	add_submenu_page(
		'docxtowp',
		__('Settings', 'docxtowp'),
		__('Settings', 'docxtowp'),
		'manage_options',
		'dtpost-settings',
		'dtpost_render_settings_page'
	);

	add_submenu_page(
		'docxtowp',
		__('Upgrade to Pro', 'docxtowp'),
		__('Upgrade', 'docxtowp'),
		'upload_files',
		'dtpost-upgrade',
		'dtpost_render_upgrade_page'
	);
}

/**
 * Enqueue admin CSS and JS only on DocxToPost pages.
 */
function dtpost_enqueue_admin_assets(string $hook): void
{
	$page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

	// Only load on our plugin pages.
	if ('docxtowp' !== $page && strpos($page, 'dtpost-') !== 0) {
		return;
	}

	wp_enqueue_style(
		'dtpost-admin',
		DTPOST_PLUGIN_URL . 'assets/admin.css',
		array(),
		DTPOST_VERSION
	);

	wp_enqueue_script(
		'dtpost-admin',
		DTPOST_PLUGIN_URL . 'assets/admin-v2.js',
		array(),
		DTPOST_VERSION,
		true
	);

	// Build a list of public post types for the publish UI.
	$post_types_raw = get_post_types(array('public' => true), 'objects');
	$post_types = array();
	foreach ($post_types_raw as $slug => $obj) {
		$post_types[$slug] = $obj->labels->singular_name;
	}

	wp_localize_script(
		'dtpost-admin',
		'DTPOST',
		array(
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce' => wp_create_nonce('dtpost_nonce'),
			'max_mb' => (int) get_option('dtpost_max_upload_mb', 10),
			'post_types' => $post_types,
			'strings' => array(
				'uploading' => __('Uploading…', 'docxtowp'),
				'parsing' => __('Parsing document…', 'docxtowp'),
				'publishing' => __('Publishing…', 'docxtowp'),
				'confirm_publish' => __('Are you sure you want to publish this post?', 'docxtowp'),
				'confirm_trash' => __('Move this post to the trash? You can restore it from the Trash afterwards.', 'docxtowp'),
				'file_too_large' => __('File exceeds maximum allowed size.', 'docxtowp'),
				'invalid_type' => __('Only .docx files are allowed.', 'docxtowp'),
				'error' => __('An error occurred. Please try again.', 'docxtowp'),
				'btn_publish' => __('Publish', 'docxtowp'),
				'btn_draft' => __('Save as Draft', 'docxtowp'),
			),
		)
	);
}

// Page render callbacks — delegate to admin include files.
/**
 * Render the upload page.
 */
function dtpost_render_upload_page(): void
{
	require DTPOST_PLUGIN_DIR . 'admin/upload-page.php';
}

/**
 * Render the preview page.
 */
function dtpost_render_preview_page(): void
{
	require DTPOST_PLUGIN_DIR . 'admin/preview-page.php';
}

/**
 * Render the settings page.
 */
function dtpost_render_settings_page(): void
{
	require DTPOST_PLUGIN_DIR . 'admin/settings-page.php';
}

/**
 * Render the upgrade page.
 */
function dtpost_render_upgrade_page(): void
{
	require DTPOST_PLUGIN_DIR . 'admin/upgrade-page.php';
}

/**
 * Render the bulk import information page.
 */
function dtpost_render_bulk_page(): void
{
	require DTPOST_PLUGIN_DIR . 'admin/bulk-page.php';
}

// ---------------------------------------------------------------------------
// AJAX handlers
// ---------------------------------------------------------------------------

/**
 * AJAX: upload and parse a .docx file.
 */
function dtpost_ajax_upload_docx(): void
{
	try {
		check_ajax_referer('dtpost_nonce', 'nonce');

		$permissions = new DTPost_Permissions();
		if (!$permissions->current_user_can_use()) {
			wp_send_json_error(array('message' => __('Permission denied.', 'docxtowp')));
		}

		// Validate file was actually uploaded.
		if (empty($_FILES['docx_file']) || !isset($_FILES['docx_file']['tmp_name'])) {
			wp_send_json_error(array('message' => __('No file uploaded.', 'docxtowp')));
		}

		// Sanitize each $_FILES field individually before use.
		$file = array(
			'name'     => sanitize_file_name(wp_unslash($_FILES['docx_file']['name'])),
			'type'     => sanitize_mime_type(wp_unslash($_FILES['docx_file']['type'])),
			'tmp_name' => $_FILES['docx_file']['tmp_name'], // tmp_name is a server-generated path, not user input.
			'error'    => absint($_FILES['docx_file']['error']),
			'size'     => absint($_FILES['docx_file']['size']),
		);

		if (UPLOAD_ERR_OK !== $file['error']) {
			wp_send_json_error(array('message' => __('File upload error.', 'docxtowp')));
		}

		$max_size = (int) get_option('dtpost_max_upload_mb', 10) * 1024 * 1024;

		// Validate size.
		if ($file['size'] > $max_size) {
			wp_send_json_error(
				array(
					'message' => sprintf(
						/* translators: %d: max file size in MB */
						__('File exceeds maximum allowed size (%dMB).', 'docxtowp'),
						$max_size / 1024 / 1024
					),
				)
			);
		}

		// Validate that this is a real uploaded file (prevents path traversal).
		if (!is_uploaded_file($file['tmp_name'])) {
			wp_send_json_error(array('message' => __('Invalid file upload.', 'docxtowp')));
		}

		// Detect MIME type server-side; never trust the browser-supplied type.
		$mime = '';
		if (function_exists('finfo_open') && defined('FILEINFO_MIME_TYPE')) {
			$finfo = finfo_open(FILEINFO_MIME_TYPE);
			if ($finfo) {
				$detected = finfo_file($finfo, $file['tmp_name']);
				$mime     = $detected ? sanitize_mime_type($detected) : '';
				finfo_close($finfo);
			}
		}
		if (empty($mime) && function_exists('mime_content_type')) {
			$detected = mime_content_type($file['tmp_name']);
			$mime     = $detected ? sanitize_mime_type($detected) : '';
		}
		if (empty($mime)) {
			$mime = $file['type'];
		}

		$allowed_mimes = array(
			'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'application/zip', // Some systems report .docx as zip.
			'application/octet-stream', // Some servers misidentify docx as binary.
		);

		$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
		if ('docx' !== $ext || !in_array($mime, $allowed_mimes, true)) {
			wp_send_json_error(array('message' => __('Invalid file type. Only .docx files are allowed.', 'docxtowp')));
		}

		// Save to temp dir using wp_handle_upload pattern.
		$temp_dir = dtpost_get_temp_dir();
		if (!file_exists($temp_dir)) {
			wp_mkdir_p($temp_dir);
		}

		$uid = wp_generate_uuid4();
		$temp_file = $temp_dir . $uid . '.docx';

		if (!function_exists('wp_handle_upload')) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		// Temporarily filter upload dir to point to our temp folder.
		$custom_upload_filter = function ($arr) {
			$arr['path'] = trailingslashit($arr['basedir']) . 'dtpost-temp';
			$arr['url'] = trailingslashit($arr['baseurl']) . 'dtpost-temp';
			$arr['subdir'] = '/dtpost-temp';
			return $arr;
		};
		add_filter('upload_dir', $custom_upload_filter);

		$upload_overrides = array(
			'test_form' => false,
			'mimes' => array('docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
			'unique_filename_callback' => function ($dir, $name, $ext) use ($uid) {
				return $uid . '.docx';
			},
		);

		$movefile = wp_handle_upload($file, $upload_overrides);

		remove_filter('upload_dir', $custom_upload_filter);

		if ($movefile && !isset($movefile['error'])) {
			$temp_file = $movefile['file'];
		} else {
			wp_send_json_error(array('message' => $movefile['error'] ?? __('Failed to handle uploaded file.', 'docxtowp')));
		}

		// Verify the file was written and is readable.
		if (!file_exists($temp_file) || !is_readable($temp_file)) {
			wp_send_json_error(array('message' => __('Failed to verify uploaded file.', 'docxtowp')));
		}

		// Parse document — pass original name so title fallback is clean.
		$original_name = sanitize_file_name($file['name']);
		$parser = new DTPost_Parser();
		$result = $parser->parse($temp_file, $original_name);

		if (is_wp_error($result)) {
			wp_delete_file($temp_file);
			wp_send_json_error(array('message' => $result->get_error_message()));
		}

		// Handle featured image — sanitize each field before passing to the handler.
		$featured_image_id = 0;
		if (
			!empty($_FILES['featured_image'])
			&& isset($_FILES['featured_image']['error'])
			&& UPLOAD_ERR_OK === (int) $_FILES['featured_image']['error']
			&& is_uploaded_file($_FILES['featured_image']['tmp_name'])
		) {
			$featured_image_file = array(
				'name'     => sanitize_file_name(wp_unslash($_FILES['featured_image']['name'])),
				'type'     => sanitize_mime_type(wp_unslash($_FILES['featured_image']['type'])),
				'tmp_name' => $_FILES['featured_image']['tmp_name'],
				'error'    => absint($_FILES['featured_image']['error']),
				'size'     => absint($_FILES['featured_image']['size']),
			);
			$image_handler     = new DTPost_Image();
			$featured_image_id = $image_handler->upload_featured_image($featured_image_file);
		}

		// Store session data.
		$session_key = 'dtpost_session_' . get_current_user_id();
		set_transient(
			$session_key,
			array(
				'uid' => $uid,
				'temp_file' => $temp_file,
				'original_name' => $original_name,
				'title' => $result['title'],
				'content' => $result['content'],
				'featured_image_id' => $featured_image_id,
			),
			2 * HOUR_IN_SECONDS
		);

		wp_send_json_success(
			array(
				'redirect' => admin_url('admin.php?page=dtpost-preview'),
			)
		);
	} catch (\Throwable $e) {
		dtpost_log_exception($e);
		wp_send_json_error(array('message' => __('Something went wrong while processing the document. Please try again.', 'docxtowp')));
	}
}

/**
 * AJAX: publish the post.
 */
function dtpost_ajax_publish_post(): void
{
	try {
		check_ajax_referer('dtpost_nonce', 'nonce');

		$permissions = new DTPost_Permissions();
		if (!$permissions->current_user_can_use()) {
			wp_send_json_error(array('message' => __('Permission denied.', 'docxtowp')));
		}

		$session_key = 'dtpost_session_' . get_current_user_id();
		$session = get_transient($session_key);

		if (!$session) {
			wp_send_json_error(array('message' => __('Session expired. Please re-upload your document.', 'docxtowp')));
		}

		// Validate post type — allow any registered public post type.
		$requested_type = sanitize_key($_POST['post_type'] ?? 'post'); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$public_types = array_keys(get_post_types(array('public' => true)));
		$post_type = in_array($requested_type, $public_types, true) ? $requested_type : 'post';

		// Reaching the plugin is not the same as being allowed to create this
		// post type. wp_insert_post() enforces no capabilities of its own, so
		// the check has to happen here.
		if (!$permissions->can_create($post_type)) {
			wp_send_json_error(array('message' => __('You are not allowed to create content of this type.', 'docxtowp')));
		}

		// Validate status.
		$allowed_statuses = array('publish', 'draft', 'private', 'pending');
		$raw_status = sanitize_key($_POST['post_status'] ?? 'draft'); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$status = in_array($raw_status, $allowed_statuses, true) ? $raw_status : 'draft';

		// Publishing and publishing privately both need the publish capability.
		// Anyone without it gets a draft rather than an error, so the import
		// they just waited for is not thrown away.
		if (in_array($status, array('publish', 'private'), true) && !$permissions->can_publish($post_type)) {
			$status = 'pending';
		}

		// Attributing the post to another user needs edit_others_posts.
		$raw_author = isset($_POST['post_author']) ? absint($_POST['post_author']) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ($raw_author > 0 && $raw_author !== get_current_user_id() && !$permissions->can_set_author($post_type)) {
			$raw_author = 0;
		}
		$data = array(
			'post_type' => $post_type,
			'title' => sanitize_text_field(wp_unslash($_POST['post_title'] ?? '')), // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'content' => wp_kses_post(wp_unslash($_POST['post_content'] ?? $session['content'])), // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'excerpt' => sanitize_textarea_field(wp_unslash($_POST['post_excerpt'] ?? '')), // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'category' => array_map('absint', (array) ($_POST['post_category'] ?? array(get_option('default_category', 1)))), // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'tags' => sanitize_text_field(wp_unslash($_POST['post_tags'] ?? '')), // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'author' => $raw_author > 0 ? $raw_author : get_current_user_id(),
			'status' => $status,
			'slug' => sanitize_title(wp_unslash($_POST['post_slug'] ?? '')), // phpcs:ignore WordPress.Security.NonceVerification.Missing
			'featured_image_id' => absint($session['featured_image_id']),
		);

		$publisher = new DTPost_Publisher();
		$post_id = $publisher->publish($data);

		if (is_wp_error($post_id)) {
			wp_send_json_error(array('message' => $post_id->get_error_message()));
		}

		// Clear session.
		delete_transient($session_key);

		// Count the conversion. This drives the contextual notices, and is the
		// only thing the plugin records about how you use it — it never leaves
		// the site.
		$user_id = get_current_user_id();
		update_user_meta($user_id, 'dtpost_conversion_count', dtpost_conversion_count() + 1);

		wp_send_json_success(
			array(
				'post_id' => $post_id,
				'permalink' => get_permalink($post_id),
				'title' => get_the_title($post_id),
				'redirect' => admin_url('admin.php?page=dtpost-preview&dtpost_success=1&post_id=' . $post_id),
			)
		);
	} catch (\Throwable $e) {
		dtpost_log_exception($e);
		wp_send_json_error(array('message' => __('Something went wrong while processing the document. Please try again.', 'docxtowp')));
	}
}

/**
 * AJAX: search existing tags.
 */
function dtpost_ajax_tag_search(): void
{
	check_ajax_referer('dtpost_nonce', 'nonce');

	$permissions = new DTPost_Permissions();
	if (!$permissions->current_user_can_use()) {
		wp_send_json_error(array('message' => __('Permission denied.', 'docxtowp')));
	}

	$search = sanitize_text_field(wp_unslash($_GET['q'] ?? '')); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$tags = get_tags(
		array(
			'search' => $search,
			'number' => 20,
			'hide_empty' => false,
		)
	);

	$results = array();
	foreach ((array) $tags as $tag) {
		$results[] = array(
			'id' => $tag->term_id,
			'name' => $tag->name,
		);
	}

	wp_send_json_success($results);
}

/**
 * AJAX: clear temp upload directory.
 */
function dtpost_ajax_clear_temp(): void
{
	check_ajax_referer('dtpost_nonce', 'nonce');

	if (!current_user_can('manage_options')) {
		wp_send_json_error(array('message' => __('Permission denied.', 'docxtowp')));
	}

	$temp_dir = dtpost_get_temp_dir();

	if (!is_dir($temp_dir)) {
		wp_send_json_success(array('cleared' => 0));
	}

	$files = glob($temp_dir . '*') ?: array();
	$cleared = 0;

	// Leave the last hour alone. Sessions hold a two-hour transient pointing
	// at these files, so deleting one out from under another editor breaks
	// their import with no warning.
	$cutoff = time() - HOUR_IN_SECONDS;

	foreach ($files as $file) {
		if (!is_file($file)) {
			continue;
		}
		$name = basename($file);
		if ('.htaccess' === $name || 'index.php' === $name) {
			continue;
		}
		if (filemtime($file) > $cutoff) {
			continue;
		}
		wp_delete_file($file);
		$cleared++;
	}

	wp_send_json_success(array('cleared' => $cleared));
}
