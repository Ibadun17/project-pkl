<?php
require_once __DIR__ . '/../_bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Method not allowed', 405);

$body = read_json_body();
$id = (int)($body['id'] ?? 0);
if ($id < 1) fail('id tidak valid', 422);

$pdo = db();
$st = $pdo->prepare("DELETE FROM schedules WHERE id = ? AND user_id = ?");
$st->execute([$id, $_SESSION['user_id']]);

ok(['id'=>$id], 'Deleted');
