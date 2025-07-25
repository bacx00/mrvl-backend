<?php
namespace App\Http\Controllers;

use App\Models\{Team, Player, News};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImageUploadController extends Controller
{
    private $allowedTypes = ['jpeg', 'jpg', 'png', 'webp'];
    private $maxFileSize = 5120; // 5MB in KB

    // Team Logo Upload
    public function uploadTeamLogo(Request $request, $teamId)
    {
        try {
            $team = Team::findOrFail($teamId);
            
            if (!$request->hasFile('logo')) {
                return response()->json([
                    'success' => false,
                    'message' => 'No logo file provided'
                ], 400);
            }

            $file = $request->file('logo');
            
            if (!$file->isValid()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid file upload. Error: ' . $file->getError()
                ], 400);
            }
            

            // Delete old logo if exists
            if ($team->logo) {
                Storage::disk('public')->delete($team->logo);
            }

            // Simple file storage without processing
            $extension = $file->getClientOriginalExtension();
            $filename = uniqid() . '.' . $extension;
            $directory = 'teams/logos';
            
            // Use manual file move approach
            try {
                $finalPath = $directory . '/' . $filename;
                $destinationPath = storage_path('app/public/' . $finalPath);
                
                // Ensure directory exists
                $destinationDir = dirname($destinationPath);
                if (!is_dir($destinationDir)) {
                    mkdir($destinationDir, 0775, true);
                }
                
                // Move uploaded file
                if (!move_uploaded_file($file->path(), $destinationPath)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to move uploaded file'
                    ], 500);
                }
                
                // Set proper permissions
                chmod($destinationPath, 0644);
                
                $path = $finalPath;
                
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Storage exception: ' . $e->getMessage()
                ], 500);
            }

            $team->update(['logo' => $path]);

            return response()->json([
                'success' => true,
                'message' => 'Team logo uploaded successfully',
                'data' => [
                    'logo' => $path,
                    'logo_url' => Storage::disk('public')->url($path)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload logo: ' . $e->getMessage()
            ], 500);
        }
    }

    // Team Flag Upload
    public function uploadTeamFlag(Request $request, $teamId)
    {
        $request->validate([
            'flag' => 'required|image|mimes:jpeg,jpg,png,webp|max:' . $this->maxFileSize,
        ]);

        try {
            $team = Team::findOrFail($teamId);
            
            // Delete old flag if exists
            if ($team->flag) {
                Storage::disk('public')->delete($team->flag);
            }

            $file = $request->file('flag');
            $path = $this->processAndStoreImage($file, 'teams/flags', [
                'width' => 64,
                'height' => 42,
                'maintain_ratio' => false // Flags should maintain specific ratio
            ]);

            $team->update(['flag' => $path]);

            return response()->json([
                'success' => true,
                'message' => 'Team flag uploaded successfully',
                'data' => [
                    'flag' => $path,
                    'flag_url' => Storage::disk('public')->url($path)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload flag: ' . $e->getMessage()
            ], 500);
        }
    }

    // Team Coach Picture Upload
    public function uploadTeamCoach(Request $request, $teamId)
    {
        $request->validate([
            'coach_picture' => 'required|image|mimes:jpeg,jpg,png,webp|max:' . $this->maxFileSize,
        ]);

        try {
            $team = Team::findOrFail($teamId);
            
            // Delete old coach picture if exists
            if (isset($team->coach_picture) && $team->coach_picture) {
                Storage::disk('public')->delete($team->coach_picture);
            }

            $file = $request->file('coach_picture');
            $path = $this->processAndStoreImage($file, 'teams/coaches', [
                'width' => 128,
                'height' => 128,
                'maintain_ratio' => true // Coach pictures should be square
            ]);

            // Use database query instead of Eloquent to avoid potential column issues
            DB::table('teams')->where('id', $teamId)->update([
                'coach_picture' => $path,
                'updated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Team coach picture uploaded successfully',
                'data' => [
                    'coach_picture' => $path,
                    'coach_picture_url' => Storage::disk('public')->url($path)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload coach picture: ' . $e->getMessage()
            ], 500);
        }
    }

    // Player Avatar Upload
    public function uploadPlayerAvatar(Request $request, $playerId)
    {
        try {
            $player = Player::findOrFail($playerId);
            
            if (!$request->hasFile('avatar')) {
                return response()->json([
                    'success' => false,
                    'message' => 'No avatar file provided'
                ], 400);
            }

            $file = $request->file('avatar');
            
            if (!$file->isValid()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid file upload. Error: ' . $file->getError()
                ], 400);
            }

            // Delete old avatar if exists
            if ($player->avatar) {
                Storage::disk('public')->delete($player->avatar);
            }

            // Simple file storage without processing
            $extension = $file->getClientOriginalExtension();
            $filename = uniqid() . '.' . $extension;
            $directory = 'players/avatars';
            
            // Use manual file move approach
            try {
                $finalPath = $directory . '/' . $filename;
                $destinationPath = storage_path('app/public/' . $finalPath);
                
                // Ensure directory exists
                $destinationDir = dirname($destinationPath);
                if (!is_dir($destinationDir)) {
                    mkdir($destinationDir, 0775, true);
                }
                
                // Move uploaded file
                if (!move_uploaded_file($file->path(), $destinationPath)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to move uploaded file'
                    ], 500);
                }
                
                // Set proper permissions
                chmod($destinationPath, 0644);
                
                $path = $finalPath;
                
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Storage exception: ' . $e->getMessage()
                ], 500);
            }

            $player->update(['avatar' => $path]);

            return response()->json([
                'success' => true,
                'message' => 'Player avatar uploaded successfully',
                'data' => [
                    'avatar' => $path,
                    'avatar_url' => Storage::disk('public')->url($path)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload avatar: ' . $e->getMessage()
            ], 500);
        }
    }

    // News Featured Image Upload
    public function uploadNewsFeaturedImage(Request $request, $newsId)
    {
        $request->validate([
            'featured_image' => 'required|image|mimes:jpeg,jpg,png,webp|max:' . $this->maxFileSize,
        ]);

        try {
            $news = News::findOrFail($newsId);
            
            // Check permissions
            if (!$news->canBeEditedBy(auth()->user())) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to edit this article'
                ], 403);
            }

            // Delete old featured image if exists
            if ($news->featured_image) {
                Storage::disk('public')->delete($news->featured_image);
            }

            $file = $request->file('featured_image');
            $path = $this->processAndStoreImage($file, 'news/featured', [
                'width' => 800,
                'height' => 450,
                'maintain_ratio' => true
            ]);

            $news->update(['featured_image' => $path]);

            return response()->json([
                'success' => true,
                'message' => 'Featured image uploaded successfully',
                'data' => [
                    'featured_image' => $path,
                    'featured_image_url' => Storage::disk('public')->url($path)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload featured image: ' . $e->getMessage()
            ], 500);
        }
    }

    // News Gallery Images Upload
    public function uploadNewsGalleryImages(Request $request, News $news)
    {
        $request->validate([
            'images' => 'required|array|max:10',
            'images.*' => 'image|mimes:jpeg,jpg,png,webp|max:' . $this->maxFileSize,
        ]);

        try {
            // Check permissions
            if (!$news->canBeEditedBy(auth()->user())) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to edit this article'
                ], 403);
            }

            $uploadedImages = [];
            $currentGallery = $news->gallery ?? [];

            foreach ($request->file('images') as $file) {
                $path = $this->processAndStoreImage($file, 'news/gallery', [
                    'width' => 800,
                    'height' => 600,
                    'maintain_ratio' => true
                ]);
                
                $uploadedImages[] = $path;
                $currentGallery[] = $path;
            }

            $news->update(['gallery' => $currentGallery]);

            return response()->json([
                'success' => true,
                'message' => 'Gallery images uploaded successfully',
                'data' => [
                    'uploaded_images' => $uploadedImages,
                    'gallery' => $currentGallery,
                    'gallery_urls' => collect($currentGallery)->map(function($image) {
                        return Storage::disk('public')->url($image);
                    })
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload gallery images: ' . $e->getMessage()
            ], 500);
        }
    }

    // Remove News Gallery Image
    public function removeNewsGalleryImage(Request $request, News $news)
    {
        $request->validate([
            'image_path' => 'required|string'
        ]);

        try {
            // Check permissions
            if (!$news->canBeEditedBy(auth()->user())) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to edit this article'
                ], 403);
            }

            $currentGallery = $news->gallery ?? [];
            $imagePath = $request->image_path;

            // Remove from gallery array
            $newGallery = array_values(array_filter($currentGallery, function($image) use ($imagePath) {
                return $image !== $imagePath;
            }));

            // Delete file from storage
            Storage::disk('public')->delete($imagePath);

            $news->update(['gallery' => $newGallery]);

            return response()->json([
                'success' => true,
                'message' => 'Image removed from gallery successfully',
                'data' => [
                    'gallery' => $newGallery,
                    'gallery_urls' => collect($newGallery)->map(function($image) {
                        return Storage::disk('public')->url($image);
                    })
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove image: ' . $e->getMessage()
            ], 500);
        }
    }

    // Private helper method to process and store images
    private function processAndStoreImage($file, $directory, $options = [])
    {
        try {
            // Validate file
            if (!$file || !$file->isValid()) {
                throw new \Exception('Invalid file upload');
            }

            // Generate unique filename
            $extension = $file->getClientOriginalExtension();
            if (empty($extension)) {
                $extension = 'png'; // Default extension
            }
            
            $filename = Str::random(40) . '.' . $extension;
            $fullPath = $directory . '/' . $filename;

            // Create directory if it doesn't exist
            $fullDirectoryPath = storage_path('app/public/' . $directory);
            if (!file_exists($fullDirectoryPath)) {
                mkdir($fullDirectoryPath, 0755, true);
            }

            // Try multiple methods to store the file
            $stored = false;
            
            // Method 1: Use Laravel's storeAs
            try {
                $result = $file->storeAs($directory, $filename, 'public');
                if ($result) {
                    $stored = true;
                }
            } catch (\Exception $e) {
                \Log::warning('storeAs failed: ' . $e->getMessage());
            }
            
            // Method 2: Use move method if storeAs failed
            if (!$stored) {
                try {
                    $destinationPath = storage_path('app/public/' . $fullPath);
                    if ($file->move(dirname($destinationPath), basename($destinationPath))) {
                        $stored = true;
                    }
                } catch (\Exception $e) {
                    \Log::warning('move failed: ' . $e->getMessage());
                }
            }
            
            // Method 3: Use file_put_contents as last resort
            if (!$stored) {
                try {
                    $destinationPath = storage_path('app/public/' . $fullPath);
                    $contents = file_get_contents($file->getRealPath());
                    if ($contents !== false && file_put_contents($destinationPath, $contents) !== false) {
                        $stored = true;
                    }
                } catch (\Exception $e) {
                    \Log::warning('file_put_contents failed: ' . $e->getMessage());
                }
            }
            
            if (!$stored) {
                throw new \Exception('All file storage methods failed');
            }

            // Verify file was stored
            $finalPath = storage_path('app/public/' . $fullPath);
            if (!file_exists($finalPath)) {
                throw new \Exception('File not found after upload: ' . $finalPath);
            }

            // Set proper permissions
            chmod($finalPath, 0644);

            return $fullPath;
        } catch (\Exception $e) {
            \Log::error('processAndStoreImage error: ' . $e->getMessage());
            throw $e;
        }
    }


    // Team Banner Upload
    public function uploadTeamBanner(Request $request, $teamId)
    {
        try {
            $team = Team::findOrFail($teamId);
            
            if (!$request->hasFile('banner')) {
                return response()->json([
                    'success' => false,
                    'message' => 'No banner file provided'
                ], 400);
            }

            $file = $request->file('banner');
            
            if (!$file->isValid()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid file upload. Error: ' . $file->getError()
                ], 400);
            }

            // Delete old banner if exists
            if ($team->banner) {
                Storage::disk('public')->delete($team->banner);
            }

            // Simple file storage without processing
            $extension = $file->getClientOriginalExtension();
            $filename = uniqid() . '.' . $extension;
            $directory = 'teams/banners';
            
            // Use manual file move approach
            try {
                $finalPath = $directory . '/' . $filename;
                $destinationPath = storage_path('app/public/' . $finalPath);
                
                // Ensure directory exists
                $destinationDir = dirname($destinationPath);
                if (!is_dir($destinationDir)) {
                    mkdir($destinationDir, 0775, true);
                }
                
                // Move uploaded file
                if (!move_uploaded_file($file->path(), $destinationPath)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to move uploaded file'
                    ], 500);
                }
                
                // Set proper permissions
                chmod($destinationPath, 0644);
                
                $path = $finalPath;
                
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Storage exception: ' . $e->getMessage()
                ], 500);
            }

            $team->update(['banner' => $path]);

            return response()->json([
                'success' => true,
                'message' => 'Team banner uploaded successfully',
                'data' => [
                    'banner' => $path,
                    'banner_url' => Storage::disk('public')->url($path)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload banner: ' . $e->getMessage()
            ], 500);
        }
    }

    // Event Banner Upload
    public function uploadEventBanner(Request $request, $eventId)
    {
        $event = \App\Models\Event::findOrFail($eventId);
        
        $request->validate([
            'banner' => 'required|image|mimes:jpeg,jpg,png,webp|max:' . $this->maxFileSize,
        ]);

        try {
            // Delete old banner if exists
            if ($event->banner) {
                Storage::disk('public')->delete($event->banner);
            }

            $file = $request->file('banner');
            $path = $this->processAndStoreImage($file, 'events/banners', [
                'width' => 1200,
                'height' => 400,
                'maintain_ratio' => true
            ]);

            $event->update(['banner' => $path]);

            return response()->json([
                'success' => true,
                'message' => 'Event banner uploaded successfully',
                'data' => [
                    'banner' => $path,
                    'banner_url' => Storage::disk('public')->url($path)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload banner: ' . $e->getMessage()
            ], 500);
        }
    }

    public function uploadEventLogo(Request $request, $eventId)
    {
        try {
            $event = \App\Models\Event::findOrFail($eventId);
            
            // Check if file exists
            if (!$request->hasFile('logo')) {
                return response()->json([
                    'success' => false,
                    'message' => 'No logo file provided'
                ], 400);
            }
            
            $file = $request->file('logo');
            
            // Validate file
            if (!$file->isValid()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid file upload'
                ], 400);
            }
            
            // Validate file type and size
            $validation = validator(['logo' => $file], [
                'logo' => 'required|image|mimes:jpeg,jpg,png,webp|max:' . $this->maxFileSize
            ]);
            
            if ($validation->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validation->errors()
                ], 422);
            }

            // Delete old logo if exists
            if ($event->logo && Storage::disk('public')->exists($event->logo)) {
                Storage::disk('public')->delete($event->logo);
            }

            // Simple direct storage method
            $extension = $file->getClientOriginalExtension() ?: 'png';
            $filename = 'event_' . $eventId . '_' . time() . '_' . Str::random(10) . '.' . $extension;
            $directory = 'events/logos';
            $fullPath = $directory . '/' . $filename;
            
            // Ensure directory exists
            $fullDirectoryPath = storage_path('app/public/' . $directory);
            if (!file_exists($fullDirectoryPath)) {
                mkdir($fullDirectoryPath, 0755, true);
            }
            
            // Store the file using Laravel's storage
            $stored = false;
            try {
                $path = $file->storeAs($directory, $filename, 'public');
                if ($path) {
                    $stored = true;
                }
            } catch (\Exception $e) {
                \Log::error('storeAs failed: ' . $e->getMessage());
            }
            
            // Fallback: Direct file move
            if (!$stored) {
                $destinationPath = storage_path('app/public/' . $fullPath);
                if ($file->move(dirname($destinationPath), basename($destinationPath))) {
                    $path = $fullPath;
                    $stored = true;
                }
            }
            
            if (!$stored) {
                throw new \Exception('Failed to store file after trying multiple methods');
            }

            // Update event with new logo path
            $event->logo = $path;
            $event->save();

            return response()->json([
                'success' => true,
                'message' => 'Event logo uploaded successfully',
                'data' => [
                    'logo' => $path,
                    'logo_url' => Storage::disk('public')->url($path)
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Event logo upload error: ' . $e->getMessage() . ' - ' . $e->getTraceAsString());
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload logo: ' . $e->getMessage()
            ], 500);
        }
    }

    // Hero Images Upload
    public function uploadHeroImages(Request $request, $heroId)
    {
        // Heroes are managed differently - they use predefined images in the public folder
        // This method would be for custom hero images if needed
        return response()->json([
            'success' => false,
            'message' => 'Hero images are managed through the system. Please contact admin for hero image updates.'
        ], 400);
    }

    // User Avatar Upload
    public function uploadUserAvatar(Request $request, $userId)
    {
        $user = \App\Models\User::findOrFail($userId);
        
        // Check if user can edit this profile
        if (auth()->id() !== $user->id && !auth()->user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to edit this profile'
            ], 403);
        }

        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,jpg,png,webp|max:' . $this->maxFileSize,
        ]);

        try {
            // Delete old avatar if exists
            if ($user->avatar && !str_starts_with($user->avatar, '/images/heroes/')) {
                Storage::disk('public')->delete($user->avatar);
            }

            $file = $request->file('avatar');
            $path = $this->processAndStoreImage($file, 'users/avatars', [
                'width' => 300,
                'height' => 300,
                'maintain_ratio' => true
            ]);

            $user->update(['avatar' => $path]);

            return response()->json([
                'success' => true,
                'message' => 'Avatar uploaded successfully',
                'data' => [
                    'avatar' => $path,
                    'avatar_url' => Storage::disk('public')->url($path)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload avatar: ' . $e->getMessage()
            ], 500);
        }
    }

    // Delete Team Logo
    public function deleteTeamLogo(Team $team)
    {
        try {
            if ($team->logo) {
                Storage::disk('public')->delete($team->logo);
                $team->update(['logo' => null]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Team logo deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete logo: ' . $e->getMessage()
            ], 500);
        }
    }

    // Delete Team Flag
    public function deleteTeamFlag(Team $team)
    {
        try {
            if ($team->flag) {
                Storage::disk('public')->delete($team->flag);
                $team->update(['flag' => null]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Team flag deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete flag: ' . $e->getMessage()
            ], 500);
        }
    }

    // Delete Team Banner
    public function deleteTeamBanner(Team $team)
    {
        try {
            if ($team->banner) {
                Storage::disk('public')->delete($team->banner);
                $team->update(['banner' => null]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Team banner deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete banner: ' . $e->getMessage()
            ], 500);
        }
    }

    // Delete Player Avatar
    public function deletePlayerAvatar(Player $player)
    {
        try {
            if ($player->avatar) {
                Storage::disk('public')->delete($player->avatar);
                $player->update(['avatar' => null]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Player avatar deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete avatar: ' . $e->getMessage()
            ], 500);
        }
    }

    // Delete Event Banner
    public function deleteEventBanner($eventId)
    {
        $event = \App\Models\Event::findOrFail($eventId);
        
        try {
            if ($event->image) {
                Storage::disk('public')->delete($event->image);
                $event->update(['image' => null]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Event banner deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete banner: ' . $e->getMessage()
            ], 500);
        }
    }

    // Delete User Avatar
    public function deleteUserAvatar($userId)
    {
        $user = \App\Models\User::findOrFail($userId);
        
        // Check if user can edit this profile
        if (auth()->id() !== $user->id && !auth()->user()->hasRole('admin')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to edit this profile'
            ], 403);
        }

        try {
            if ($user->avatar && !str_starts_with($user->avatar, '/images/heroes/')) {
                Storage::disk('public')->delete($user->avatar);
                $user->update(['avatar' => null]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Avatar deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete avatar: ' . $e->getMessage()
            ], 500);
        }
    }
}
