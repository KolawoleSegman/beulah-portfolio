<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

echo "<h2>1. PHP is working</h2>";
echo "Current folder: " . __DIR__ . "<br><br>";

// Check config.local.php
$localFile = __DIR__ . '/config.local.php';
echo "<h2>2. Looking for config.local.php</h2>";
echo "Path: $localFile<br>";
if (is_file($localFile)) {
    echo "<strong style='color:green'>FOUND</strong><br>";
    require $localFile;
    echo "Host: " . ($dbHost ?? 'NOT SET') . "<br>";
    echo "Database: " . ($dbName ?? 'NOT SET') . "<br>";
    echo "User: " . ($dbUser ?? 'NOT SET') . "<br>";
} else {
    echo "<strong style='color:red'>NOT FOUND</strong><br>";
    echo "This is why the site is broken. Create the file now.<br>";
    exit;
}

echo "<br><h2>3. Testing database connection</h2>";
try {
    $pdo = new PDO(
        "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPass,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "<strong style='color:green'>CONNECTED SUCCESSFULLY</strong><br>";
} catch (Exception $e) {
    echo "<strong style='color:red'>FAILED:</strong> " . htmlspecialchars($e->getMessage());
    exit;
}

echo "<br><h2>4. Checking tables</h2>";
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "Tables found: " . implode(', ', $tables) . "<br>";

echo "<br><h2>5. All good!</h2>";
echo "If you see this message, the database is fine. The problem is inside index.php.";