<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\ApiResponseController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class AdminNewsMediaController extends ApiResponseController
{
    /**
     * Upload featured image for news article
     */
    public function uploadFeaturedImage(Request $request)
    {
        try {
            // Check authorization
            if (!auth('api')->check() || !auth('api')->user()->hasAnyRole(['admin', 'moderator'])) {
                return $this->errorResponse('Unauthorized - Admin or Moderator access required', 403);
            }

            $validator = Validator::make($request->all(), [
                'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120', // Max 5MB
                'news_id' => 'nullable|exists:news,id',
                'resize_width' => 'nullable|integer|min:100|max:2000',
                'resize_height' => 'nullable|integer|min:100|max:2000',
                'quality' => 'nullable|integer|min:60|max:100',
                'alt_text' => 'nullable|string|max:255',
                'caption' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', 422, $validator->errors());
            }

            $image = $request->file('image');
            $newsId = $request->news_id;

            // Generate unique filename
            $filename = $this->generateImageFilename($image->getClientOriginalExtension());
            $imagePath = 'news/featured/' . $filename;

            // Process and optimize image
            $processedImage = $this->processImage(
                $image,
                $request->get('resize_width', 1200),
                $request->get('resize_height', 800),
                $request->get('quality', 85)
            );

            // Store the image
            $stored = Storage::disk('public')->put($imagePath, $processedImage);
            
            if (!$stored) {
                return $this->errorResponse('Failed to upload image', 500);
            }

            // Update news article if news_id provided
            if ($newsId) {
                DB::table('news')->where('id', $newsId)->update([
                    'featured_image' => $imagePath,
                    'updated_at' => now()
                ]);
            }

            // Store image metadata
            $imageData = [
                'filename' => $filename,
                'path' => $imagePath,
                'url' => asset('storage/' . $imagePath),
                'size' => Storage::disk('public')->size($imagePath),
                'dimensions' => $this->getImageDimensions(Storage::disk('public')->path($imagePath)),
                'alt_text' => $request->alt_text,
                'caption' => $request->caption,
                'news_id' => $newsId,
                'uploaded_by' => auth('api')->id(),
                'upload_type' => 'featured_image'
            ];

            return $this->createdResponse($imageData, 'Featured image uploaded successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Error uploading image: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Upload multiple gallery images for news article
     */
    public function uploadGalleryImages(Request $request)
    {
        try {
            // Check authorization
            if (!auth('api')->check() || !auth('api')->user()->hasAnyRole(['admin', 'moderator'])) {
                return $this->errorResponse('Unauthorized - Admin or Moderator access required', 403);
            }

            $validator = Validator::make($request->all(), [
                'images' => 'required|array|min:1|max:10',
                'images.*' => 'image|mimes:jpeg,png,jpg,gif,webp|max:5120', // Max 5MB each
                'news_id' => 'nullable|exists:news,id',
                'resize_width' => 'nullable|integer|min:100|max:2000',
                'resize_height' => 'nullable|integer|min:100|max:2000',
                'quality' => 'nullable|integer|min:60|max:100'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', 422, $validator->errors());
            }

            $newsId = $request->news_id;
            $uploadedImages = [];
            $galleryPaths = [];

            foreach ($request->file('images') as $index => $image) {
                try {
                    // Generate unique filename
                    $filename = $this->generateImageFilename($image->getClientOriginalExtension());
                    $imagePath = 'news/gallery/' . $filename;

                    // Process and optimize image
                    $processedImage = $this->processImage(
                        $image,
                        $request->get('resize_width', 800),
                        $request->get('resize_height', 600),
                        $request->get('quality', 85)
                    );

                    // Store the image
                    $stored = Storage::disk('public')->put($imagePath, $processedImage);
                    
                    if ($stored) {
                        $galleryPaths[] = $imagePath;
                        
                        $uploadedImages[] = [
                            'filename' => $filename,
                            'path' => $imagePath,
                            'url' => asset('storage/' . $imagePath),
                            'size' => Storage::disk('public')->size($imagePath),
                            'dimensions' => $this->getImageDimensions(Storage::disk('public')->path($imagePath)),
                            'order' => $index + 1
                        ];
                    }

                } catch (\Exception $e) {
                    \Log::error("Error processing gallery image {$index}: " . $e->getMessage());
                }
            }

            if (empty($uploadedImages)) {
                return $this->errorResponse('Failed to upload any images', 500);
            }

            // Update news article gallery if news_id provided
            if ($newsId) {
                // Get existing gallery
                $existingNews = DB::table('news')->where('id', $newsId)->first();
                $existingGallery = $existingNews->gallery ? json_decode($existingNews->gallery, true) : [];
                
                // Merge with new images
                $updatedGallery = array_merge($existingGallery, $galleryPaths);

                DB::table('news')->where('id', $newsId)->update([
                    'gallery' => json_encode($updatedGallery),
                    'updated_at' => now()
                ]);
            }

            return $this->createdResponse([
                'uploaded_count' => count($uploadedImages),
                'images' => $uploadedImages
            ], count($uploadedImages) . ' gallery images uploaded successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Error uploading gallery images: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Delete an image from news article
     */
    public function deleteImage(Request $request, $newsId)
    {
        try {
            // Check authorization
            if (!auth('api')->check() || !auth('api')->user()->hasAnyRole(['admin', 'moderator'])) {
                return $this->errorResponse('Unauthorized - Admin or Moderator access required', 403);
            }

            $validator = Validator::make($request->all(), [
                'image_type' => 'required|in:featured,gallery',
                'image_path' => 'required_if:image_type,gallery|string',
                'image_index' => 'nullable|integer|min:0'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', 422, $validator->errors());
            }

            $article = DB::table('news')->where('id', $newsId)->first();
            if (!$article) {
                return $this->errorResponse('News article not found', 404);
            }

            if ($request->image_type === 'featured') {
                if ($article->featured_image) {
                    // Delete featured image from storage
                    Storage::disk('public')->delete($article->featured_image);
                    
                    // Update database
                    DB::table('news')->where('id', $newsId)->update([
                        'featured_image' => null,
                        'updated_at' => now()
                    ]);
                }
                
                return $this->successResponse(null, 'Featured image deleted successfully');
                
            } else {
                // Handle gallery image deletion
                $gallery = $article->gallery ? json_decode($article->gallery, true) : [];
                
                if (empty($gallery)) {
                    return $this->errorResponse('No gallery images found', 404);
                }

                $imagePath = $request->image_path;
                $imageIndex = array_search($imagePath, $gallery);
                
                if ($imageIndex === false) {
                    return $this->errorResponse('Image not found in gallery', 404);
                }

                // Delete image from storage
                Storage::disk('public')->delete($imagePath);
                
                // Remove from gallery array
                unset($gallery[$imageIndex]);
                $gallery = array_values($gallery); // Reindex array

                // Update database
                DB::table('news')->where('id', $newsId)->update([
                    'gallery' => !empty($gallery) ? json_encode($gallery) : null,
                    'updated_at' => now()
                ]);

                return $this->successResponse(null, 'Gallery image deleted successfully');
            }

        } catch (\Exception $e) {
            return $this->errorResponse('Error deleting image: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Upload video thumbnail or process video embed
     */
    public function uploadVideoThumbnail(Request $request)
    {
        try {
            // Check authorization
            if (!auth('api')->check() || !auth('api')->user()->hasAnyRole(['admin', 'moderator'])) {
                return $this->errorResponse('Unauthorized - Admin or Moderator access required', 403);
            }

            $validator = Validator::make($request->all(), [
                'thumbnail' => 'required|image|mimes:jpeg,png,jpg,webp|max:2048', // Max 2MB
                'video_url' => 'required|url',
                'video_platform' => 'required|in:youtube,twitch-clip,twitch-video,twitter,generic',
                'news_id' => 'nullable|exists:news,id',
                'title' => 'nullable|string|max:255',
                'duration' => 'nullable|string|max:20'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', 422, $validator->errors());
            }

            $thumbnail = $request->file('thumbnail');
            $newsId = $request->news_id;

            // Generate unique filename for thumbnail
            $filename = $this->generateImageFilename($thumbnail->getClientOriginalExtension());
            $thumbnailPath = 'news/video-thumbnails/' . $filename;

            // Process thumbnail image
            $processedThumbnail = $this->processImage($thumbnail, 480, 360, 80);

            // Store the thumbnail
            $stored = Storage::disk('public')->put($thumbnailPath, $processedThumbnail);
            
            if (!$stored) {
                return $this->errorResponse('Failed to upload thumbnail', 500);
            }

            // Extract video ID from URL
            $videoId = $this->extractVideoId($request->video_url, $request->video_platform);

            // Create video embed data
            $videoData = [
                'platform' => $request->video_platform,
                'video_id' => $videoId,
                'original_url' => $request->video_url,
                'embed_url' => $this->generateEmbedUrl($request->video_url, $request->video_platform),
                'thumbnail' => asset('storage/' . $thumbnailPath),
                'title' => $request->title,
                'duration' => $request->duration,
                'uploaded_by' => auth('api')->id()
            ];

            // Add to news article if news_id provided
            if ($newsId) {
                $existingNews = DB::table('news')->where('id', $newsId)->first();
                $existingVideos = $existingNews->videos ? json_decode($existingNews->videos, true) : [];
                
                $existingVideos[] = $videoData;

                DB::table('news')->where('id', $newsId)->update([
                    'videos' => json_encode($existingVideos),
                    'updated_at' => now()
                ]);
            }

            return $this->createdResponse($videoData, 'Video thumbnail uploaded successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Error uploading video thumbnail: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get media library for news articles
     */
    public function getMediaLibrary(Request $request)
    {
        try {
            // Check authorization
            if (!auth('api')->check() || !auth('api')->user()->hasAnyRole(['admin', 'moderator'])) {
                return $this->errorResponse('Unauthorized - Admin or Moderator access required', 403);
            }

            $mediaType = $request->get('type', 'all'); // all, images, videos
            $perPage = min($request->get('per_page', 20), 100);
            
            $images = [];
            $videos = [];

            // Get images from storage
            if (in_array($mediaType, ['all', 'images'])) {
                $imageDirs = ['news/featured/', 'news/gallery/', 'news/video-thumbnails/'];
                
                foreach ($imageDirs as $dir) {
                    $files = Storage::disk('public')->files($dir);
                    
                    foreach ($files as $file) {
                        if ($this->isImageFile($file)) {
                            $images[] = [
                                'type' => 'image',
                                'filename' => basename($file),
                                'path' => $file,
                                'url' => asset('storage/' . $file),
                                'size' => Storage::disk('public')->size($file),
                                'last_modified' => Storage::disk('public')->lastModified($file),
                                'category' => $this->getImageCategory($file)
                            ];
                        }
                    }
                }
            }

            // Get videos from database
            if (in_array($mediaType, ['all', 'videos'])) {
                $videoEmbeds = DB::table('news_video_embeds as nve')
                    ->leftJoin('news as n', 'nve.news_id', '=', 'n.id')
                    ->select([
                        'nve.*',
                        'n.title as news_title'
                    ])
                    ->orderBy('nve.created_at', 'desc')
                    ->get();

                foreach ($videoEmbeds as $video) {
                    $videos[] = [
                        'type' => 'video',
                        'id' => $video->id,
                        'platform' => $video->platform,
                        'video_id' => $video->video_id,
                        'original_url' => $video->original_url,
                        'embed_url' => $video->embed_url,
                        'thumbnail' => $video->thumbnail,
                        'title' => $video->title,
                        'duration' => $video->duration,
                        'news_title' => $video->news_title,
                        'created_at' => $video->created_at
                    ];
                }
            }

            // Combine and sort by date
            $allMedia = array_merge($images, $videos);
            usort($allMedia, function($a, $b) {
                $timeA = isset($a['last_modified']) ? $a['last_modified'] : strtotime($a['created_at']);
                $timeB = isset($b['last_modified']) ? $b['last_modified'] : strtotime($b['created_at']);
                return $timeB - $timeA;
            });

            // Paginate manually
            $offset = ($request->get('page', 1) - 1) * $perPage;
            $paginatedMedia = array_slice($allMedia, $offset, $perPage);
            
            return $this->successResponse([
                'data' => $paginatedMedia,
                'pagination' => [
                    'current_page' => $request->get('page', 1),
                    'per_page' => $perPage,
                    'total' => count($allMedia),
                    'last_page' => ceil(count($allMedia) / $perPage)
                ]
            ], 'Media library retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Error fetching media library: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Clean up unused media files
     */
    public function cleanupUnusedMedia(Request $request)
    {
        try {
            // Check authorization - Only admins can cleanup
            if (!auth('api')->check() || !auth('api')->user()->hasRole('admin')) {
                return $this->errorResponse('Unauthorized - Admin access required', 403);
            }

            $dryRun = $request->boolean('dry_run', true);
            $cleanupResults = [
                'images_found' => 0,
                'images_referenced' => 0,
                'images_unused' => 0,
                'images_deleted' => 0,
                'space_freed' => 0,
                'errors' => []
            ];

            // Get all image files
            $imageDirs = ['news/featured/', 'news/gallery/', 'news/video-thumbnails/'];
            $allImages = [];
            
            foreach ($imageDirs as $dir) {
                $files = Storage::disk('public')->files($dir);
                foreach ($files as $file) {
                    if ($this->isImageFile($file)) {
                        $allImages[] = $file;
                    }
                }
            }
            
            $cleanupResults['images_found'] = count($allImages);

            // Get all referenced images from database
            $referencedImages = [];
            
            // Featured images
            $featuredImages = DB::table('news')
                ->whereNotNull('featured_image')
                ->pluck('featured_image')
                ->toArray();
            $referencedImages = array_merge($referencedImages, $featuredImages);
            
            // Gallery images
            $galleryImages = DB::table('news')
                ->whereNotNull('gallery')
                ->pluck('gallery')
                ->toArray();
                
            foreach ($galleryImages as $galleryJson) {
                $gallery = json_decode($galleryJson, true);
                if (is_array($gallery)) {
                    $referencedImages = array_merge($referencedImages, $gallery);
                }
            }
            
            // Video thumbnails
            $videoThumbnails = DB::table('news_video_embeds')
                ->whereNotNull('thumbnail')
                ->pluck('thumbnail')
                ->toArray();
                
            foreach ($videoThumbnails as $thumbnail) {
                // Extract path from URL
                $path = str_replace(asset('storage/'), '', $thumbnail);
                $referencedImages[] = $path;
            }

            $referencedImages = array_unique($referencedImages);
            $cleanupResults['images_referenced'] = count($referencedImages);

            // Find unused images
            $unusedImages = array_diff($allImages, $referencedImages);
            $cleanupResults['images_unused'] = count($unusedImages);

            // Delete unused images if not dry run
            if (!$dryRun && !empty($unusedImages)) {
                foreach ($unusedImages as $unusedImage) {
                    try {
                        $size = Storage::disk('public')->size($unusedImage);
                        $deleted = Storage::disk('public')->delete($unusedImage);
                        
                        if ($deleted) {
                            $cleanupResults['images_deleted']++;
                            $cleanupResults['space_freed'] += $size;
                        }
                    } catch (\Exception $e) {
                        $cleanupResults['errors'][] = "Failed to delete {$unusedImage}: " . $e->getMessage();
                    }
                }
            }

            $message = $dryRun 
                ? "Cleanup analysis complete. {$cleanupResults['images_unused']} unused images found."
                : "Cleanup complete. {$cleanupResults['images_deleted']} images deleted, " . $this->formatBytes($cleanupResults['space_freed']) . " freed.";

            return $this->successResponse($cleanupResults, $message);

        } catch (\Exception $e) {
            return $this->errorResponse('Error during media cleanup: ' . $e->getMessage(), 500);
        }
    }

    // ===================================
    // HELPER METHODS
    // ===================================

    /**
     * Generate unique filename for uploaded image
     */
    private function generateImageFilename($extension)
    {
        return date('Y/m/d/') . Str::random(32) . '.' . $extension;
    }

    /**
     * Process and optimize image
     */
    private function processImage($image, $maxWidth = 1200, $maxHeight = 800, $quality = 85)
    {
        try {
            $manager = new ImageManager(new Driver());
            $processedImage = $manager->read($image->getRealPath());

            // Resize if needed
            $originalWidth = $processedImage->width();
            $originalHeight = $processedImage->height();

            if ($originalWidth > $maxWidth || $originalHeight > $maxHeight) {
                $processedImage->scaleDown($maxWidth, $maxHeight);
            }

            // Convert to JPEG and optimize
            return $processedImage->toJpeg($quality);

        } catch (\Exception $e) {
            \Log::error('Error processing image: ' . $e->getMessage());
            // Fallback to original file
            return file_get_contents($image->getRealPath());
        }
    }

    /**
     * Get image dimensions
     */
    private function getImageDimensions($imagePath)
    {
        try {
            if (file_exists($imagePath)) {
                $imageInfo = getimagesize($imagePath);
                return [
                    'width' => $imageInfo[0],
                    'height' => $imageInfo[1]
                ];
            }
        } catch (\Exception $e) {
            \Log::error('Error getting image dimensions: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Extract video ID from URL
     */
    private function extractVideoId($url, $platform)
    {
        switch ($platform) {
            case 'youtube':
                if (preg_match('/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/', $url, $matches)) {
                    return $matches[1];
                }
                break;
                
            case 'twitch-clip':
                if (preg_match('/clips\.twitch\.tv\/([a-zA-Z0-9_-]+)/', $url, $matches)) {
                    return $matches[1];
                }
                break;
                
            case 'twitch-video':
                if (preg_match('/twitch\.tv\/videos\/([0-9]+)/', $url, $matches)) {
                    return $matches[1];
                }
                break;
        }

        return basename(parse_url($url, PHP_URL_PATH));
    }

    /**
     * Generate embed URL from original URL
     */
    private function generateEmbedUrl($originalUrl, $platform)
    {
        $videoId = $this->extractVideoId($originalUrl, $platform);
        
        switch ($platform) {
            case 'youtube':
                return "https://www.youtube.com/embed/{$videoId}";
                
            case 'twitch-clip':
                return "https://clips.twitch.tv/embed?clip={$videoId}&parent=" . parse_url(config('app.url'), PHP_URL_HOST);
                
            case 'twitch-video':
                return "https://player.twitch.tv/?video={$videoId}&parent=" . parse_url(config('app.url'), PHP_URL_HOST);
                
            default:
                return $originalUrl;
        }
    }

    /**
     * Check if file is an image
     */
    private function isImageFile($filename)
    {
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($extension, $imageExtensions);
    }

    /**
     * Get image category based on path
     */
    private function getImageCategory($filepath)
    {
        if (str_contains($filepath, 'featured/')) return 'featured';
        if (str_contains($filepath, 'gallery/')) return 'gallery';
        if (str_contains($filepath, 'video-thumbnails/')) return 'video-thumbnail';
        return 'other';
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
}