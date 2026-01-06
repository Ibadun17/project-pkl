<?php
// app/middleware/auth.php

function require_login(): void {
  if (session_status() !== PHP_SESSION_ACTIVE) session_start();

  if (empty($_SESSION['user_id'])) {
    fail('Unauthorized. Silakan login dulu.', 401);
  }
}
