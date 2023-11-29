'use strict';
const MANIFEST = 'flutter-app-manifest';
const TEMP = 'flutter-temp-cache';
const CACHE_NAME = 'flutter-app-cache';

const RESOURCES = {"assets/AssetManifest.bin": "8213bf75b5cd05780b740092adfbbea9",
"assets/AssetManifest.bin.json": "a1a9f6652a297871a2525b90983c512f",
"assets/AssetManifest.json": "be19dd80538f68b15f0f9653e6eb1a7e",
"assets/assets/anio_large.svg": "82fd5c659301d5a468df8f9a7b927997",
"assets/assets/apartadas.png": "f86bdcc53c2d72cdf322e763f76e8f3a",
"assets/assets/autoparnet_logo_1024.png": "bbbb6107c6420e42f41fc003f1b48611",
"assets/assets/box-me.svg": "787e5f364b40bc7b66a79c77e7cf7cee",
"assets/assets/buscar_men.svg.vec": "23e34b2b4deed3b7902f6e8279d6712b",
"assets/assets/camera_icon.svg.vec": "76c2c368df85707cfc71f29f23a246c3",
"assets/assets/chat.png": "b738dba926f6293cc857e108fd66e5dd",
"assets/assets/chek_ok.svg.vec": "199c9f1926f91ac78ac4e605854ece1a",
"assets/assets/cohete.svg": "b3bb027a29a8185d4ab39c7a11ec974d",
"assets/assets/compatibilities.svg": "37c0c83fe449c1fb1cd9c5dd5f549b65",
"assets/assets/delivery.png": "c98914e16e38c2638419aefe387dbcf7",
"assets/assets/developer.svg.vec": "deedd005b2ef140f839986fbe78ad569",
"assets/assets/empty-state.svg.vec": "e1b90f93e8e8d20c896d4cde2b5ce95b",
"assets/assets/favoritos.svg": "a8adcbb7506b41cc8511605b694a90a0",
"assets/assets/icon.png": "ccbcf0a6d9584c383ef720ae32b0e809",
"assets/assets/img_view.svg.vec": "ef887e1b07e24aace6c268dc9e10bc9f",
"assets/assets/interface_portrait.svg.vec": "4f88a219c2178270f622d4762ee55ae7",
"assets/assets/lad_pos.svg": "12463c8f9c39e5aacba47eaee3785464",
"assets/assets/list-check.svg.vec": "f3aa6196735a455d499a5d02bacb4067",
"assets/assets/logo-arrows.svg.vec": "f0b41fb6afb44d4e06d859abf26680a9",
"assets/assets/mail.svg.vec": "adb3009abd36f99975a422170043d9e8",
"assets/assets/marca_large.svg": "17d82c709c6421983a20b734a025e592",
"assets/assets/mlm_bg.png": "d12d365ef0296003dda14a3cc75753f4",
"assets/assets/mlm_logo.png": "a694038bf41b361be32d731dffb09395",
"assets/assets/mlm_manos.png": "46c933f85b3089cd8567b8df84349ab5",
"assets/assets/mlm_name.png": "a73fce65933b559b62c8ab3573aa6559",
"assets/assets/mobile_app.svg.vec": "bfc51925c04f1988bd627f27b581d100",
"assets/assets/not_foto.jpg": "3cf7c31bcca80d744e993d7270fd6c62",
"assets/assets/no_fetch_items.svg": "01bbf88d82201c4424a9fedb3feb45cc",
"assets/assets/no_items.svg": "8c14ab5443a5e3f539dae7fa2a5b75f1",
"assets/assets/pieza_large.svg": "2ff282c6b238884ab5d0e7fb8c76e67e",
"assets/assets/programing.svg.vec": "899d42dbdc87ffa19183b4567413bac4",
"assets/assets/resumen_large.svg": "3a587deb56afc0da347d2e96275772b0",
"assets/assets/rev_mlm.jpg": "3ea2bc8008c0eba638639bf6f66566b8",
"assets/assets/scrap.png": "7c9372b0b7c6ebeb1ba3fb5062129e3d",
"assets/assets/take_picture.jpg": "6356dad1e277a03313cf95871d94a187",
"assets/assets/whatsapp.svg": "ed26ee568c0b4e5ded2ff06e85689e8a",
"assets/FontManifest.json": "7b2a36307916a9721811788013e65289",
"assets/fonts/MaterialIcons-Regular.otf": "48307326000a8c94671e4851d0bc5dba",
"assets/NOTICES": "9a186d37a1cdcf2c9de91ccdaa9e8c72",
"assets/packages/flutter_image_compress_web/assets/pica.min.js": "6208ed6419908c4b04382adc8a3053a2",
"assets/shaders/ink_sparkle.frag": "4096b5150bac93c41cbc9b45276bd90f",
"canvaskit/canvaskit.js": "eb8797020acdbdf96a12fb0405582c1b",
"canvaskit/canvaskit.wasm": "64edb91684bdb3b879812ba2e48dd487",
"canvaskit/chromium/canvaskit.js": "0ae8bbcc58155679458a0f7a00f66873",
"canvaskit/chromium/canvaskit.wasm": "f87e541501c96012c252942b6b75d1ea",
"canvaskit/skwasm.js": "87063acf45c5e1ab9565dcf06b0c18b8",
"canvaskit/skwasm.wasm": "4124c42a73efa7eb886d3400a1ed7a06",
"canvaskit/skwasm.worker.js": "bfb704a6c714a75da9ef320991e88b03",
"drift_worker.js": "595fdbe03561e208d449ab4cace94121",
"favicon.png": "69dbb7375a4cecdabba414b20b2f5d0d",
"firebase-messaging-sw.js": "889eb03ba13958d9f6c5d967ab712d17",
"flutter.js": "59a12ab9d00ae8f8096fffc417b6e84f",
"icons/Icon-192.png": "acd44014fd26db199dd612b3109c8ee5",
"icons/Icon-512.png": "a5fb5b5bf66172c2f216882c69375385",
"icons/Icon-maskable-192.png": "acd44014fd26db199dd612b3109c8ee5",
"icons/Icon-maskable-512.png": "a5fb5b5bf66172c2f216882c69375385",
"index.html": "9d53f1366fb030133b8a6b8518fe49f8",
"/": "9d53f1366fb030133b8a6b8518fe49f8",
"main.dart.js": "674c270cc76f5a3d7352bbf9afc23978",
"manifest.json": "1c5b995b02e7d835932d37f6fa6efc4b",
"splash/img/dark-1x.png": "a4a96554f375e81dcee0c7301cfede64",
"splash/img/dark-2x.png": "6d448d771684b3bccd555a4110e1a4c9",
"splash/img/dark-3x.png": "333aa2fe0f6c0b1bf8aa1d5525efea69",
"splash/img/dark-4x.png": "fe52992a13df3162a9db5fa66833c53d",
"splash/img/light-1x.png": "a4a96554f375e81dcee0c7301cfede64",
"splash/img/light-2x.png": "6d448d771684b3bccd555a4110e1a4c9",
"splash/img/light-3x.png": "333aa2fe0f6c0b1bf8aa1d5525efea69",
"splash/img/light-4x.png": "fe52992a13df3162a9db5fa66833c53d",
"sqlite3.wasm": "2068781fd3a05f89e76131a98da09b5b",
"version.json": "e401ef4712f02d52642dfb4f6b7d5d7a"};
// The application shell files that are downloaded before a service worker can
// start.
const CORE = ["main.dart.js",
"index.html",
"assets/AssetManifest.json",
"assets/FontManifest.json"];

// During install, the TEMP cache is populated with the application shell files.
self.addEventListener("install", (event) => {
  self.skipWaiting();
  return event.waitUntil(
    caches.open(TEMP).then((cache) => {
      return cache.addAll(
        CORE.map((value) => new Request(value, {'cache': 'reload'})));
    })
  );
});
// During activate, the cache is populated with the temp files downloaded in
// install. If this service worker is upgrading from one with a saved
// MANIFEST, then use this to retain unchanged resource files.
self.addEventListener("activate", function(event) {
  return event.waitUntil(async function() {
    try {
      var contentCache = await caches.open(CACHE_NAME);
      var tempCache = await caches.open(TEMP);
      var manifestCache = await caches.open(MANIFEST);
      var manifest = await manifestCache.match('manifest');
      // When there is no prior manifest, clear the entire cache.
      if (!manifest) {
        await caches.delete(CACHE_NAME);
        contentCache = await caches.open(CACHE_NAME);
        for (var request of await tempCache.keys()) {
          var response = await tempCache.match(request);
          await contentCache.put(request, response);
        }
        await caches.delete(TEMP);
        // Save the manifest to make future upgrades efficient.
        await manifestCache.put('manifest', new Response(JSON.stringify(RESOURCES)));
        // Claim client to enable caching on first launch
        self.clients.claim();
        return;
      }
      var oldManifest = await manifest.json();
      var origin = self.location.origin;
      for (var request of await contentCache.keys()) {
        var key = request.url.substring(origin.length + 1);
        if (key == "") {
          key = "/";
        }
        // If a resource from the old manifest is not in the new cache, or if
        // the MD5 sum has changed, delete it. Otherwise the resource is left
        // in the cache and can be reused by the new service worker.
        if (!RESOURCES[key] || RESOURCES[key] != oldManifest[key]) {
          await contentCache.delete(request);
        }
      }
      // Populate the cache with the app shell TEMP files, potentially overwriting
      // cache files preserved above.
      for (var request of await tempCache.keys()) {
        var response = await tempCache.match(request);
        await contentCache.put(request, response);
      }
      await caches.delete(TEMP);
      // Save the manifest to make future upgrades efficient.
      await manifestCache.put('manifest', new Response(JSON.stringify(RESOURCES)));
      // Claim client to enable caching on first launch
      self.clients.claim();
      return;
    } catch (err) {
      // On an unhandled exception the state of the cache cannot be guaranteed.
      console.error('Failed to upgrade service worker: ' + err);
      await caches.delete(CACHE_NAME);
      await caches.delete(TEMP);
      await caches.delete(MANIFEST);
    }
  }());
});
// The fetch handler redirects requests for RESOURCE files to the service
// worker cache.
self.addEventListener("fetch", (event) => {
  if (event.request.method !== 'GET') {
    return;
  }
  var origin = self.location.origin;
  var key = event.request.url.substring(origin.length + 1);
  // Redirect URLs to the index.html
  if (key.indexOf('?v=') != -1) {
    key = key.split('?v=')[0];
  }
  if (event.request.url == origin || event.request.url.startsWith(origin + '/#') || key == '') {
    key = '/';
  }
  // If the URL is not the RESOURCE list then return to signal that the
  // browser should take over.
  if (!RESOURCES[key]) {
    return;
  }
  // If the URL is the index.html, perform an online-first request.
  if (key == '/') {
    return onlineFirst(event);
  }
  event.respondWith(caches.open(CACHE_NAME)
    .then((cache) =>  {
      return cache.match(event.request).then((response) => {
        // Either respond with the cached resource, or perform a fetch and
        // lazily populate the cache only if the resource was successfully fetched.
        return response || fetch(event.request).then((response) => {
          if (response && Boolean(response.ok)) {
            cache.put(event.request, response.clone());
          }
          return response;
        });
      })
    })
  );
});
self.addEventListener('message', (event) => {
  // SkipWaiting can be used to immediately activate a waiting service worker.
  // This will also require a page refresh triggered by the main worker.
  if (event.data === 'skipWaiting') {
    self.skipWaiting();
    return;
  }
  if (event.data === 'downloadOffline') {
    downloadOffline();
    return;
  }
});
// Download offline will check the RESOURCES for all files not in the cache
// and populate them.
async function downloadOffline() {
  var resources = [];
  var contentCache = await caches.open(CACHE_NAME);
  var currentContent = {};
  for (var request of await contentCache.keys()) {
    var key = request.url.substring(origin.length + 1);
    if (key == "") {
      key = "/";
    }
    currentContent[key] = true;
  }
  for (var resourceKey of Object.keys(RESOURCES)) {
    if (!currentContent[resourceKey]) {
      resources.push(resourceKey);
    }
  }
  return contentCache.addAll(resources);
}
// Attempt to download the resource online before falling back to
// the offline cache.
function onlineFirst(event) {
  return event.respondWith(
    fetch(event.request).then((response) => {
      return caches.open(CACHE_NAME).then((cache) => {
        cache.put(event.request, response.clone());
        return response;
      });
    }).catch((error) => {
      return caches.open(CACHE_NAME).then((cache) => {
        return cache.match(event.request).then((response) => {
          if (response != null) {
            return response;
          }
          throw error;
        });
      });
    })
  );
}
