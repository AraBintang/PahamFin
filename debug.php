<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h2>PahamFin System Diagnostic</h2>";

try {
    require_once __DIR__ . '/app/includes/config.php';
    echo "✅ Config loaded successfully.<br>";
} catch (Throwable $e) {
    echo "❌ Config Error: " . htmlspecialchars($e->getMessage()) . "<br>";
}

try {
    require_once __DIR__ . '/app/db.php';
    if (isset($pdo) && $pdo instanceof PDO) {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        echo "✅ Database Connected! Driver: <strong>" . htmlspecialchars($driver) . "</strong><br>";
        
        $userCount = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        echo "📊 Total Users in DB: <strong>" . (int)$userCount . "</strong><br>";
    } else {
        echo "⚠️ Database Object (\$pdo) is NULL.<br>";
    }
} catch (Throwable $e) {
    echo "❌ DB Error: " . htmlspecialchars($e->getMessage()) . "<br>";
}

echo "<hr><p>End of diagnostic check.</p>";
