<?php
namespace App\Http\Controllers;

use App\Models\{Team, Player, News};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;

class ImageUploadController extends Controller
{
    private $allowedTypes = ['jpeg', 'jpg', 'png', 'webp'];
    private $maxFileSize = 5120; // 5MB in KB
    
    /**
     * Check if user is authenticated and authorized for image uploads
     */
    private function checkAuthAndPermissions($requiredPermission = 'manage-events')
    {
        $user = auth('api')->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required. Please provide a valid Bearer token.'
            ], 401);
        }
        
        // Check if user has required permission or admin role
        if (!$user->hasRole(['admin', 'super_admin']) && !$user->hasPermissionTo($requiredPermission)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to upload images. Admin role required.'
            ], 403);
        }
        
        return null; // No error, user is authorized
    }

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
    public function uploadTeamFlag(Request $request, Team $team)
    {
        $request->validate([
            'flag' => 'required|image|mimes:jpeg,jpg,png,webp|max:' . $this->maxFileSize,
        ]);

        try {
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
    public function uploadNewsFeaturedImage(Request $request, News $news)
    {
        // Support both 'featured_image' and 'image' field names for backward compatibility
        $fieldName = $request->hasFile('featured_image') ? 'featured_image' : 'image';
        
        $request->validate([
            $fieldName => 'required|image|mimes:jpeg,jpg,png,webp|max:' . $this->maxFileSize,
        ]);

        try {
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

            $file = $request->file($fieldName);
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

    // News General Images Upload (for backward compatibility)
    public function uploadNewsImages(Request $request, News $news)
    {
        // Check if this is a featured image request
        if ($request->hasFile('featured_image') || $request->hasFile('image')) {
            return $this->uploadNewsFeaturedImage($request, $news);
        }
        
        // Otherwise, handle as gallery images
        return $this->uploadNewsGalleryImages($request, $news);
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
        // Generate unique filename
        $extension = $file->getClientOriginalExtension();
        $filename = Str::random(40) . '.' . $extension;
        $fullPath = $directory . '/' . $filename;

        // Create directory if it doesn't exist
        Storage::disk('public')->makeDirectory($directory);

        // Store original file
        $result = $file->storeAs($directory, $filename, 'public');
        
        if (!$result) {
            throw new \Exception('File storage failed - upload returned false');
        }

        // Verify file was stored
        if (!Storage::disk('public')->exists($fullPath)) {
            throw new \Exception('File storage failed - file not found after upload');
        }

        // If image processing is available and options are provided, resize
        if (class_exists('Intervention\Image\Facades\Image') && !empty($options)) {
            try {
                $this->resizeImage(Storage::disk('public')->path($fullPath), $options);
            } catch (\Exception $e) {
                \Log::warning('Image resizing failed: ' . $e->getMessage());
            }
        }

        return $fullPath;
    }

    // Private helper method to resize images (optional - requires intervention/image)
    private function resizeImage($fullPath, $options)
    {
        try {
            $image = Image::make($fullPath);
            
            if (isset($options['width']) && isset($options['height'])) {
                if ($options['maintain_ratio'] ?? true) {
                    $image->resize($options['width'], $options['height'], function ($constraint) {
                        $constraint->aspectRatio();
                        $constraint->upsize();
                    });
                } else {
                    $image->resize($options['width'], $options['height']);
                }
            }
            
            $image->save($fullPath, 85); // Save with 85% quality
        } catch (\Exception $e) {
            // If image processing fails, continue with original image
            \Log::warning('Image resizing failed: ' . $e->getMessage());
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
            if ($event->image) {
                Storage::disk('public')->delete($event->image);
            }

            $file = $request->file('banner');
            $path = $this->processAndStoreImage($file, 'events/banners', [
                'width' => 1200,
                'height' => 400,
                'maintain_ratio' => true
            ]);

            $event->update(['image' => $path]);

            return response()->json([
                'success' => true,
                'message' => 'Event banner uploaded successfully',
                'data' => [
                    'image' => $path,
                    'image_url' => Storage::disk('public')->url($path)
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
        // Check authentication
        $user = auth('api')->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required. Please provide a valid Bearer token.'
            ], 401);
        }
        
        // Check if user has admin role
        if (!$user->hasRole(['admin', 'super_admin']) && !$user->hasPermissionTo('manage-events')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to upload event images. Admin role required.'
            ], 403);
        }
        
        $event = \App\Models\Event::findOrFail($eventId);
        
        $request->validate([
            'logo' => 'required|image|mimes:jpeg,jpg,png,webp|max:' . $this->maxFileSize,
        ]);

        try {
            // Delete old logo if exists
            if ($event->logo) {
                $logoPath = str_replace(url('storage/'), '', $event->logo);
                Storage::disk('public')->delete($logoPath);
            }

            $file = $request->file('logo');
            
            // Simple file storage without complex processing
            $extension = $file->getClientOriginalExtension();
            $filename = 'event_' . $eventId . '_' . time() . '_' . Str::random(10) . '.' . $extension;
            $directory = 'events/logos';
            
            // Ensure directory exists
            $fullDirectory = storage_path('app/public/' . $directory);
            if (!is_dir($fullDirectory)) {
                mkdir($fullDirectory, 0775, true);
            }
            
            $finalPath = $directory . '/' . $filename;
            $destinationPath = storage_path('app/public/' . $finalPath);
            
            // Move uploaded file
            if (!move_uploaded_file($file->path(), $destinationPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to move uploaded file'
                ], 500);
            }
            
            // Set proper permissions
            chmod($destinationPath, 0644);
            
            // Generate full URL for the logo
            $logoUrl = url('storage/' . $finalPath);
            
            // Update event with logo URL
            $event->update(['logo' => $logoUrl]);

            return response()->json([
                'success' => true,
                'message' => 'Event logo uploaded successfully',
                'data' => [
                    'logo' => $finalPath,
                    'logo_url' => $logoUrl
                ]
            ]);

        } catch (\Exception $e) {
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
