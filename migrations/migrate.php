<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/Security.php';

$pdo = getDB();

// Create tables
$sql = file_get_contents(__DIR__ . '/001_initial_schema.sql');
// Remove the placeholder admin, we'll add a real one
$sql = preg_replace('/-- Default admin.*?;\n/s', '', $sql);

try {
    // Split and run statements
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        fn($s) => !empty($s) && !str_starts_with(ltrim($s), '--')
    );
    foreach ($statements as $statement) {
        if (trim($statement)) {
            $pdo->exec($statement);
        }
    }
    echo " Tables created successfully\n";
} catch (PDOException $e) {
    echo " Migration error: " . $e->getMessage() . "\n";
    exit(1);
}

// Seed default admin
$username = 'Admin Users';
$email    = 'adminuser@gmail.com';
$password = '12345678';
$hash     = Security::hashPassword($password);

$stmt = $pdo->prepare("INSERT IGNORE INTO admins (username, email, password) VALUES (?, ?, ?)");
$stmt->execute([$username, $email, $hash]);

if ($stmt->rowCount()) {
    echo "✓ Default admin created: $username / $password\n";
} else {
    echo "ℹ Admin already exists\n";
}

echo "\n Migration complete!\n";
echo "Default credentials: $username / $password\n";
echo "⚠  Change the password after first login!\n";