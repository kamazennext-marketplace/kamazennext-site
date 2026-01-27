# Diagnostics

Test these URLs directly (no rewrites). Expected results:

1. `/probe.php` -> JSON with time, php_version, server paths, and loaded ini file.
2. `/kzn-health.txt` -> `OK KZN ROOT HEALTH <timestamp>` text.
3. `/marketplace/test.txt` -> `MARKETPLACE STATIC OK <timestamp>` text.
4. `/marketplace/health.php` -> JSON `{ "ok": true, "time": "...", "path": "..." }`.
