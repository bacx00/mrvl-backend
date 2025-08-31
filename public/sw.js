// MRVL Platform Service Worker
// Optimized for mobile performance and offline support

const CACHE_NAME = 'mrvl-v1.0.1';
const STATIC_CACHE = 'mrvl-static-v1.0.1';
const API_CACHE = 'mrvl-api-v1.0.1';

// Resources to cache immediately
const STATIC_ASSETS = [
  '/',
  '/static/css/main.dd3e51a9.css',
  '/static/js/main.cb4673c7.js',
  '/favicon.ico',
  '/favicon.svg',
  '/manifest.json'
];

// API endpoints to cache with network-first strategy
const CACHEABLE_API_ROUTES = [
  '/api/teams',
  '/api/players',
  '/api/matches',
  '/api/news',
  '/api/events',
  '/api/tournaments',
  '/api/rankings'
];

// Install event - cache static assets
self.addEventListener('install', event => {
  console.log('🔧 Service Worker installing...');
  
  event.waitUntil(
    Promise.all([
      caches.open(STATIC_CACHE).then(cache => {
        console.log('📦 Caching static assets');
        return cache.addAll(STATIC_ASSETS.map(url => new Request(url, { cache: 'no-cache' })));
      }),
      self.skipWaiting()
    ])
  );
});

// Activate event - clean up old caches
self.addEventListener('activate', event => {
  console.log('✅ Service Worker activating...');
  
  event.waitUntil(
    Promise.all([
      // Clean up old caches
      caches.keys().then(cacheNames => {
        return Promise.all(
          cacheNames
            .filter(cacheName => 
              cacheName !== CACHE_NAME && 
              cacheName !== STATIC_CACHE && 
              cacheName !== API_CACHE
            )
            .map(cacheName => {
              console.log('🗑️ Deleting old cache:', cacheName);
              return caches.delete(cacheName);
            })
        );
      }),
      // Take control of all clients
      self.clients.claim()
    ])
  );
});

// Fetch event - handle different caching strategies
self.addEventListener('fetch', event => {
  const { request } = event;
  const { url, method, destination } = request;
  
  // Only handle GET requests
  if (method !== 'GET') {
    return;
  }
  
  // Skip cross-origin requests
  if (!url.startsWith(self.location.origin)) {
    return;
  }
  
  // Handle different types of requests
  if (url.includes('/api/')) {
    // API requests - network first with cache fallback
    event.respondWith(handleApiRequest(request));
  } else if (destination === 'image') {
    // Images - cache first with network fallback
    event.respondWith(handleImageRequest(request));
  } else if (url.includes('/static/')) {
    // Static assets - cache first
    event.respondWith(handleStaticRequest(request));
  } else {
    // Navigation requests - network first with cache fallback
    event.respondWith(handleNavigationRequest(request));
  }
});

// API request handler - network first, cache fallback
async function handleApiRequest(request) {
  const url = request.url;
  const isCacheable = CACHEABLE_API_ROUTES.some(route => url.includes(route));
  
  if (!isCacheable) {
    // Don't cache sensitive API calls
    return fetch(request);
  }
  
  try {
    // Try network first
    const networkResponse = await fetch(request);
    
    if (networkResponse.ok) {
      // Cache successful responses
      const cache = await caches.open(API_CACHE);
      cache.put(request, networkResponse.clone());
      return networkResponse;
    }
    
    throw new Error('Network response not ok');
  } catch (error) {
    // Fallback to cache
    console.log('📱 Network failed, trying cache for:', url);
    const cachedResponse = await caches.match(request);
    
    if (cachedResponse) {
      return cachedResponse;
    }
    
    // Return offline page for API failures
    return new Response(
      JSON.stringify({
        error: 'Offline',
        message: 'You are currently offline. Some data may be outdated.'
      }),
      {
        status: 503,
        statusText: 'Service Unavailable',
        headers: { 'Content-Type': 'application/json' }
      }
    );
  }
}

// Image request handler - cache first with network fallback
async function handleImageRequest(request) {
  try {
    const cachedResponse = await caches.match(request);
    
    if (cachedResponse) {
      return cachedResponse;
    }
    
    // Fetch from network
    const networkResponse = await fetch(request);
    
    if (networkResponse.ok) {
      // Cache images
      const cache = await caches.open(STATIC_CACHE);
      cache.put(request, networkResponse.clone());
    }
    
    return networkResponse;
  } catch (error) {
    // Return placeholder image on failure
    return new Response(
      '<svg width="200" height="200" xmlns="http://www.w3.org/2000/svg"><rect width="200" height="200" fill="#f3f4f6"/><text x="50%" y="50%" text-anchor="middle" dy="0.35em" fill="#9ca3af">Image unavailable</text></svg>',
      { headers: { 'Content-Type': 'image/svg+xml' } }
    );
  }
}

// Static asset handler - cache first
async function handleStaticRequest(request) {
  const cachedResponse = await caches.match(request);
  
  if (cachedResponse) {
    return cachedResponse;
  }
  
  try {
    const networkResponse = await fetch(request);
    
    if (networkResponse.ok) {
      const cache = await caches.open(STATIC_CACHE);
      cache.put(request, networkResponse.clone());
    }
    
    return networkResponse;
  } catch (error) {
    console.log('❌ Failed to fetch static asset:', request.url);
    throw error;
  }
}

// Navigation request handler - network first with cache fallback
async function handleNavigationRequest(request) {
  try {
    const networkResponse = await fetch(request);
    return networkResponse;
  } catch (error) {
    // Fallback to cached main page
    const cachedResponse = await caches.match('/');
    
    if (cachedResponse) {
      return cachedResponse;
    }
    
    // Return offline page
    return new Response(`
      <!DOCTYPE html>
      <html>
        <head>
          <meta charset="utf-8">
          <meta name="viewport" content="width=device-width, initial-scale=1">
          <title>MRVL - Offline</title>
          <style>
            body { 
              font-family: -apple-system, BlinkMacSystemFont, sans-serif;
              background: #0f172a;
              color: #f1f5f9;
              display: flex;
              align-items: center;
              justify-content: center;
              min-height: 100vh;
              margin: 0;
              text-align: center;
            }
            .offline-message {
              max-width: 400px;
              padding: 2rem;
            }
            .offline-icon {
              width: 64px;
              height: 64px;
              margin: 0 auto 1rem;
              opacity: 0.5;
            }
          </style>
        </head>
        <body>
          <div class="offline-message">
            <div class="offline-icon">📱</div>
            <h1>You're Offline</h1>
            <p>Please check your internet connection and try again.</p>
            <button onclick="window.location.reload()" 
                    style="background: #ef4444; color: white; border: none; padding: 12px 24px; border-radius: 6px; cursor: pointer; margin-top: 1rem;">
              Try Again
            </button>
          </div>
        </body>
      </html>
    `, {
      headers: { 'Content-Type': 'text/html' }
    });
  }
}

// Background sync for offline actions
self.addEventListener('sync', event => {
  if (event.tag === 'background-sync') {
    console.log('🔄 Background sync triggered');
    event.waitUntil(handleBackgroundSync());
  }
});

async function handleBackgroundSync() {
  // Handle offline actions when connection is restored
  try {
    const cache = await caches.open('offline-actions');
    const requests = await cache.keys();
    
    for (const request of requests) {
      try {
        await fetch(request);
        await cache.delete(request);
        console.log('✅ Synced offline action:', request.url);
      } catch (error) {
        console.log('❌ Failed to sync:', request.url);
      }
    }
  } catch (error) {
    console.log('Background sync failed:', error);
  }
}

// Push notification handler
self.addEventListener('push', event => {
  if (!event.data) {
    return;
  }
  
  const data = event.data.json();
  const options = {
    body: data.body,
    icon: '/favicon.svg',
    badge: '/favicon.svg',
    tag: data.tag || 'mrvl-notification',
    renotify: true,
    actions: data.actions || []
  };
  
  event.waitUntil(
    self.registration.showNotification(data.title, options)
  );
});

// Notification click handler
self.addEventListener('notificationclick', event => {
  event.notification.close();
  
  if (event.action) {
    // Handle notification action
    console.log('Notification action clicked:', event.action);
  } else {
    // Open the app
    event.waitUntil(
      self.clients.matchAll().then(clients => {
        if (clients.length > 0) {
          return clients[0].focus();
        } else {
          return self.clients.openWindow('/');
        }
      })
    );
  }
});

// Message handler for communication with main thread
self.addEventListener('message', event => {
  const { type, payload } = event.data;
  
  switch (type) {
    case 'CACHE_API_RESPONSE':
      handleCacheApiResponse(payload);
      break;
    case 'CLEAR_CACHE':
      handleClearCache(payload);
      break;
    case 'GET_CACHE_STATUS':
      handleGetCacheStatus(event);
      break;
  }
});

async function handleCacheApiResponse(payload) {
  const { url, data } = payload;
  const cache = await caches.open(API_CACHE);
  const response = new Response(JSON.stringify(data), {
    headers: { 'Content-Type': 'application/json' }
  });
  await cache.put(url, response);
}

async function handleClearCache(payload) {
  const { cacheNames } = payload;
  
  for (const cacheName of cacheNames) {
    await caches.delete(cacheName);
  }
  
  console.log('🗑️ Cleared caches:', cacheNames);
}

async function handleGetCacheStatus(event) {
  const cacheNames = await caches.keys();
  const cacheStatus = {};
  
  for (const cacheName of cacheNames) {
    const cache = await caches.open(cacheName);
    const keys = await cache.keys();
    cacheStatus[cacheName] = keys.length;
  }
  
  event.ports[0].postMessage({ type: 'CACHE_STATUS', payload: cacheStatus });
}

console.log('🚀 MRVL Service Worker loaded successfully!');