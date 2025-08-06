// Marvel Rivals Mobile Service Worker
// Optimized for tournament platform with offline-first architecture

const CACHE_NAME = 'mrvl-mobile-v1.2.0';
const STATIC_CACHE = 'mrvl-static-v1.2.0';
const DYNAMIC_CACHE = 'mrvl-dynamic-v1.2.0';
const IMAGES_CACHE = 'mrvl-images-v1.2.0';

// Files to cache for offline functionality
const STATIC_FILES = [
  '/',
  '/static/js/bundle.js',
  '/static/css/main.css',
  '/manifest.json',
  '/favicon.svg',
  '/favicon.ico'
];

// API endpoints to cache with strategies
const API_CACHE_STRATEGIES = {
  '/api/matches': 'stale-while-revalidate',
  '/api/teams': 'cache-first',
  '/api/players': 'cache-first',
  '/api/events': 'stale-while-revalidate',
  '/api/news': 'network-first',
  '/api/forums': 'network-first'
};

// Performance optimization settings
let performanceStrategy = {
  imageQuality: 85,
  lazy: true,
  animations: 'full',
  initialBatchSize: 12,
  batchSize: 8
};

// Install event - cache static resources
self.addEventListener('install', event => {
  console.log('🔧 Mobile SW: Installing...');
  
  event.waitUntil(
    Promise.all([
      // Cache static files
      caches.open(STATIC_CACHE).then(cache => {
        console.log('📦 Mobile SW: Caching static files');
        return cache.addAll(STATIC_FILES);
      }),
      
      // Skip waiting to activate immediately
      self.skipWaiting()
    ])
  );
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
  console.log('🚀 Mobile SW: Activating...');
  
  event.waitUntil(
    Promise.all([
      // Clean up old caches
      caches.keys().then(cacheNames => {
        return Promise.all(
          cacheNames
            .filter(cacheName => 
              cacheName.startsWith('mrvl-') && 
              ![CACHE_NAME, STATIC_CACHE, DYNAMIC_CACHE, IMAGES_CACHE].includes(cacheName)
            )
            .map(cacheName => {
              console.log('🗑️ Mobile SW: Deleting old cache:', cacheName);
              return caches.delete(cacheName);
            })
        );
      }),
      
      // Take control of all clients
      self.clients.claim()
    ])
  );
});

// Fetch event - implement caching strategies
self.addEventListener('fetch', event => {
  const request = event.request;
  const url = new URL(request.url);
  
  // Skip non-GET requests
  if (request.method !== 'GET') return;
  
  // Skip chrome-extension and other protocol requests
  if (!url.protocol.startsWith('http')) return;
  
  // Handle different types of requests
  if (isApiRequest(url)) {
    event.respondWith(handleApiRequest(request));
  } else if (isImageRequest(url)) {
    event.respondWith(handleImageRequest(request));
  } else if (isStaticAsset(url)) {
    event.respondWith(handleStaticAsset(request));
  } else {
    event.respondWith(handlePageRequest(request));
  }
});

// Message event - receive strategy updates from main app
self.addEventListener('message', event => {
  const { type, strategy } = event.data;
  
  if (type === 'UPDATE_STRATEGY') {
    performanceStrategy = { ...performanceStrategy, ...strategy };
    console.log('📊 Mobile SW: Performance strategy updated:', performanceStrategy);
  } else if (type === 'CLEAR_CACHE') {
    clearAllCaches();
  } else if (type === 'PREFETCH_RESOURCES') {
    prefetchResources(event.data.resources);
  }
});

// Check if request is to API
function isApiRequest(url) {
  return url.pathname.startsWith('/api/');
}

// Check if request is for images
function isImageRequest(url) {
  return /\.(jpg|jpeg|png|gif|webp|svg|ico)$/i.test(url.pathname);
}

// Check if request is for static assets
function isStaticAsset(url) {
  return /\.(js|css|woff|woff2|ttf|eot)$/i.test(url.pathname) || 
         url.pathname.startsWith('/static/');
}

// Handle API requests with appropriate caching strategy
async function handleApiRequest(request) {
  const url = new URL(request.url);
  const strategy = getApiStrategy(url.pathname);
  
  try {
    switch (strategy) {
      case 'cache-first':
        return await cacheFirst(request, DYNAMIC_CACHE);
      case 'network-first':
        return await networkFirst(request, DYNAMIC_CACHE);
      case 'stale-while-revalidate':
        return await staleWhileRevalidate(request, DYNAMIC_CACHE);
      default:
        return await networkFirst(request, DYNAMIC_CACHE);
    }
  } catch (error) {
    console.error('❌ Mobile SW: API request failed:', error);
    return createOfflineResponse('API temporarily unavailable');
  }
}

// Handle image requests with lazy loading and quality optimization
async function handleImageRequest(request) {
  try {
    // Try cache first for images
    const cachedResponse = await caches.match(request);
    if (cachedResponse) {
      return cachedResponse;
    }
    
    // Fetch with optimizations
    const response = await fetch(request);
    
    if (response.ok) {
      // Cache successful image responses
      const cache = await caches.open(IMAGES_CACHE);
      
      // Clone response for caching
      const responseToCache = response.clone();
      cache.put(request, responseToCache);
      
      return response;
    }
    
    return createImagePlaceholder();
  } catch (error) {
    console.error('❌ Mobile SW: Image request failed:', error);
    return createImagePlaceholder();
  }
}

// Handle static assets with cache-first strategy
async function handleStaticAsset(request) {
  try {
    return await cacheFirst(request, STATIC_CACHE);
  } catch (error) {
    console.error('❌ Mobile SW: Static asset failed:', error);
    return new Response('Asset not available', { status: 404 });
  }
}

// Handle page requests with network-first strategy
async function handlePageRequest(request) {
  try {
    return await networkFirst(request, DYNAMIC_CACHE);
  } catch (error) {
    console.error('❌ Mobile SW: Page request failed:', error);
    
    // Return cached version or offline page
    const cachedResponse = await caches.match(request);
    if (cachedResponse) {
      return cachedResponse;
    }
    
    return createOfflinePage();
  }
}

// Cache-first strategy
async function cacheFirst(request, cacheName) {
  const cachedResponse = await caches.match(request);
  if (cachedResponse) {
    return cachedResponse;
  }
  
  const response = await fetch(request);
  if (response.ok) {
    const cache = await caches.open(cacheName);
    cache.put(request, response.clone());
  }
  
  return response;
}

// Network-first strategy
async function networkFirst(request, cacheName) {
  try {
    const response = await fetch(request);
    
    if (response.ok) {
      const cache = await caches.open(cacheName);
      cache.put(request, response.clone());
    }
    
    return response;
  } catch (error) {
    const cachedResponse = await caches.match(request);
    if (cachedResponse) {
      return cachedResponse;
    }
    throw error;
  }
}

// Stale-while-revalidate strategy
async function staleWhileRevalidate(request, cacheName) {
  const cachedResponse = await caches.match(request);
  
  // Always fetch in background
  const fetchPromise = fetch(request).then(response => {
    if (response.ok) {
      const cache = caches.open(cacheName);
      cache.then(c => c.put(request, response.clone()));
    }
    return response;
  }).catch(error => {
    console.warn('🔄 Mobile SW: Background fetch failed:', error);
  });
  
  // Return cached version immediately if available
  return cachedResponse || fetchPromise;
}

// Get API caching strategy for endpoint
function getApiStrategy(pathname) {
  for (const [endpoint, strategy] of Object.entries(API_CACHE_STRATEGIES)) {
    if (pathname.startsWith(endpoint)) {
      return strategy;
    }
  }
  return 'network-first';
}

// Create offline response for API
function createOfflineResponse(message) {
  return new Response(JSON.stringify({
    error: message,
    offline: true,
    timestamp: Date.now()
  }), {
    status: 503,
    statusText: 'Service Unavailable',
    headers: {
      'Content-Type': 'application/json',
      'Cache-Control': 'no-cache'
    }
  });
}

// Create image placeholder
function createImagePlaceholder() {
  // Simple SVG placeholder
  const svg = `
    <svg width="400" height="300" xmlns="http://www.w3.org/2000/svg">
      <rect width="100%" height="100%" fill="#f3f4f6"/>
      <text x="50%" y="50%" text-anchor="middle" dy=".3em" fill="#9ca3af" font-family="Arial, sans-serif" font-size="16">
        Image Unavailable
      </text>
    </svg>
  `;
  
  return new Response(svg, {
    headers: {
      'Content-Type': 'image/svg+xml',
      'Cache-Control': 'max-age=86400'
    }
  });
}

// Create offline page
function createOfflinePage() {
  const html = `
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1">
      <title>MRVL - Offline</title>
      <style>
        body {
          font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
          margin: 0;
          padding: 2rem;
          background: linear-gradient(135deg, #1f2937 0%, #111827 100%);
          color: white;
          min-height: 100vh;
          display: flex;
          align-items: center;
          justify-content: center;
          text-align: center;
        }
        .offline-container {
          max-width: 400px;
        }
        .logo {
          font-size: 2rem;
          font-weight: bold;
          color: #ef4444;
          margin-bottom: 1rem;
        }
        .message {
          font-size: 1.2rem;
          margin-bottom: 1rem;
          opacity: 0.9;
        }
        .description {
          opacity: 0.7;
          margin-bottom: 2rem;
        }
        .retry-btn {
          background: #ef4444;
          color: white;
          border: none;
          padding: 0.75rem 1.5rem;
          border-radius: 0.5rem;
          font-size: 1rem;
          font-weight: 600;
          cursor: pointer;
          transition: background-color 0.2s;
        }
        .retry-btn:hover {
          background: #dc2626;
        }
      </style>
    </head>
    <body>
      <div class="offline-container">
        <div class="logo">MRVL</div>
        <div class="message">You're offline</div>
        <div class="description">
          Check your internet connection and try again.
          Cached content is still available.
        </div>
        <button class="retry-btn" onclick="window.location.reload()">
          Try Again
        </button>
      </div>
    </body>
    </html>
  `;
  
  return new Response(html, {
    headers: {
      'Content-Type': 'text/html',
      'Cache-Control': 'no-cache'
    }
  });
}

// Clear all caches
async function clearAllCaches() {
  const cacheNames = await caches.keys();
  await Promise.all(
    cacheNames
      .filter(name => name.startsWith('mrvl-'))
      .map(name => caches.delete(name))
  );
  console.log('🗑️ Mobile SW: All caches cleared');
}

// Prefetch resources
async function prefetchResources(resources) {
  if (!Array.isArray(resources)) return;
  
  const cache = await caches.open(DYNAMIC_CACHE);
  
  for (const resource of resources) {
    try {
      const response = await fetch(resource);
      if (response.ok) {
        await cache.put(resource, response);
        console.log('📦 Mobile SW: Prefetched:', resource);
      }
    } catch (error) {
      console.warn('⚠️ Mobile SW: Failed to prefetch:', resource, error);
    }
  }
}

// Background sync for offline actions
self.addEventListener('sync', event => {
  if (event.tag === 'background-sync') {
    event.waitUntil(performBackgroundSync());
  }
});

// Perform background sync operations
async function performBackgroundSync() {
  console.log('🔄 Mobile SW: Performing background sync...');
  
  try {
    // Sync any pending operations
    // This could include match updates, forum posts, etc.
    const pendingActions = await getStoredActions();
    
    for (const action of pendingActions) {
      try {
        await syncAction(action);
        await removeStoredAction(action.id);
      } catch (error) {
        console.error('❌ Mobile SW: Sync action failed:', action, error);
      }
    }
  } catch (error) {
    console.error('❌ Mobile SW: Background sync failed:', error);
  }
}

// Get stored actions from IndexedDB (placeholder)
async function getStoredActions() {
  // Implementation would use IndexedDB to store offline actions
  return [];
}

// Sync individual action
async function syncAction(action) {
  // Implementation would send stored actions to server
  console.log('🔄 Mobile SW: Syncing action:', action);
}

// Remove stored action after successful sync
async function removeStoredAction(actionId) {
  // Implementation would remove from IndexedDB
  console.log('✅ Mobile SW: Action synced and removed:', actionId);
}

// Push notification handling
self.addEventListener('push', event => {
  if (!event.data) return;
  
  const data = event.data.json();
  const options = {
    body: data.body,
    icon: '/favicon.svg',
    badge: '/favicon.svg',
    vibrate: [100, 50, 100],
    data: data.data,
    actions: [
      {
        action: 'view',
        title: 'View',
        icon: '/static/icons/view.png'
      },
      {
        action: 'dismiss',
        title: 'Dismiss',
        icon: '/static/icons/dismiss.png'
      }
    ]
  };
  
  event.waitUntil(
    self.registration.showNotification(data.title, options)
  );
});

// Notification click handling
self.addEventListener('notificationclick', event => {
  event.notification.close();
  
  if (event.action === 'view') {
    // Open the app to relevant page
    event.waitUntil(
      clients.openWindow(event.notification.data.url || '/')
    );
  }
});

console.log('🚀 Mobile SW: Service worker loaded and ready!');