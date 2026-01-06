<?php
require_once __DIR__ . '/../_bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Method not allowed', 405);

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
session_destroy();
ok(null, 'Logout sukses');
