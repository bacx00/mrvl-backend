<?php
/**
 * Debug Player Structure
 * Examine the actual player data structure
 */

class PlayerStructureDebugger
{
    private $baseUrl = 'http://localhost:8000/api';
    
    public function analyzePlayerStructure()
    {
        echo "🔍 Analyzing Player Data Structure...\n\n";
        
        $response = $this->makeRequest('GET', '/players');
        
        if ($response && isset($response['data']) && !empty($response['data'])) {
            $firstPlayer = $response['data'][0];
            
            echo "📋 First Player Structure:\n";
            echo "=====================================\n";
            
            foreach ($firstPlayer as $key => $value) {
                if (is_array($value) || is_object($value)) {
                    echo "• $key: " . json_encode($value, JSON_PRETTY_PRINT) . "\n";
                } else {
                    echo "• $key: $value\n";
                }
            }
            
            echo "\n📊 Player Data Analysis:\n";
            echo "=====================================\n";
            
            // Check what fields exist
            $expectedFields = [
                'id' => isset($firstPlayer['id']),
                'name' => isset($firstPlayer['name']),
                'username' => isset($firstPlayer['username']),
                'team_id' => isset($firstPlayer['team_id']),
                'team' => isset($firstPlayer['team']),
                'role' => isset($firstPlayer['role']),
                'country' => isset($firstPlayer['country']),
                'age' => isset($firstPlayer['age']),
                'avatar' => isset($firstPlayer['avatar'])
            ];
            
            foreach ($expectedFields as $field => $exists) {
                $status = $exists ? '✅' : '❌';
                echo "$status $field\n";
            }
            
            echo "\n🔍 Team Relationship Analysis:\n";
            echo "=====================================\n";
            
            if (isset($firstPlayer['team'])) {
                echo "✅ Player has nested team object\n";
                echo "Team structure: " . json_encode($firstPlayer['team'], JSON_PRETTY_PRINT) . "\n";
            } else {
                echo "❌ No team relationship found\n";
            }
            
            if (isset($firstPlayer['team_id'])) {
                echo "✅ Player has team_id field: " . $firstPlayer['team_id'] . "\n";
            } else {
                echo "❌ No direct team_id field\n";
            }
            
        } else {
            echo "❌ No player data found or API failed\n";
        }
        
        // Also check a single player endpoint
        echo "\n\n🔍 Single Player Endpoint Analysis:\n";
        echo "=====================================\n";
        
        $singlePlayerResponse = $this->makeRequest('GET', '/players/1');
        
        if ($singlePlayerResponse) {
            echo "Single player structure:\n";
            foreach ($singlePlayerResponse as $key => $value) {
                if (is_array($value) || is_object($value)) {
                    echo "• $key: " . json_encode($value, JSON_PRETTY_PRINT) . "\n";
                } else {
                    echo "• $key: $value\n";
                }
            }
        } else {
            echo "❌ Single player endpoint failed\n";
        }
    }
    
    private function makeRequest($method, $endpoint)
    {
        $url = $this->baseUrl . $endpoint;
        $curl = curl_init();
        
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_TIMEOUT => 10
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        if ($response === false || $httpCode >= 400) {
            return false;
        }
        
        return json_decode($response, true);
    }
}

$debugger = new PlayerStructureDebugger();
$debugger->analyzePlayerStructure();