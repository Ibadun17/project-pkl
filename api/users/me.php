<?php
require_once __DIR__ . '/../_bootstrap.php';
require_login();

$pdo = db();
$st = $pdo->prepare("SELECT id, nama, nip, email, role FROM users WHERE id = ?");
$st->execute([$_SESSION['user_id']]);
$user = $st->fetch();

if (!$user) fail('User tidak ditemukan', 404);
ok($user);
