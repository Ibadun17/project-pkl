<?php
require_once __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Method not allowed', 405);

$body = read_json_body();
$missing = require_fields($body, ['email', 'password']);
if ($missing) fail('Field wajib belum diisi', 422, ['missing' => $missing]);

$email = strtolower(trim($body['email']));
$pass  = (string)$body['password'];

$pdo = db();
$st = $pdo->prepare("SELECT id, email, password_hash, role, nama, nip FROM users WHERE email = ?");
$st->execute([$email]);
$user = $st->fetch();

if (!$user || !password_verify($pass, $user['password_hash'])) {
  fail('Email atau password salah', 401);
}

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
$_SESSION['user_id'] = (int)$user['id'];

ok([
  'id' => (int)$user['id'],
  'email' => $user['email'],
  'nama' => $user['nama'],
], 'Login sukses');
