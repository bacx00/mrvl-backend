// Empty service worker to prevent 404 errors
// This file exists only to satisfy browser requests
// All service worker functionality has been disabled

self.addEventListener('install', function(event) {
  // Skip waiting and become active immediately
  self.skipWaiting();
});

self.addEventListener('activate', function(event) {
  // Take control of all pages immediately
  event.waitUntil(clients.claim());
});

// No fetch event listener - all requests pass through normally