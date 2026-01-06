<?php
// app/config/database.php

function db(): PDO {
  static $pdo = null;
  if ($pdo) return $pdo;

  $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
  $name = $_ENV['DB_NAME'] ?? 'smart_kominfo';
  $user = $_ENV['DB_USER'] ?? 'root';
  $pass = $_ENV['DB_PASS'] ?? '';

  $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";
  $pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);

  return $pdo;
}
