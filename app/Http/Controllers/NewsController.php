<?php
namespace App\Http\Controllers;

use App\Models\News;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class NewsController extends Controller
{
    // Public Routes
    public function index(Request $request)
    {
        $query = News::with(['author:id,name'])
                    ->published()
                    ->orderBy('featured', 'desc')
                    ->orderBy('published_at', 'desc');

        // Filters
        if ($request->category && $request->category !== 'all') {
            $query->byCategory($request->category);
        }

        if ($request->search) {
            $query->search($request->search);
        }

        if ($request->featured) {
            $query->featured();
        }

        // Pagination
        $perPage = min($request->get('per_page', 12), 50);
        $news = $query->paginate($perPage);

        return response()->json([
            'data' => $news->items(),
            'meta' => [
                'current_page' => $news->currentPage(),
                'last_page' => $news->lastPage(),
                'per_page' => $news->perPage(),
                'total' => $news->total(),
                'from' => $news->firstItem(),
                'to' => $news->lastItem()
            ],
            'success' => true
        ]);
    }

    public function show($slug)
    {
        $news = News::with(['author:id,name,avatar'])
                   ->where('slug', $slug)
                   ->published()
                   ->firstOrFail();

        // Increment views
        $news->incrementViews();

        // Get related news
        $related = News::with(['author:id,name'])
                      ->published()
                      ->where('id', '!=', $news->id)
                      ->where('category', $news->category)
                      ->orderBy('published_at', 'desc')
                      ->limit(3)
                      ->get();

        return response()->json([
            'data' => $news,
            'related' => $related,
            'success' => true
        ]);
    }

    public function categories()
    {
        $categories = News::published()
                         ->selectRaw('category, COUNT(*) as count')
                         ->groupBy('category')
                         ->orderBy('count', 'desc')
                         ->get();

        return response()->json([
            'data' => $categories,
            'success' => true
        ]);
    }

    // Admin Routes
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'excerpt' => 'nullable|string|max:500',
            'content' => 'required|string',
            'category' => 'required|string|max:100',
            'tags' => 'nullable|array',
            'status' => 'required|in:draft,published,archived',
            'featured' => 'boolean',
            'published_at' => 'nullable|date',
            'meta_data' => 'nullable|array'
        ]);

        // Set author
        $validated['author_id'] = Auth::id();

        // Auto-set published_at if status is published and not set
        if ($validated['status'] === 'published' && !isset($validated['published_at'])) {
            $validated['published_at'] = now();
        }

        $news = News::create($validated);

        return response()->json([
            'data' => $news->load('author:id,name'),
            'success' => true,
            'message' => 'News article created successfully'
        ], 201);
    }

    public function update(Request $request, News $news)
    {
        // Check permissions
        if (!$news->canBeEditedBy(Auth::user())) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to edit this article'
            ], 403);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'excerpt' => 'nullable|string|max:500',
            'content' => 'sometimes|string',
            'category' => 'sometimes|string|max:100',
            'tags' => 'nullable|array',
            'status' => 'sometimes|in:draft,published,archived',
            'featured' => 'boolean',
            'published_at' => 'nullable|date',
            'meta_data' => 'nullable|array'
        ]);

        // Auto-set published_at if changing to published
        if (isset($validated['status']) && $validated['status'] === 'published' && !$news->published_at) {
            $validated['published_at'] = now();
        }

        $news->update($validated);

        return response()->json([
            'data' => $news->fresh()->load('author:id,name'),
            'success' => true,
            'message' => 'News article updated successfully'
        ]);
    }

    public function destroy(News $news)
    {
        // Check permissions
        if (!$news->canBeEditedBy(Auth::user())) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to delete this article'
            ], 403);
        }

        // Delete associated images
        if ($news->featured_image) {
            \Storage::disk('public')->delete($news->featured_image);
        }

        if ($news->gallery) {
            foreach ($news->gallery as $image) {
                \Storage::disk('public')->delete($image);
            }
        }

        $news->delete();

        return response()->json([
            'success' => true,
            'message' => 'News article deleted successfully'
        ]);
    }

    // Admin: Get all news including drafts
    public function adminIndex(Request $request)
    {
        $query = News::with(['author:id,name'])
                    ->orderBy('created_at', 'desc');

        // Filters
        if ($request->status && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        if ($request->category && $request->category !== 'all') {
            $query->byCategory($request->category);
        }

        if ($request->search) {
            $query->search($request->search);
        }

        $perPage = min($request->get('per_page', 20), 100);
        $news = $query->paginate($perPage);

        return response()->json([
            'data' => $news->items(),
            'meta' => [
                'current_page' => $news->currentPage(),
                'last_page' => $news->lastPage(),
                'per_page' => $news->perPage(),
                'total' => $news->total(),
                'from' => $news->firstItem(),
                'to' => $news->lastItem()
            ],
            'success' => true
        ]);
    }
}
