// /public/js/offline.js
(() => {
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

  async function addToQueue(item) {
    const db = await openDB();
    const tx = db.transaction('queue', 'readwrite');
    tx.objectStore('queue').add(item);
    return new Promise((res, rej) => {
      tx.oncomplete = res;
      tx.onerror = () => rej(tx.error);
    });
  }

  async function registerSync() {
    if ('serviceWorker' in navigator && 'SyncManager' in window) {
      try {
        const reg = await navigator.serviceWorker.ready;
        await reg.sync.register('sync-queue');
      } catch (e) {
        // ignore
      }
    }
  }

  function updateStatus() {
    const banner = document.getElementById('network-banner');
    if (!banner) return;
    if (navigator.onLine) {
      banner.textContent = 'Online';
      banner.className = 'alert alert-success text-center small';
    } else {
      banner.textContent = 'Offline: changes will sync when back online';
      banner.className = 'alert alert-warning text-center small';
    }
    banner.classList.remove('d-none');
  }

  window.addEventListener('online', () => {
    updateStatus();
    registerSync();
  });
  window.addEventListener('offline', updateStatus);

  document.addEventListener('DOMContentLoaded', () => {
    updateStatus();
    registerSync();
  });

  window.offlineQueue = {
    add: async item => {
      await addToQueue(item);
      await registerSync();
    }
  };
})();
