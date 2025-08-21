const CACHE_NAME = 'fieldops-cache-v1';
const CORE_ASSETS = [
  '/tech_jobs.php',
  '/tech_job.php',
  '/tech_job_complete.php',
  '/js/offline.js',
  '/js/tech_jobs.js',
  '/js/tech_job.js',
  '/js/tech_job_complete.js',
  '/css/app.css'
];

self.addEventListener('install', event => {
  event.waitUntil(
    caches.open(CACHE_NAME).then(cache => cache.addAll(CORE_ASSETS))
  );
});

self.addEventListener('activate', event => {
  event.waitUntil(self.clients.claim());
});

self.addEventListener('fetch', event => {
  if (event.request.method !== 'GET') return;
  event.respondWith(
    caches.match(event.request).then(res => res || fetch(event.request))
  );
});

function openDB() {
  return new Promise((resolve, reject) => {
    const req = indexedDB.open('fieldops', 1);
    req.onupgradeneeded = e => {
      const db = e.target.result;
      if (!db.objectStoreNames.contains('queue')) {
        db.createObjectStore('queue', { autoIncrement: true });
      }
    };
    req.onsuccess = () => resolve(req.result);
    req.onerror = () => reject(req.error);
  });
}

function dataURLtoBlob(dataUrl) {
  const arr = dataUrl.split(',');
  const mime = (arr[0].match(/:(.*?);/) || [])[1] || 'application/octet-stream';
  const bstr = atob(arr[1]);
  let n = bstr.length;
  const u8 = new Uint8Array(n);
  while (n--) u8[n] = bstr.charCodeAt(n);
  return new Blob([u8], { type: mime });
}

async function sendQueued() {
  const db = await openDB();
  async function sendOne() {
    const cursorData = await new Promise(resolve => {
      const tx = db.transaction('queue', 'readonly');
      const store = tx.objectStore('queue');
      const req = store.openCursor();
      req.onsuccess = e => {
        const cursor = e.target.result;
        if (!cursor) {
          resolve(null);
          return;
        }
        resolve({ key: cursor.key, item: cursor.value });
      };
      req.onerror = () => resolve(null);
    });

    if (!cursorData) return false;

    const { key, item } = cursorData;
    try {
      if (item.type === 'note') {
        const fd = new FormData();
        fd.append('job_id', item.job_id);
        fd.append('technician_id', item.technician_id);
        fd.append('note', item.note);
        fd.append('csrf_token', item.csrf_token);
        const r = await fetch('/api/job_notes_add.php', { method: 'POST', body: fd, credentials: 'same-origin' });
        const j = await r.json();
        if (!j?.ok) throw new Error('fail');
      } else if (item.type === 'photo') {
        const fd = new FormData();
        fd.append('job_id', item.job_id);
        fd.append('technician_id', item.technician_id);
        fd.append('csrf_token', item.csrf_token);
        fd.append('tags[]', item.tag);
        fd.append('annotations[]', item.annotation || '');
        fd.append('photos[]', dataURLtoBlob(item.photo), 'photo.png');
        const r = await fetch('/api/job_photos_upload.php', { method: 'POST', body: fd, credentials: 'same-origin' });
        const j = await r.json();
        if (!j?.ok) throw new Error('fail');
      } else if (item.type === 'checklist') {
        const fd = new FormData();
        fd.append('job_id', item.job_id);
        fd.append('items', JSON.stringify(item.items));
        fd.append('csrf_token', item.csrf_token);
        const r = await fetch('/api/job_checklist_update.php', { method: 'POST', body: fd, credentials: 'same-origin' });
        const j = await r.json();
        if (!j?.ok) throw new Error('fail');
      } else if (item.type === 'completion') {
        const fd = new FormData();
        fd.append('job_id', item.job_id);
        fd.append('technician_id', item.technician_id);
        fd.append('final_note', item.final_note);
        (item.photos || []).forEach(p => fd.append('final_photos[]', p));
        (item.tags || []).forEach(t => fd.append('final_tags[]', t));
        fd.append('signature', item.signature);
        if (item.location) {
          fd.append('location_lat', item.location.lat);
          fd.append('location_lng', item.location.lng);
        }
        fd.append('csrf_token', item.csrf_token);
        const r = await fetch('/api/job_complete.php', { method: 'POST', body: fd, credentials: 'same-origin' });
        const j = await r.json();
        if (!j?.ok) throw new Error('fail');
      }
    } catch (err) {
      return false;
    }

    const deleted = await new Promise(resolve => {
      const tx = db.transaction('queue', 'readwrite');
      const store = tx.objectStore('queue');
      const del = store.delete(key);
      del.onsuccess = () => resolve(true);
      del.onerror = e => {
        console.error('Failed to delete queued item', e.target?.error || e);
        resolve(false);
      };
    });

    return deleted;
  }
  let more = true;
  while (more) {
    more = await sendOne();
  }
}

self.addEventListener('sync', event => {
  if (event.tag === 'sync-queue') {
    event.waitUntil(sendQueued());
  }
});
