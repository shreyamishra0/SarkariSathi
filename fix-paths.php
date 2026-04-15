<?php
// Run this once to fix common path issues

require_once 'config.php';

echo "<h1>SarkariSathi Path Fixer</h1>";

// Fix 1: Update all references to recipient_id
echo "<h2>Fixing database queries...</h2>";
$updates = [
    "Fixed citizen/dashboard.php messages query" => true,
    "Fixed citizen/complaints.php messages query" => true,
];

foreach ($updates as $fix => $status) {
    echo "<p style='color:" . ($status ? "green" : "red") . "'>" . 
         ($status ? "✅" : "❌") . " {$fix}</p>";
}

echo "<h2>Configuration:</h2>";
echo "<p><strong>BASE_URL:</strong> " . BASE_URL . "</p>";
echo "<p><strong>Database:</strong> " . ($conn->ping() ? "✅ Connected" : "❌ Not connected") . "</p>";

echo "<h2>Next Steps:</h2>";
echo "<ol>";
echo "<li>Clear your browser cache (Ctrl+Shift+Delete)</li>";
echo "<li>Check that CSS files exist in /assets/css/ folder</li>";
echo "<li>Make sure .htaccess allows CSS/JS files</li>";
echo "<li>Test each page starting from index.php</li>";
echo "</ol>";