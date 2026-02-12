/**
 * Service Worker for ALX CDN SCORM
 * Intercepts all fetch requests and redirects them to the CDN
 */

// This will be set when the service worker is registered
let CDN_BASE_URL = '';
let MOODLE_BASE_URL = '';

self.addEventListener('install', function (event) {
    console.log('ALX CDN Service Worker: Installing...');
    self.skipWaiting();
});

self.addEventListener('activate', function (event) {
    console.log('ALX CDN Service Worker: Activating...');
    event.waitUntil(self.clients.claim());
});

self.addEventListener('message', function (event) {
    if (event.data && event.data.type === 'INIT') {
        CDN_BASE_URL = event.data.cdnBaseUrl;
        MOODLE_BASE_URL = event.data.moodleBaseUrl;
        console.log('ALX CDN Service Worker: Initialized', { CDN_BASE_URL, MOODLE_BASE_URL });
    }
});

self.addEventListener('fetch', function (event) {
    const url = new URL(event.request.url);

    // Only intercept requests to our plugin path
    if (url.origin === MOODLE_BASE_URL && url.pathname.startsWith('/local/alx_cdn_scorm/')) {

        // Extract the relative path after /local/alx_cdn_scorm/
        const relativePath = url.pathname.replace('/local/alx_cdn_scorm/', '');

        // Skip proxy.php and our own files
        if (relativePath.startsWith('proxy.php') ||
            relativePath.startsWith('player.php') ||
            relativePath.startsWith('sw.js') ||
            relativePath.startsWith('styles.css') ||
            relativePath.startsWith('alx_styles.css') ||
            relativePath.startsWith('save.php')) {
            return; // Let it pass through normally
        }

        // Redirect to CDN
        const cdnUrl = CDN_BASE_URL + relativePath;

        console.log('ALX CDN SW: Redirecting', event.request.url, '->', cdnUrl);

        event.respondWith(
            fetch(cdnUrl, {
                mode: 'cors',
                credentials: 'omit'
            }).catch(function (error) {
                console.error('ALX CDN SW: Fetch failed for', cdnUrl, error);
                return new Response('Resource not found', { status: 404 });
            })
        );
    }
});
