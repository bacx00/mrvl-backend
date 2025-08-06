<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=mrvl_tournament', 'root', 'Sup3rSecur3!');
    
    // Check if earnings column exists
    $stmt = $pdo->query("DESCRIBE players");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Players table columns:\n";
    foreach ($columns as $column) {
        if (strpos($column['Field'], 'earnings') !== false) {
            echo "- " . $column['Field'] . " (" . $column['Type'] . ")\n";
        }
    }
    
    // Check player 461 data
    $stmt = $pdo->prepare("SELECT id, username, earnings, total_earnings FROM players WHERE id = 461");
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "\nPlayer 461 data:\n";
    if ($result) {
        print_r($result);
    } else {
        echo "Player 461 not found\n";
    }
    
    // Check if there are any players with earnings > 0
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM players WHERE earnings > 0");
    $count = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "\nPlayers with earnings > 0: " . $count['count'] . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>