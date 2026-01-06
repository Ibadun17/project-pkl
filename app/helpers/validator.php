<?php
// app/helpers/validator.php

function read_json_body(): array {
  $raw = file_get_contents('php://input');
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}

function require_fields(array $data, array $fields): array {
  $missing = [];
  foreach ($fields as $f) {
    if (!isset($data[$f])) {
      $missing[] = $f;
      continue;
    }

    // ✅ TAMBAHAN: kalau value array, valid kalau tidak kosong
    if (is_array($data[$f])) {
      if (count($data[$f]) === 0) $missing[] = $f;
      continue;
    }

    // default (string/number/bool/null)
    if (trim((string)$data[$f]) === '') $missing[] = $f;
  }
  return $missing;
}
