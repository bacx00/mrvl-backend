// MRVL Service Worker - VLR.gg Style PWA Implementation
const CACHE_NAME = 'mrvl-v1.0.1';
const STATIC_CACHE = 'mrvl-static-v1.0.1';
const DYNAMIC_CACHE = 'mrvl-dynamic-v1.0.1';
const API_CACHE = 'mrvl-api-v1.0.1';

// Files to cache immediately - only existing files
const STATIC_ASSETS = [
  '/',
  '/manifest.json'
  // Don't cache build files as they change with each build
  // Don't cache non-existent images
];

// API endpoints to cache
const API_ENDPOINTS = [
  '/api/matches',
  '/api/events',
  '/api/teams',
  '/api/players',
  '/api/rankings'
];

// Runtime caching strategies
const CACHE_STRATEGIES = {
  // Images - Cache first, network fallback
  images: {
    pattern: /\.(png|jpg|jpeg|svg|gif|webp|ico)$/i,
    strategy: 'cacheFirst',
    maxAge: 7 * 24 * 60 * 60 * 1000, // 7 days
    maxEntries: 100
  },
  // API data - Network first, cache fallback
  api: {
    pattern: /\/api\//,
    strategy: 'networkFirst',
    maxAge: 5 * 60 * 1000, // 5 minutes
    maxEntries: 50
  },
  // Match data - Stale while revalidate
  matches: {
    pattern: /\/api\/(matches|live-scoring)/,
    strategy: 'staleWhileRevalidate',
    maxAge: 30 * 1000, // 30 seconds
    maxEntries: 25
  },
  // Static assets - Cache first
  static: {
    pattern: /\.(js|css|woff|woff2|ttf|eot)$/i,
    strategy: 'cacheFirst',
    maxAge: 30 * 24 * 60 * 60 * 1000, // 30 days
    maxEntries: 50
  }
};

// Install event - Cache static assets
self.addEventListener('install', (event) => {
  console.log('[SW] Installing MRVL Service Worker');
  
  event.waitUntil(
    Promise.all([
      caches.open(STATIC_CACHE).then(cache => {
        console.log('[SW] Caching static assets');
        // Try to cache assets, but don't fail if some are missing
        return cache.addAll(STATIC_ASSETS).catch(err => {
          console.log('[SW] Some assets failed to cache:', err);
          // Try caching individually to not fail entire install
          return Promise.all(
            STATIC_ASSETS.map(asset => 
              cache.add(asset).catch(e => console.log('[SW] Failed to cache:', asset))
            )
          );
        });
      }),
      // Skip waiting to activate immediately
      self.skipWaiting()
    ])
  );
});

// Activate event - Clean up old caches
self.addEventListener('activate', (event) => {
  console.log('[SW] Activating MRVL Service Worker');
  
  event.waitUntil(
    Promise.all([
      // Clean up old caches
      caches.keys().then(cacheNames => {
        return Promise.all(
          cacheNames
            .filter(cacheName => {
              return cacheName !== STATIC_CACHE && 
                     cacheName !== DYNAMIC_CACHE && 
                     cacheName !== API_CACHE;
            })
            .map(cacheName => {
              console.log('[SW] Deleting old cache:', cacheName);
              return caches.delete(cacheName);
            })
        );
      }),
      // Take control of all clients
      self.clients.claim()
    ])
  );
});

// Fetch event - Handle all network requests
self.addEventListener('fetch', (event) => {
  const { request } = event;
  const url = new URL(request.url);
  
  // Skip non-GET requests
  if (request.method !== 'GET') {
    return;
  }
  
  // Skip chrome-extension requests
  if (url.protocol === 'chrome-extension:') {
    return;
  }
  
  // SKIP API REQUESTS - let them go through normally without service worker
  if (url.pathname.startsWith('/api/')) {
    return;
  }
  
  // Handle different types of requests
  if (CACHE_STRATEGIES.images.pattern.test(url.pathname)) {
    event.respondWith(handleImageRequest(request));
  } else if (CACHE_STRATEGIES.static.pattern.test(url.pathname)) {
    event.respondWith(handleStaticRequest(request));
  } else {
    event.respondWith(handleNavigationRequest(request));
  }
});

// Handle API requests - Network first with cache fallback
async function handleApiRequest(request) {
  const url = new URL(request.url);
  const cache = await caches.open(API_CACHE);
  
  try {
    // Try network first
    const networkResponse = await fetch(request);
    
    if (networkResponse.ok) {
      // Cache successful responses
      const responseClone = networkResponse.clone();
      
      // Add timestamp for expiry checking
      const responseWithTimestamp = new Response(responseClone.body, {
        status: responseClone.status,
        statusText: responseClone.statusText,
        headers: {
          ...Object.fromEntries(responseClone.headers.entries()),
          'sw-cache-timestamp': Date.now().toString()
        }
      });
      
      await cache.put(request, responseWithTimestamp);
      return networkResponse;
    }
    
    throw new Error('Network response not ok');
  } catch (error) {
    console.log('[SW] Network failed for API request, trying cache:', url.pathname);
    
    const cachedResponse = await cache.match(request);
    
    if (cachedResponse) {
      // Check if cache is still valid
      const cacheTimestamp = cachedResponse.headers.get('sw-cache-timestamp');
      const maxAge = url.pathname.includes('matches') || url.pathname.includes('live-scoring') 
        ? CACHE_STRATEGIES.matches.maxAge 
        : CACHE_STRATEGIES.api.maxAge;
      
      if (cacheTimestamp && (Date.now() - parseInt(cacheTimestamp)) < maxAge) {
        return cachedResponse;
      }
    }
    
    // Return offline fallback for API requests
    return new Response(
      JSON.stringify({ 
        error: 'Offline', 
        message: 'No network connection available',
        cached: false 
      }),
      {
        status: 503,
        statusText: 'Service Unavailable',
        headers: { 'Content-Type': 'application/json' }
      }
    );
  }
}

// Handle image requests - Cache first
async function handleImageRequest(request) {
  const cache = await caches.open(DYNAMIC_CACHE);
  
  // Try cache first
  const cachedResponse = await cache.match(request);
  if (cachedResponse) {
    return cachedResponse;
  }
  
  try {
    // Fetch from network
    const networkResponse = await fetch(request);
    
    if (networkResponse.ok) {
      // Cache the image
      await cache.put(request, networkResponse.clone());
      return networkResponse;
    }
    
    throw new Error('Network response not ok');
  } catch (error) {
    console.log('[SW] Failed to load image:', request.url);
    
    // Return placeholder image for failed loads
    const placeholder = await cache.match('/images/default-placeholder.svg');
    return placeholder || new Response('', { status: 404 });
  }
}

// Handle static assets - Cache first
async function handleStaticRequest(request) {
  const cache = await caches.open(STATIC_CACHE);
  
  const cachedResponse = await cache.match(request);
  if (cachedResponse) {
    return cachedResponse;
  }
  
  try {
    const networkResponse = await fetch(request);
    
    if (networkResponse.ok) {
      await cache.put(request, networkResponse.clone());
      return networkResponse;
    }
    
    throw new Error('Network response not ok');
  } catch (error) {
    console.log('[SW] Failed to load static asset:', request.url);
    return new Response('', { status: 404 });
  }
}

// Handle navigation requests - App shell pattern
async function handleNavigationRequest(request) {
  // For navigation requests, always try network first
  try {
    const networkResponse = await fetch(request);
    return networkResponse;
  } catch (error) {
    // If network fails, return cached app shell
    const cache = await caches.open(STATIC_CACHE);
    const cachedResponse = await cache.match('/');
    
    if (cachedResponse) {
      return cachedResponse;
    }
    
    // Fallback offline page
    return new Response(
      `<!DOCTYPE html>
      <html>
        <head>
          <meta charset="utf-8">
          <meta name="viewport" content="width=device-width, initial-scale=1">
          <title>MRVL - Offline</title>
          <style>
            body {
              font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
              margin: 0;
              padding: 2rem;
              text-align: center;
              background: linear-gradient(135deg, #1f2937, #111827);
              color: white;
              min-height: 100vh;
              display: flex;
              flex-direction: column;
              justify-content: center;
              align-items: center;
            }
            .logo {
              width: 64px;
              height: 64px;
              background: #dc2626;
              border-radius: 12px;
              display: flex;
              align-items: center;
              justify-content: center;
              font-size: 2rem;
              font-weight: bold;
              margin-bottom: 1rem;
            }
            h1 { margin-bottom: 0.5rem; }
            p { opacity: 0.8; margin-bottom: 2rem; }
            button {
              background: #dc2626;
              color: white;
              border: none;
              padding: 0.75rem 1.5rem;
              border-radius: 8px;
              font-size: 1rem;
              cursor: pointer;
              transition: background-color 0.2s;
            }
            button:hover { background: #b91c1c; }
          </style>
        </head>
        <body>
          <div class="logo">M</div>
          <h1>You're Offline</h1>
          <p>Check your internet connection and try again</p>
          <button onclick="window.location.reload()">Retry</button>
        </body>
      </html>`,
      {
        headers: { 'Content-Type': 'text/html' }
      }
    );
  }
}

// Background sync for offline actions
self.addEventListener('sync', (event) => {
  console.log('[SW] Background sync triggered:', event.tag);
  
  if (event.tag === 'sync-match-updates') {
    event.waitUntil(syncMatchUpdates());
  } else if (event.tag === 'sync-user-actions') {
    event.waitUntil(syncUserActions());
  }
});

// Sync offline match updates
async function syncMatchUpdates() {
  try {
    // Get stored offline actions from IndexedDB
    const offlineActions = await getStoredActions('match-updates');
    
    for (const action of offlineActions) {
      try {
        await fetch(action.url, {
          method: action.method,
          headers: action.headers,
          body: action.body
        });
        
        // Remove from storage on success
        await removeStoredAction('match-updates', action.id);
      } catch (error) {
        console.log('[SW] Failed to sync action:', action.id);
      }
    }
  } catch (error) {
    console.log('[SW] Background sync failed:', error);
  }
}

// Sync user actions
async function syncUserActions() {
  try {
    const offlineActions = await getStoredActions('user-actions');
    
    for (const action of offlineActions) {
      try {
        await fetch(action.url, {
          method: action.method,
          headers: action.headers,
          body: action.body
        });
        
        await removeStoredAction('user-actions', action.id);
      } catch (error) {
        console.log('[SW] Failed to sync user action:', action.id);
      }
    }
  } catch (error) {
    console.log('[SW] User action sync failed:', error);
  }
}

// IndexedDB helpers
async function getStoredActions(store) {
  return new Promise((resolve) => {
    // Simplified - in real implementation, use IndexedDB
    resolve([]);
  });
}

async function removeStoredAction(store, id) {
  return new Promise((resolve) => {
    // Simplified - in real implementation, use IndexedDB
    resolve();
  });
}

// Push notification handling
self.addEventListener('push', (event) => {
  console.log('[SW] Push notification received');
  
  if (!event.data) {
    return;
  }
  
  const data = event.data.json();
  const options = {
    body: data.body || 'New update from MRVL',
    icon: '/favicon.svg',
    badge: '/favicon.svg',
    image: data.image,
    tag: data.tag || 'mrvl-notification',
    renotify: true,
    actions: [
      {
        action: 'view',
        title: 'View',
        icon: '/images/view-icon.png'
      },
      {
        action: 'dismiss',
        title: 'Dismiss'
      }
    ],
    data: {
      url: data.url || '/',
      matchId: data.matchId,
      eventId: data.eventId
    }
  };
  
  event.waitUntil(
    self.registration.showNotification(data.title || 'MRVL Update', options)
  );
});

// Handle notification clicks
self.addEventListener('notificationclick', (event) => {
  console.log('[SW] Notification clicked:', event.notification.tag);
  
  event.notification.close();
  
  const urlToOpen = event.notification.data?.url || '/';
  
  event.waitUntil(
    self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(clients => {
      // Check if app is already open
      for (const client of clients) {
        if (client.url.includes(self.location.origin)) {
          return client.navigate(urlToOpen).then(() => client.focus());
        }
      }
      
      // Open new window
      return self.clients.openWindow(urlToOpen);
    })
  );
});

// Message handling from main app
self.addEventListener('message', (event) => {
  console.log('[SW] Message received:', event.data);
  
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  } else if (event.data && event.data.type === 'GET_CACHE_STATUS') {
    event.ports[0].postMessage({
      cached: true,
      version: CACHE_NAME
    });
  }
});

// Performance monitoring is handled in main fetch event

// Generic request handler
async function handleRequest(request) {
  const url = new URL(request.url);
  
  // Route to specific handlers
  if (url.pathname.startsWith('/api/')) {
    return handleApiRequest(request);
  } else if (CACHE_STRATEGIES.images.pattern.test(url.pathname)) {
    return handleImageRequest(request);
  } else if (CACHE_STRATEGIES.static.pattern.test(url.pathname)) {
    return handleStaticRequest(request);
  } else {
    return handleNavigationRequest(request);
  }
}