<?php
/**
 * The Black Friday 2026 notice.
 *
 *   php tests/test-black-friday.php
 *
 * The window is the thing that must be right: a sale notice that shows a day
 * early, or is still up after the code has expired, sends people to a
 * checkout that refuses the code. The functions are lifted out of the main
 * plugin file and run against the boundary times.
 *
 * The rest is guideline 11 of the plugin directory: the notice renders only
 * on this plugin's own screens, is dismissible, and stays dismissed.
 *
 * @package DocxToPost
 */

$main = (string) file_get_contents( __DIR__ . '/../docxtopost.php' );

$pass = 0;
$fail = 0;

function check( string $label, bool $ok, string $extra = '' ): void {
	global $pass, $fail;
	printf( "  %-62s %s%s\n", $label, $ok ? 'ok' : 'FAIL', '' !== $extra ? '  ' . $extra : '' );
	$ok ? $pass++ : $fail++;
}

// Lift the two pure functions out and define them here.
foreach ( array( 'dtpost_bf_key', 'dtpost_bf_active' ) as $fn ) {
	if ( preg_match( '/function ' . $fn . '\(.*?\r?\n}\r?\n/s', $main, $m ) ) {
		eval( $m[0] ); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- test harness, own source.
	}
}

echo "\nThe sale window (2 Nov to 1 Dec, India time)\n\n";

$at = static function ( string $iso ): int {
	return (int) strtotime( $iso );
};

check( 'functions found in docxtopost.php', function_exists( 'dtpost_bf_active' ) && function_exists( 'dtpost_bf_key' ) );
check( 'off the day before', ! dtpost_bf_active( $at( '2026-11-01T23:59:59+05:30' ) ) );
check( 'on at midnight, 2 November', dtpost_bf_active( $at( '2026-11-02T00:00:00+05:30' ) ) );
check( 'on during Black Friday', dtpost_bf_active( $at( '2026-11-27T12:00:00+05:30' ) ) );
check( 'on at 23:59:59 on 1 December', dtpost_bf_active( $at( '2026-12-01T23:59:59+05:30' ) ) );
check( 'off one second later', ! dtpost_bf_active( $at( '2026-12-02T00:00:00+05:30' ) ) );
check( 'on today, the day the release ships', dtpost_bf_active( $at( '2026-11-02T12:00:00+05:30' ) ) );

echo "\nPlugin directory rules\n\n";

preg_match( '/function dtpost_admin_notices\(\).*?\r?\n}\r?\n/s', $main, $m );
$notices = $m[0] ?? '';
preg_match( '/function dtpost_render_bf_notice\(\).*?\r?\n}\r?\n/s', $main, $m );
$render = $m[0] ?? '';
preg_match( '/function dtpost_ajax_dismiss_notice\(\).*?\r?\n}\r?\n/s', $main, $m );
$dismiss = $m[0] ?? '';

check( 'it renders from the plugin-screens-only notice hook',
	str_contains( $notices, 'dtpost_render_bf_notice()' )
	&& strpos( $notices, "strpos(\$page, 'dtpost-')" ) < strpos( $notices, 'dtpost_render_bf_notice()' ) );
check( 'only while the sale runs, and not once dismissed',
	str_contains( $notices, 'dtpost_bf_active() && !dtpost_notice_dismissed(dtpost_bf_key())' ) );
check( 'it is dismissible, through the existing handler', str_contains( $render, 'is-dismissible' ) && str_contains( $render, 'data-dtpost-notice' ) );
check( 'the dismiss handler accepts its key and nothing new besides', str_contains( $dismiss, '&& dtpost_bf_key() !== $key' ) );
check( 'the code shown is the one set up in Dodo', str_contains( $render, 'BFDX2026' ) );
check( 'the link is tagged so sales from it can be counted',
	str_contains( $render, "'utm_medium'   => 'bf-notice'" ) && str_contains( $render, "'utm_campaign' => dtpost_bf_key()" ) );
check( 'no request to any server decides whether it shows',
	! preg_match( '/wp_(safe_)?remote_/', $render . $notices ) );

printf( "\n  %d passed, %d failed.\n\n", $pass, $fail );
exit( 0 === $fail ? 0 : 1 );
