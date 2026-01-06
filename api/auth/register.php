<?php
require_once __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Method not allowed', 405);

$body = read_json_body();
$missing = require_fields($body, ['email', 'password']);
if ($missing) fail('Field wajib belum diisi', 422, ['missing' => $missing]);

$email = strtolower(trim($body['email']));
$pass  = (string)$body['password'];

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail('Email tidak valid', 422);

$pdo = db();

// cek email sudah ada
$st = $pdo->prepare("SELECT id FROM users WHERE email = ?");
$st->execute([$email]);
if ($st->fetch()) fail('Email sudah terdaftar', 409);

// simpan user
$hash = password_hash($pass, PASSWORD_BCRYPT);
$role = 'user';

$st = $pdo->prepare("INSERT INTO users (email, password_hash, role) VALUES (?, ?, ?)");
$st->execute([$email, $hash, $role]);

ok(['user_id' => (int)$pdo->lastInsertId(), 'email' => $email, 'role' => $role], 'Register sukses');
