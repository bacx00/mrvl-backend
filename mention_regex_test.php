<?php

// Test mention regex patterns without database dependencies

function testMentionRegexPatterns()
{
    echo "=== TESTING MENTION REGEX PATTERNS ===\n\n";
    
    $testCases = [
        "Hey @testuser how are you?",
        "Check out @team:TSM and @team:C9",
        "Great play by @player:shroud and @player:ninja",
        "Tournament @team:G2 vs @team:FNC with @player:caps and @testuser watching",
        "Email test@example.com should not be parsed",
        "@user_with_underscores is valid",
        "@123numbers should work"
    ];
    
    foreach ($testCases as $content) {
        echo "Testing: $content\n";
        
        // Test user mentions
        preg_match_all('/(?<![a-zA-Z0-9.])@([a-zA-Z0-9_]+)(?![:@])(?!\w)/', $content, $userMatches);
        echo "  User mentions found: " . count($userMatches[1]) . " - " . implode(', ', $userMatches[1]) . "\n";
        
        // Test team mentions
        preg_match_all('/@team:([a-zA-Z0-9_]+)/', $content, $teamMatches);
        echo "  Team mentions found: " . count($teamMatches[1]) . " - " . implode(', ', $teamMatches[1]) . "\n";
        
        // Test player mentions
        preg_match_all('/@player:([a-zA-Z0-9_]+)/', $content, $playerMatches);
        echo "  Player mentions found: " . count($playerMatches[1]) . " - " . implode(', ', $playerMatches[1]) . "\n";
        
        echo "\n";
    }
}

testMentionRegexPatterns();