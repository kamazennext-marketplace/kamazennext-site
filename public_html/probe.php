<?php
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
  "ok" => true,
  "time" => date('c'),
  "script" => __FILE__,
  "cwd" => getcwd(),
  "server" => [
    "host" => $_SERVER['HTTP_HOST'] ?? null,
    "uri" => $_SERVER['REQUEST_URI'] ?? null
  ],
  "ini" => [
    "auto_prepend_file" => ini_get('auto_prepend_file'),
    "auto_append_file" => ini_get('auto_append_file'),
    "user_ini_filename" => ini_get('user_ini.filename'),
    "loaded_ini_file" => php_ini_loaded_file(),
    "scanned_ini_files" => php_ini_scanned_files()
  ]
], JSON_UNESCAPED_SLASHES);
