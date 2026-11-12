<?php
require_once dirname(__DIR__) . '/config/db.php';
$rows = db()->fetchAll("SELECT id,user_id,type,status,amount,currency,gateway,reference,gateway_reference,invoice_number,metadata FROM transactions ORDER BY id DESC");
foreach ($rows as $r) { echo json_encode($r, JSON_UNESCAPED_SLASHES) . "\n"; }
echo "--- settings ---\n";
$s = db()->fetchAll("SELECT setting_key, LEFT(setting_value,60) v FROM site_settings WHERE setting_key LIKE '%pay%' OR setting_key LIKE '%flutter%' OR setting_key LIKE '%referral%' OR setting_key LIKE '%credit%'");
foreach ($s as $r) { echo json_encode($r) . "\n"; }
echo "--- users ---\n";
foreach (db()->fetchAll("SELECT id,email,role,status,referral_code,referred_by,ad_credits,ai_credits FROM users") as $r) { echo json_encode($r) . "\n"; }
