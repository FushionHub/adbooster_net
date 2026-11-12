<?php
require_once dirname(__DIR__) . '/config/db.php';
foreach (['referrals','referral_rewards','transactions','site_settings'] as $t) {
    $cols = db()->fetchAll("SHOW COLUMNS FROM `$t`");
    echo "== $t ==\n";
    foreach ($cols as $c) { echo "  {$c['Field']} {$c['Type']} null={$c['Null']} def={$c['Default']}\n"; }
}
echo "== counts ==\n";
foreach (['referrals','transactions','users'] as $t) {
    try { echo "$t: " . db()->count($t) . "\n"; } catch (\Throwable $e) { echo "$t: ERR " . $e->getMessage() . "\n"; }
}
