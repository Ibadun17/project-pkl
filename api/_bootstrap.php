<?php
// api/_bootstrap.php

require_once __DIR__ . '/../app/config/config.php';
load_env(__DIR__ . '/../.env');

require_once __DIR__ . '/../app/config/database.php';
require_once __DIR__ . '/../app/helpers/response.php';
require_once __DIR__ . '/../app/helpers/validator.php';
require_once __DIR__ . '/../app/middleware/auth.php';

// CORS (kalau kamu akses dari file HTML). Kalau nanti sudah 1 domain bisa diperketat.
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;
