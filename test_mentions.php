<?php
$dsn = "mysql:host=127.0.0.1;dbname=mrvl_production";
$pdo = new PDO($dsn, 'mrvl_user', '1f9ER!ancao13\$18jdw9ioqs');

// Count mentions by type
$stmt = $pdo->query("SELECT mentioned_type, COUNT(*) as total FROM mentions GROUP BY mentioned_type");
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Mentions by type:\n";
print_r($result);

// Check specific player mentions
echo "\nPlayer mentions for ID 405 (delenaa):\n";
$stmt = $pdo->prepare("SELECT * FROM mentions WHERE mentioned_type = 'player' AND mentioned_id = 405");
$stmt->execute();
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Count: " . count($result) . "\n";

// Check any player mentions
echo "\nAny player mentions:\n";
$stmt = $pdo->prepare("SELECT * FROM mentions WHERE mentioned_type = 'player' LIMIT 5");
$stmt->execute();
$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "Count: " . count($result) . "\n";
if (count($result) > 0) {
    foreach($result as $row) {
        echo "- Player ID: {$row['mentioned_id']}, Text: {$row['mention_text']}\n";
    }
}
