<?php
// app/config/config.php

function load_env($path){
  if(!file_exists($path)) return;

  $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  foreach($lines as $line){
    $line = trim($line);
    if($line === '' || str_starts_with($line, '#')) continue;

    [$key, $val] = array_pad(explode('=', $line, 2), 2, '');
    $key = trim($key);
    $val = trim($val);

    // hapus kutip kalau ada
    $val = trim($val, "\"'");

    $_ENV[$key] = $val;
    $_SERVER[$key] = $val;
    putenv("$key=$val"); // <-- ini yang bikin getenv() kebaca
  }
}

