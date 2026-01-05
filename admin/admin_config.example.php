<?php
// Copy this file to a secure location outside of the public web root,
// e.g. /home2/youruser/admin_config.php, and update the password hash below.
// Then ensure /admin/index.php can read it. The default lookup path is two
// directories above /admin (dirname(__DIR__, 2) . '/admin_config.php').
//
// Generate a bcrypt hash for your chosen admin password using:
// php -r 'echo password_hash("YOUR_PASSWORD", PASSWORD_BCRYPT), PHP_EOL;'
//
// Example:
// define('ADMIN_PASSWORD_HASH', '$2y$10$examplehashreplace');

// Replace the example hash with the output from password_hash.
// define('ADMIN_PASSWORD_HASH', '');
