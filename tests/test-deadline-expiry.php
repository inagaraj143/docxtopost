<?php
define('DAY_IN_SECONDS', 86400);
define('DTPOST_LIFETIME_DEADLINE', '2026-09-30T23:59:59+00:00');

function seconds_left_at(string $now): int {
    return strtotime(DTPOST_LIFETIME_DEADLINE) - strtotime($now);
}
function days_left_from(int $secs): int { return (int) ceil($secs / DAY_IN_SECONDS); }

$cases = array(
    array('2026-09-01T12:00:00+00:00', 'a month out',              true),
    array('2026-09-29T12:00:00+00:00', 'day before',               true),
    array('2026-09-30T12:00:00+00:00', 'final day',                true),
    array('2026-09-30T23:59:58+00:00', 'one second before',        true),
    array('2026-10-01T00:00:01+00:00', 'one second after',         false),
    array('2026-10-01T12:00:00+00:00', 'the next morning',         false),
    array('2026-12-25T00:00:00+00:00', 'never-updated site',       false),
    array('2027-06-01T00:00:00+00:00', 'a year later',             false),
);

$fail = 0;
foreach ($cases as [$when, $label, $should_show]) {
    $secs  = seconds_left_at($when);
    $shows = $secs > 0;
    $days  = days_left_from($secs);
    $text  = $secs <= DAY_IN_SECONDS ? 'last day' : "$days days left";

    $ok = $shows === $should_show;
    printf("  %-22s %-22s %s  %-14s %s\n",
        substr($when, 0, 19), $label,
        $shows ? 'SHOWS ' : 'hidden',
        $shows ? $text : '',
        $ok ? 'ok' : 'FAIL');
    $ok || $fail++;
}
echo $fail === 0 ? "\n  Self-expiry is exact.\n" : "\n  $fail FAILED.\n";
exit($fail === 0 ? 0 : 1);
