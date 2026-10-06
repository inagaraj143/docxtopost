<?php
/**
 * Filename-to-title conversion in all three modes.
 *
 *     php tests/test-title.php
 *
 * A customer reported the old ucwords() behaviour as wrong for UK
 * grammar, "Annual Report For The Board". He was right, and so would an
 * American have been: no style guide capitalises every word. The fix is a
 * setting, and the point of these checks is that each mode does what its
 * label in Settings promises, and that none of them destroys capitalisation
 * the author put in the filename on purpose.
 */

define( 'ABSPATH', __DIR__ . '/' );

$GLOBALS['stub_options'] = array();
function get_option( string $key, $default = false ) {
	return $GLOBALS['stub_options'][ $key ] ?? $default;
}
function __( string $t, string $d = '' ): string { return $t; }

require dirname( __DIR__ ) . '/includes/class-dtpost-title.php';

$fail = 0; $pass = 0;
function check( string $label, bool $ok, string $detail = '' ): void {
	global $fail, $pass;
	printf( "  %-62s %s\n", $label, $ok ? 'ok' : 'FAIL' );
	if ( $ok ) { $pass++; } else { $fail++; if ( '' !== $detail ) { echo "      got: $detail\n"; } }
}

$s = static fn( string $f ): string => DTPost_Title::from_filename( $f, DTPost_Title::SENTENCE );
$t = static fn( string $f ): string => DTPost_Title::from_filename( $f, DTPost_Title::TITLE );
$r = static fn( string $f ): string => DTPost_Title::from_filename( $f, DTPost_Title::RAW );

echo "  Sentence case: the default, and what the customer asked for\n";
check( 'the reported case',                     'Annual report for the board' === $s( 'annual-report-for-the-board.docx' ), $s( 'annual-report-for-the-board.docx' ) );
check( 'underscores and dots are separators',   'Q3 results final' === $s( 'q3_results.final.md' ), $s( 'q3_results.final.md' ) );
check( 'NEVER lowercases a proper noun',        'Meeting with John Smith' === $s( 'meeting-with-John-Smith.docx' ), $s( 'meeting-with-John-Smith.docx' ) );
check( 'NEVER lowercases an acronym',           'Annual report NHS' === $s( 'annual-report-NHS.docx' ), $s( 'annual-report-NHS.docx' ) );
check( 'a leading acronym is left alone',       'NHS annual report' === $s( 'NHS-annual-report.docx' ), $s( 'NHS-annual-report.docx' ) );
check( 'a deliberately Title Cased filename survives', 'Annual Report For The Board' === $s( 'Annual-Report-For-The-Board.docx' ), $s( 'Annual-Report-For-The-Board.docx' ) );
check( 'already sentence case: unchanged',      'Annual report' === $s( 'Annual-report.docx' ), $s( 'Annual-report.docx' ) );

echo "\n  Title Case, for anyone who wants it\n";
check( 'minor words stay lowercase',            'Annual Report for the Board' === $t( 'annual-report-for-the-board.docx' ), $t( 'annual-report-for-the-board.docx' ) );
check( 'first word capitalised even if minor',  'The Board Meeting' === $t( 'the-board-meeting.docx' ), $t( 'the-board-meeting.docx' ) );
check( 'last word capitalised even if minor',   'What We Are Working On' === $t( 'what-we-are-working-on.docx' ), $t( 'what-we-are-working-on.docx' ) );
check( 'acronyms preserved, not Nhs',           'Annual Report NHS' === $t( 'annual-report-NHS.docx' ), $t( 'annual-report-NHS.docx' ) );
check( 'long prepositions are capitalised',     'Notes Between Meetings' === $t( 'notes-between-meetings.docx' ), $t( 'notes-between-meetings.docx' ) );
check( 'SHOUTED filename is tamed',             'Annual Report' === $t( 'Annual-Report.docx' ), $t( 'Annual-Report.docx' ) );

echo "\n  Leave as written\n";
check( 'separators only',                       'annual report for the board' === $r( 'annual-report-for-the-board.docx' ), $r( 'annual-report-for-the-board.docx' ) );
check( 'nothing is capitalised',                'nhs report' === $r( 'nhs-report.docx' ), $r( 'nhs-report.docx' ) );

echo "\n  Shared behaviour\n";
check( 'extension is dropped',                  ! str_contains( $s( 'report.docx' ), 'docx' ), $s( 'report.docx' ) );
check( 'no extension is fine',                  'Report' === $s( 'report' ), $s( 'report' ) );
check( 'runs of separators collapse',           'Report final' === $s( 'report_-_final.docx' ), $s( 'report_-_final.docx' ) );
check( 'leading/trailing separators trimmed',   'Report' === $s( '-report-.docx' ), $s( '-report-.docx' ) );
check( 'empty name gives empty string',         '' === $s( '' ) && '' === $s( '.docx' ), '[' . $s( '.docx' ) . ']' );
check( 'accented first letter capitalises',     'Ünsere notizen' === $s( 'ünsere-notizen.md' ), $s( 'ünsere-notizen.md' ) );
check( 'non-latin script is left intact',       'हिंदी नोट' === $s( 'हिंदी-नोट.md' ), $s( 'हिंदी-नोट.md' ) );

echo "\n  The setting itself\n";
check( 'default is sentence case',              DTPost_Title::SENTENCE === DTPost_Title::mode() );
$GLOBALS['stub_options']['dtpost_filename_title_case'] = 'title';
check( 'a stored mode is honoured',             DTPost_Title::TITLE === DTPost_Title::mode() );
check( 'from_filename() with no mode reads it', 'Annual Report for the Board' === DTPost_Title::from_filename( 'annual-report-for-the-board.docx' ), DTPost_Title::from_filename( 'annual-report-for-the-board.docx' ) );
$GLOBALS['stub_options']['dtpost_filename_title_case'] = 'nonsense-from-the-database';
check( 'a junk stored value falls back safely', DTPost_Title::SENTENCE === DTPost_Title::mode() );

printf( "\n  %d passed, %d failed.\n", $pass, $fail );
exit( 0 === $fail ? 0 : 1 );
