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

    // Team Logo Upload
    public function uploadTeamLogo(Request $request, Team $team)
    {
        $request->validate([
            'logo' => 'required|image|mimes:jpeg,jpg,png,webp|max:' . $this->maxFileSize,
        ]);

        try {
            // Delete old logo if exists
            if ($team->logo) {
                Storage::disk('public')->delete($team->logo);
            }

            $file = $request->file('logo');
            $path = $this->processAndStoreImage($file, 'teams/logos', [
                'width' => 200,
                'height' => 200,
                'maintain_ratio' => true
            ]);

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
    public function uploadPlayerAvatar(Request $request, Player $player)
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,jpg,png,webp|max:' . $this->maxFileSize,
        ]);

        try {
            // Delete old avatar if exists
            if ($player->avatar) {
                Storage::disk('public')->delete($player->avatar);
            }

            $file = $request->file('avatar');
            $path = $this->processAndStoreImage($file, 'players/avatars', [
                'width' => 300,
                'height' => 300,
                'maintain_ratio' => true
            ]);

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
        $request->validate([
            'featured_image' => 'required|image|mimes:jpeg,jpg,png,webp|max:' . $this->maxFileSize,
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
        // Generate unique filename
        $extension = $file->getClientOriginalExtension();
        $filename = Str::random(40) . '.' . $extension;
        $fullPath = $directory . '/' . $filename;

        // Create directory if it doesn't exist
        Storage::disk('public')->makeDirectory($directory);

        // Store original file
        $file->storeAs($directory, $filename, 'public');

        // If image processing is available and options are provided, resize
        if (class_exists('Intervention\Image\Facades\Image') && !empty($options)) {
            $this->resizeImage(Storage::disk('public')->path($fullPath), $options);
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
}
