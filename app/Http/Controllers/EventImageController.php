<?php
namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;

class EventImageController extends Controller
{
    public function uploadLogo(Request $request, $eventId)
    {
        $request->validate([
            'logo' => 'required|image|mimes:jpeg,png,jpg,gif,svg|max:5120', // 5MB max
        ]);

        try {
            $event = Event::findOrFail($eventId);
            
            // Check if user can upload (event organizer or admin)
            $this->authorize('update', $event);

            $file = $request->file('logo');
            $filename = 'event_logo_' . $eventId . '_' . time() . '.' . $file->getClientOriginalExtension();
            
            // Create optimized logo (square format, 512x512)
            $image = Image::make($file);
            $image->fit(512, 512, function ($constraint) {
                $constraint->upsize();
            });
            $image->encode('png', 90);
            
            // Store in public storage
            $path = 'events/logos/' . $filename;
            Storage::disk('public')->put($path, $image->stream());
            
            // Delete old logo if exists
            if ($event->logo) {
                $oldPath = str_replace(url('storage/'), '', $event->logo);
                Storage::disk('public')->delete($oldPath);
            }
            
            // Update event with new logo URL
            $logoUrl = url('storage/' . $path);
            $event->update(['logo' => $logoUrl]);

            return response()->json([
                'success' => true,
                'logo_url' => $logoUrl,
                'message' => 'Event logo uploaded successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error uploading logo: ' . $e->getMessage()
            ], 500);
        }
    }

    public function uploadBanner(Request $request, $eventId)
    {
        $request->validate([
            'banner' => 'required|image|mimes:jpeg,png,jpg,gif|max:10240', // 10MB max
        ]);

        try {
            $event = Event::findOrFail($eventId);
            
            // Check if user can upload (event organizer or admin)
            $this->authorize('update', $event);

            $file = $request->file('banner');
            $filename = 'event_banner_' . $eventId . '_' . time() . '.' . $file->getClientOriginalExtension();
            
            // Create optimized banner (16:9 aspect ratio, 1920x1080)
            $image = Image::make($file);
            $image->fit(1920, 1080, function ($constraint) {
                $constraint->upsize();
            });
            $image->encode('jpg', 85);
            
            // Store in public storage
            $path = 'events/banners/' . $filename;
            Storage::disk('public')->put($path, $image->stream());
            
            // Delete old banner if exists
            if ($event->banner) {
                $oldPath = str_replace(url('storage/'), '', $event->banner);
                Storage::disk('public')->delete($oldPath);
            }
            
            // Update event with new banner URL
            $bannerUrl = url('storage/' . $path);
            $event->update(['banner' => $bannerUrl]);

            return response()->json([
                'success' => true,
                'banner_url' => $bannerUrl,
                'message' => 'Event banner uploaded successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error uploading banner: ' . $e->getMessage()
            ], 500);
        }
    }

    public function deleteLogo($eventId)
    {
        try {
            $event = Event::findOrFail($eventId);
            
            // Check if user can delete (event organizer or admin)
            $this->authorize('update', $event);

            if ($event->logo) {
                $path = str_replace(url('storage/'), '', $event->logo);
                Storage::disk('public')->delete($path);
                
                $event->update(['logo' => null]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Event logo deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting logo: ' . $e->getMessage()
            ], 500);
        }
    }

    public function deleteBanner($eventId)
    {
        try {
            $event = Event::findOrFail($eventId);
            
            // Check if user can delete (event organizer or admin)
            $this->authorize('update', $event);

            if ($event->banner) {
                $path = str_replace(url('storage/'), '', $event->banner);
                Storage::disk('public')->delete($path);
                
                $event->update(['banner' => null]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Event banner deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting banner: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getImageInfo($eventId)
    {
        try {
            $event = Event::findOrFail($eventId);

            return response()->json([
                'success' => true,
                'data' => [
                    'event_id' => $event->id,
                    'logo' => $event->logo,
                    'banner' => $event->banner,
                    'has_logo' => !empty($event->logo),
                    'has_banner' => !empty($event->banner)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error fetching image info: ' . $e->getMessage()
            ], 500);
        }
    }
}