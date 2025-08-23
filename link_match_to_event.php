<?php
$db = new PDO("mysql:host=localhost;dbname=mrvl_gaming", "root", "");

// First, let's check event 2 details
$stmt = $db->query("SELECT * FROM events WHERE id = 2");
$event = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Event 2 Details:\n";
print_r($event);

// Update match 7 to belong to event 2
$stmt = $db->prepare("UPDATE mrvl_matches SET event_id = 2 WHERE id = 7");
$stmt->execute();
echo "\nMatch 7 linked to Event 2\n";

// Verify the update
$stmt = $db->query("SELECT id, event_id, team1_id, team2_id FROM mrvl_matches WHERE id = 7");
$match = $stmt->fetch(PDO::FETCH_ASSOC);
echo "Updated Match 7:\n";
print_r($match);