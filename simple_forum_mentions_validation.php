<?php

/**
 * Simple Forum and Mentions System Validation
 * 
 * Validates that all required components are properly set up
 */

require_once __DIR__ . '/vendor/autoload.php';

class SimpleForumMentionsValidation
{
    private $errors = [];
    private $successes = [];
    
    public function validateSystem()
    {
        echo "🔍 Validating Forum & Mentions System Implementation\n";
        echo "=" . str_repeat("=", 55) . "\n\n";
        
        $this->validateControllers();
        $this->validateServices();
        $this->validateModels();
        $this->validateRoutes();
        $this->validateMigrations();
        
        $this->printReport();
        
        return empty($this->errors);
    }
    
    private function validateControllers()
    {
        echo "📁 Validating Controllers...\n";
        
        // Check ForumController
        $this->checkMethod('App\\Http\\Controllers\\ForumController', 'createThread', 'Forum createThread method');
        $this->checkMethod('App\\Http\\Controllers\\ForumController', 'createReply', 'Forum createReply method');
        $this->checkMethod('App\\Http\\Controllers\\ForumController', 'deletePost', 'Forum deletePost method');
        
        // Check NewsController  
        $this->checkMethod('App\\Http\\Controllers\\NewsController', 'createComment', 'News createComment method');
        $this->checkMethod('App\\Http\\Controllers\\NewsController', 'deleteComment', 'News deleteComment method');
        
        // Check MatchController
        $this->checkMethod('App\\Http\\Controllers\\MatchController', 'createComment', 'Match createComment method');
        $this->checkMethod('App\\Http\\Controllers\\MatchController', 'deleteComment', 'Match deleteComment method');
        
        // Check MentionController
        $this->checkMethod('App\\Http\\Controllers\\MentionController', 'getUserMentions', 'MentionController getUserMentions method');
        $this->checkMethod('App\\Http\\Controllers\\MentionController', 'getTeamMentions', 'MentionController getTeamMentions method');
        $this->checkMethod('App\\Http\\Controllers\\MentionController', 'getPlayerMentions', 'MentionController getPlayerMentions method');
    }
    
    private function validateServices()
    {
        echo "\n🔧 Validating Services...\n";
        
        // Check MentionService
        $this->checkMethod('App\\Services\\MentionService', 'storeMentions', 'MentionService storeMentions method');
        $this->checkMethod('App\\Services\\MentionService', 'deleteMentions', 'MentionService deleteMentions method');
        $this->checkMethod('App\\Services\\MentionService', 'extractMentions', 'MentionService extractMentions method');
        $this->checkMethod('App\\Services\\MentionService', 'getMentionsForUser', 'MentionService getMentionsForUser method');
        $this->checkMethod('App\\Services\\MentionService', 'getMentionsForTeam', 'MentionService getMentionsForTeam method');
        $this->checkMethod('App\\Services\\MentionService', 'getMentionsForPlayer', 'MentionService getMentionsForPlayer method');
    }
    
    private function validateModels()
    {
        echo "\n📊 Validating Models...\n";
        
        // Check if Mention model exists and has required methods
        $this->checkClass('App\\Models\\Mention', 'Mention model');
        $this->checkMethod('App\\Models\\Mention', 'mentionable', 'Mention mentionable relationship');
        $this->checkMethod('App\\Models\\Mention', 'mentioned', 'Mention mentioned relationship');
        $this->checkMethod('App\\Models\\Mention', 'mentionedBy', 'Mention mentionedBy relationship');
    }
    
    private function validateRoutes()
    {
        echo "\n🛣️  Validating Routes...\n";
        
        try {
            // Get all routes
            $routes = \Illuminate\Support\Facades\Artisan::call('route:list', ['--json' => true]);
            $output = \Illuminate\Support\Facades\Artisan::output();
            $routeList = json_decode($output, true);
            
            $requiredRoutes = [
                'POST api/user/forums/threads' => 'Forum thread creation',
                'POST api/user/forums/threads/{threadId}/reply' => 'Forum reply creation',
                'DELETE api/user/forums/posts/{postId}' => 'Forum post deletion',
                'POST api/user/news/{newsId}/comments' => 'News comment creation',
                'DELETE api/user/news/comments/{commentId}' => 'News comment deletion',
                'POST api/user/matches/{matchId}/comments' => 'Match comment creation',
                'DELETE api/user/matches/comments/{commentId}' => 'Match comment deletion',
                'GET api/users/{id}/mentions' => 'User mentions endpoint',
                'GET api/teams/{id}/mentions' => 'Team mentions endpoint',
                'GET api/players/{id}/mentions' => 'Player mentions endpoint'
            ];
            
            foreach ($requiredRoutes as $route => $description) {
                $found = false;
                foreach ($routeList as $routeData) {
                    $routeString = $routeData['method'] . ' ' . $routeData['uri'];
                    if (strpos($routeString, str_replace('{threadId}', '{', str_replace('{postId}', '{', str_replace('{newsId}', '{', str_replace('{commentId}', '{', str_replace('{matchId}', '{', str_replace('{id}', '{', $route))))))) !== false) {
                        $found = true;
                        break;
                    }
                }
                
                if ($found) {
                    $this->addSuccess("✅ $description route exists");
                } else {
                    $this->addError("❌ $description route missing: $route");
                }
            }
            
        } catch (Exception $e) {
            $this->addError("❌ Could not validate routes: " . $e->getMessage());
        }
    }
    
    private function validateMigrations()
    {
        echo "\n🗄️  Validating Database Migrations...\n";
        
        try {
            // Check if mentions table exists
            $hasTable = \Illuminate\Support\Facades\Schema::hasTable('mentions');
            if ($hasTable) {
                $this->addSuccess("✅ Mentions table exists");
                
                // Check required columns
                $requiredColumns = [
                    'mentionable_type', 'mentionable_id', 'mentioned_type', 'mentioned_id',
                    'mention_text', 'context', 'mentioned_by', 'mentioned_at', 'is_active'
                ];
                
                foreach ($requiredColumns as $column) {
                    if (\Illuminate\Support\Facades\Schema::hasColumn('mentions', $column)) {
                        $this->addSuccess("✅ Mentions table has '$column' column");
                    } else {
                        $this->addError("❌ Mentions table missing '$column' column");
                    }
                }
            } else {
                $this->addError("❌ Mentions table does not exist");
            }
            
        } catch (Exception $e) {
            $this->addError("❌ Could not validate database: " . $e->getMessage());
        }
    }
    
    private function checkClass($className, $description)
    {
        if (class_exists($className)) {
            $this->addSuccess("✅ $description exists");
            return true;
        } else {
            $this->addError("❌ $description missing: $className");
            return false;
        }
    }
    
    private function checkMethod($className, $methodName, $description)
    {
        if (class_exists($className)) {
            if (method_exists($className, $methodName)) {
                $this->addSuccess("✅ $description exists");
                return true;
            } else {
                $this->addError("❌ $description missing in $className");
                return false;
            }
        } else {
            $this->addError("❌ Cannot check $description - class $className does not exist");
            return false;
        }
    }
    
    private function addSuccess($message)
    {
        $this->successes[] = $message;
        echo "  $message\n";
    }
    
    private function addError($message)
    {
        $this->errors[] = $message;
        echo "  $message\n";
    }
    
    private function printReport()
    {
        echo "\n" . "=" . str_repeat("=", 55) . "\n";
        echo "📊 VALIDATION REPORT\n";
        echo "=" . str_repeat("=", 55) . "\n";
        
        echo "\n✅ SUCCESSES (" . count($this->successes) . "):\n";
        foreach ($this->successes as $success) {
            echo "  $success\n";
        }
        
        if (!empty($this->errors)) {
            echo "\n❌ ERRORS (" . count($this->errors) . "):\n";
            foreach ($this->errors as $error) {
                echo "  $error\n";
            }
        }
        
        echo "\n";
        if (empty($this->errors)) {
            echo "🎉 ALL VALIDATIONS PASSED! Forum & Mentions system is properly implemented.\n";
        } else {
            echo "⚠️  " . count($this->errors) . " ISSUES FOUND. Please address these before testing.\n";
        }
        
        echo "=" . str_repeat("=", 55) . "\n";
    }
}

// Run validation if called directly
if (php_sapi_name() === 'cli') {
    $validator = new SimpleForumMentionsValidation();
    $success = $validator->validateSystem();
    exit($success ? 0 : 1);
}