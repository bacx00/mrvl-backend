<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\ApiResponseController;
use App\Models\NewsCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AdminNewsCategoryController extends ApiResponseController
{
    /**
     * Display a listing of news categories
     */
    public function index(Request $request)
    {
        try {
            // Check authorization
            if (!auth('api')->check() || !auth('api')->user()->hasAnyRole(['admin', 'moderator'])) {
                return $this->errorResponse('Unauthorized - Admin or Moderator access required', 403);
            }

            $query = DB::table('news_categories');

            // Search functionality
            if ($request->filled('search')) {
                $searchTerm = $request->search;
                $query->where(function($q) use ($searchTerm) {
                    $q->where('name', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('description', 'LIKE', "%{$searchTerm}%");
                });
            }

            // Filter by active status
            if ($request->filled('active')) {
                $query->where('active', $request->boolean('active'));
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'sort_order');
            $sortOrder = $request->get('sort_order', 'asc');
            
            $allowedSortFields = ['name', 'sort_order', 'created_at', 'updated_at'];
            if (!in_array($sortBy, $allowedSortFields)) {
                $sortBy = 'sort_order';
            }
            
            if (!in_array(strtolower($sortOrder), ['asc', 'desc'])) {
                $sortOrder = 'asc';
            }

            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = min($request->get('per_page', 20), 100);
            
            if ($request->get('all') === 'true') {
                $categories = $query->get();
                $paginationData = null;
            } else {
                $paginatedCategories = $query->paginate($perPage);
                $categories = collect($paginatedCategories->items());
                $paginationData = [
                    'current_page' => $paginatedCategories->currentPage(),
                    'last_page' => $paginatedCategories->lastPage(),
                    'per_page' => $paginatedCategories->perPage(),
                    'total' => $paginatedCategories->total(),
                    'from' => $paginatedCategories->firstItem(),
                    'to' => $paginatedCategories->lastItem()
                ];
            }

            // Add article counts to each category
            $categoriesWithCounts = $categories->map(function($category) {
                $articleCount = DB::table('news')->where('category_id', $category->id)->count();
                $publishedCount = DB::table('news')
                    ->where('category_id', $category->id)
                    ->where('status', 'published')
                    ->count();
                
                return [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'description' => $category->description,
                    'color' => $category->color,
                    'icon' => $category->icon,
                    'active' => (bool)$category->active,
                    'sort_order' => $category->sort_order,
                    'article_count' => $articleCount,
                    'published_count' => $publishedCount,
                    'created_at' => $category->created_at,
                    'updated_at' => $category->updated_at
                ];
            });

            $responseData = [
                'data' => $categoriesWithCounts
            ];

            if ($paginationData) {
                $responseData['pagination'] = $paginationData;
            }

            return $this->successResponse($responseData, 'News categories retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Error fetching news categories: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Store a newly created news category
     */
    public function store(Request $request)
    {
        try {
            // Check authorization
            if (!auth('api')->check() || !auth('api')->user()->hasAnyRole(['admin', 'moderator'])) {
                return $this->errorResponse('Unauthorized - Admin or Moderator access required', 403);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:100|unique:news_categories,name',
                'slug' => 'nullable|string|max:100|unique:news_categories,slug',
                'description' => 'nullable|string|max:500',
                'color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
                'icon' => 'nullable|string|max:50',
                'active' => 'boolean',
                'sort_order' => 'nullable|integer|min:0'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', 422, $validator->errors());
            }

            $data = $validator->validated();

            // Generate slug if not provided
            if (empty($data['slug'])) {
                $data['slug'] = $this->generateUniqueSlug($data['name']);
            } else {
                $data['slug'] = Str::slug($data['slug']);
            }

            // Set default values
            $data['active'] = $data['active'] ?? true;
            $data['color'] = $data['color'] ?? '#6b7280';

            // Set sort order if not provided
            if (!isset($data['sort_order'])) {
                $maxSort = DB::table('news_categories')->max('sort_order') ?? 0;
                $data['sort_order'] = $maxSort + 1;
            }

            // Set timestamps
            $data['created_at'] = now();
            $data['updated_at'] = now();

            // Insert category
            $categoryId = DB::table('news_categories')->insertGetId($data);

            // Fetch the created category
            $category = DB::table('news_categories')->where('id', $categoryId)->first();

            $categoryData = [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'color' => $category->color,
                'icon' => $category->icon,
                'active' => (bool)$category->active,
                'sort_order' => $category->sort_order,
                'article_count' => 0,
                'published_count' => 0,
                'created_at' => $category->created_at,
                'updated_at' => $category->updated_at
            ];

            return $this->createdResponse($categoryData, 'News category created successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Error creating news category: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified news category
     */
    public function show($categoryId)
    {
        try {
            // Check authorization
            if (!auth('api')->check() || !auth('api')->user()->hasAnyRole(['admin', 'moderator'])) {
                return $this->errorResponse('Unauthorized - Admin or Moderator access required', 403);
            }

            $category = DB::table('news_categories')->where('id', $categoryId)->first();

            if (!$category) {
                return $this->errorResponse('News category not found', 404);
            }

            // Get article counts
            $articleCount = DB::table('news')->where('category_id', $categoryId)->count();
            $publishedCount = DB::table('news')
                ->where('category_id', $categoryId)
                ->where('status', 'published')
                ->count();

            // Get recent articles in this category
            $recentArticles = DB::table('news as n')
                ->leftJoin('users as u', 'n.author_id', '=', 'u.id')
                ->where('n.category_id', $categoryId)
                ->select([
                    'n.id', 'n.title', 'n.slug', 'n.status', 'n.created_at', 'n.published_at',
                    'u.name as author_name'
                ])
                ->orderBy('n.created_at', 'desc')
                ->limit(10)
                ->get();

            $categoryData = [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'color' => $category->color,
                'icon' => $category->icon,
                'active' => (bool)$category->active,
                'sort_order' => $category->sort_order,
                'article_count' => $articleCount,
                'published_count' => $publishedCount,
                'created_at' => $category->created_at,
                'updated_at' => $category->updated_at,
                'recent_articles' => $recentArticles
            ];

            return $this->successResponse($categoryData, 'News category retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Error fetching news category: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update the specified news category
     */
    public function update(Request $request, $categoryId)
    {
        try {
            // Check authorization
            if (!auth('api')->check() || !auth('api')->user()->hasAnyRole(['admin', 'moderator'])) {
                return $this->errorResponse('Unauthorized - Admin or Moderator access required', 403);
            }

            $category = DB::table('news_categories')->where('id', $categoryId)->first();
            if (!$category) {
                return $this->errorResponse('News category not found', 404);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'nullable|string|max:100|unique:news_categories,name,' . $categoryId,
                'slug' => 'nullable|string|max:100|unique:news_categories,slug,' . $categoryId,
                'description' => 'nullable|string|max:500',
                'color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
                'icon' => 'nullable|string|max:50',
                'active' => 'nullable|boolean',
                'sort_order' => 'nullable|integer|min:0'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', 422, $validator->errors());
            }

            $data = $validator->validated();

            // Remove null values to avoid overwriting existing data
            $data = array_filter($data, function($value) {
                return $value !== null;
            });

            if (empty($data)) {
                return $this->errorResponse('No valid data provided for update', 422);
            }

            // Generate slug if name is updated but slug is not provided
            if (isset($data['name']) && !isset($data['slug'])) {
                $data['slug'] = $this->generateUniqueSlug($data['name'], $categoryId);
            } elseif (isset($data['slug'])) {
                $data['slug'] = Str::slug($data['slug']);
            }

            // Set updated timestamp
            $data['updated_at'] = now();

            // Update category
            DB::table('news_categories')->where('id', $categoryId)->update($data);

            // Fetch updated category
            $updatedCategory = DB::table('news_categories')->where('id', $categoryId)->first();

            // Get article counts
            $articleCount = DB::table('news')->where('category_id', $categoryId)->count();
            $publishedCount = DB::table('news')
                ->where('category_id', $categoryId)
                ->where('status', 'published')
                ->count();

            $categoryData = [
                'id' => $updatedCategory->id,
                'name' => $updatedCategory->name,
                'slug' => $updatedCategory->slug,
                'description' => $updatedCategory->description,
                'color' => $updatedCategory->color,
                'icon' => $updatedCategory->icon,
                'active' => (bool)$updatedCategory->active,
                'sort_order' => $updatedCategory->sort_order,
                'article_count' => $articleCount,
                'published_count' => $publishedCount,
                'created_at' => $updatedCategory->created_at,
                'updated_at' => $updatedCategory->updated_at
            ];

            return $this->successResponse($categoryData, 'News category updated successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Error updating news category: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified news category
     */
    public function destroy($categoryId)
    {
        try {
            // Check authorization - Only admins can delete categories
            if (!auth('api')->check() || !auth('api')->user()->hasRole('admin')) {
                return $this->errorResponse('Unauthorized - Admin access required', 403);
            }

            $category = DB::table('news_categories')->where('id', $categoryId)->first();
            if (!$category) {
                return $this->errorResponse('News category not found', 404);
            }

            // Check if category has articles
            $articleCount = DB::table('news')->where('category_id', $categoryId)->count();
            if ($articleCount > 0) {
                return $this->errorResponse(
                    "Cannot delete category with {$articleCount} articles. Please reassign or delete articles first.", 
                    422
                );
            }

            // Delete category
            DB::table('news_categories')->where('id', $categoryId)->delete();

            return $this->successResponse(null, 'News category deleted successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Error deleting news category: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Bulk operations on news categories
     */
    public function bulkOperation(Request $request)
    {
        try {
            // Check authorization
            if (!auth('api')->check() || !auth('api')->user()->hasAnyRole(['admin', 'moderator'])) {
                return $this->errorResponse('Unauthorized - Admin or Moderator access required', 403);
            }

            $validator = Validator::make($request->all(), [
                'action' => 'required|in:activate,deactivate,delete,reorder',
                'category_ids' => 'required|array|min:1',
                'category_ids.*' => 'integer|exists:news_categories,id',
                'sort_orders' => 'required_if:action,reorder|array',
                'sort_orders.*' => 'integer|min:0'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', 422, $validator->errors());
            }

            $action = $request->action;
            $categoryIds = $request->category_ids;
            $affected = 0;

            DB::beginTransaction();

            try {
                switch ($action) {
                    case 'activate':
                        $affected = DB::table('news_categories')
                            ->whereIn('id', $categoryIds)
                            ->update(['active' => true, 'updated_at' => now()]);
                        break;

                    case 'deactivate':
                        $affected = DB::table('news_categories')
                            ->whereIn('id', $categoryIds)
                            ->update(['active' => false, 'updated_at' => now()]);
                        break;

                    case 'delete':
                        if (!auth('api')->user()->hasRole('admin')) {
                            return $this->errorResponse('Unauthorized - Admin access required for bulk delete', 403);
                        }
                        
                        // Check if any category has articles
                        $categoriesWithArticles = DB::table('news_categories as nc')
                            ->leftJoin('news as n', 'nc.id', '=', 'n.category_id')
                            ->whereIn('nc.id', $categoryIds)
                            ->groupBy('nc.id', 'nc.name')
                            ->havingRaw('COUNT(n.id) > 0')
                            ->select('nc.name', DB::raw('COUNT(n.id) as article_count'))
                            ->get();

                        if ($categoriesWithArticles->count() > 0) {
                            $errorMessage = 'Cannot delete categories with articles: ';
                            $errorMessage .= $categoriesWithArticles->map(function($cat) {
                                return "{$cat->name} ({$cat->article_count} articles)";
                            })->implode(', ');
                            
                            return $this->errorResponse($errorMessage, 422);
                        }

                        $affected = DB::table('news_categories')->whereIn('id', $categoryIds)->delete();
                        break;

                    case 'reorder':
                        $sortOrders = $request->sort_orders;
                        
                        if (count($categoryIds) !== count($sortOrders)) {
                            return $this->errorResponse('Category IDs and sort orders count mismatch', 422);
                        }

                        foreach ($categoryIds as $index => $categoryId) {
                            DB::table('news_categories')
                                ->where('id', $categoryId)
                                ->update([
                                    'sort_order' => $sortOrders[$index],
                                    'updated_at' => now()
                                ]);
                            $affected++;
                        }
                        break;
                }

                DB::commit();

                return $this->successResponse(
                    ['affected_count' => $affected], 
                    "Bulk {$action} operation completed successfully. {$affected} categories affected."
                );

            } catch (\Exception $e) {
                DB::rollback();
                throw $e;
            }

        } catch (\Exception $e) {
            return $this->errorResponse('Error performing bulk operation: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Reorder categories
     */
    public function reorder(Request $request)
    {
        try {
            // Check authorization
            if (!auth('api')->check() || !auth('api')->user()->hasAnyRole(['admin', 'moderator'])) {
                return $this->errorResponse('Unauthorized - Admin or Moderator access required', 403);
            }

            $validator = Validator::make($request->all(), [
                'categories' => 'required|array|min:1',
                'categories.*.id' => 'required|integer|exists:news_categories,id',
                'categories.*.sort_order' => 'required|integer|min:0'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', 422, $validator->errors());
            }

            DB::beginTransaction();

            try {
                foreach ($request->categories as $category) {
                    DB::table('news_categories')
                        ->where('id', $category['id'])
                        ->update([
                            'sort_order' => $category['sort_order'],
                            'updated_at' => now()
                        ]);
                }

                DB::commit();

                return $this->successResponse(null, 'Categories reordered successfully');

            } catch (\Exception $e) {
                DB::rollback();
                throw $e;
            }

        } catch (\Exception $e) {
            return $this->errorResponse('Error reordering categories: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get category statistics
     */
    public function getStatistics()
    {
        try {
            // Check authorization
            if (!auth('api')->check() || !auth('api')->user()->hasAnyRole(['admin', 'moderator'])) {
                return $this->errorResponse('Unauthorized - Admin or Moderator access required', 403);
            }

            $stats = [
                'total_categories' => DB::table('news_categories')->count(),
                'active_categories' => DB::table('news_categories')->where('active', true)->count(),
                'categories_with_articles' => DB::table('news_categories')
                    ->whereExists(function($query) {
                        $query->select(DB::raw(1))
                            ->from('news')
                            ->whereColumn('news.category_id', 'news_categories.id');
                    })
                    ->count(),
                'most_used_categories' => DB::table('news_categories as nc')
                    ->leftJoin('news as n', 'nc.id', '=', 'n.category_id')
                    ->select('nc.name', 'nc.color', DB::raw('COUNT(n.id) as article_count'))
                    ->groupBy('nc.id', 'nc.name', 'nc.color')
                    ->orderBy('article_count', 'desc')
                    ->limit(5)
                    ->get()
            ];

            return $this->successResponse($stats, 'Category statistics retrieved successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Error fetching statistics: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Generate a unique slug for the category
     */
    private function generateUniqueSlug($name, $excludeId = null)
    {
        $slug = Str::slug($name);
        $originalSlug = $slug;
        $counter = 1;

        $query = DB::table('news_categories')->where('slug', $slug);
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        while ($query->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
            
            $query = DB::table('news_categories')->where('slug', $slug);
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }
        }

        return $slug;
    }
}