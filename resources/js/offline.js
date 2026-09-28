const DB_NAME = 'medgrid-clinical-queue';
const STORE_NAME = 'operations';

const openQueue = () => new Promise((resolve, reject) => {
    const request = indexedDB.open(DB_NAME, 1);
    request.onupgradeneeded = () => {
        if (! request.result.objectStoreNames.contains(STORE_NAME)) {
            request.result.createObjectStore(STORE_NAME, { keyPath: 'operation_id' });
        }
    };
    request.onsuccess = () => resolve(request.result);
    request.onerror = () => reject(request.error);
});

const storeRequest = async (mode, callback) => {
    const database = await openQueue();

    return new Promise((resolve, reject) => {
        const transaction = database.transaction(STORE_NAME, mode);
        callback(transaction.objectStore(STORE_NAME));
        transaction.oncomplete = () => {
            database.close();
            resolve();
        };
        transaction.onerror = () => {
            database.close();
            reject(transaction.error);
        };
    });
};

const queuedOperations = async () => {
    const database = await openQueue();

    return new Promise((resolve, reject) => {
        const request = database.transaction(STORE_NAME, 'readonly').objectStore(STORE_NAME).getAll();
        request.onsuccess = () => {
            database.close();
            resolve(request.result);
        };
        request.onerror = () => {
            database.close();
            reject(request.error);
        };
    });
};

const setSyncState = (state, count = 0) => {
    const node = document.querySelector('[data-sync-status]');
    if (! node) return;

    const labels = {
        synced: 'SYNCED',
        queued: 'SAVED LOCALLY - WAITING TO SYNC',
        syncing: 'SYNCING',
        blocked: 'SYNC NEEDS REVIEW',
    };

    node.dataset.state = state;
    node.querySelector('[data-sync-label]').textContent = labels[state];
    const badge = node.querySelector('[data-sync-count]');
    badge.textContent = count;
    badge.hidden = count === 0;
};

const refreshSyncState = async () => {
    const operations = await queuedOperations();
    const blocked = operations.some((operation) => operation.blocked);
    setSyncState(blocked ? 'blocked' : operations.length ? 'queued' : 'synced', operations.length);
};

const newUlid = () => {
    const alphabet = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
    let time = Date.now();
    let encodedTime = '';

    for (let index = 0; index < 10; index += 1) {
        encodedTime = alphabet[time % 32] + encodedTime;
        time = Math.floor(time / 32);
    }

    const random = new Uint8Array(16);
    crypto.getRandomValues(random);
    let encodedRandom = '';

    for (let index = 0; index < 16; index += 1) {
        encodedRandom += alphabet[random[index] % 32];
    }

    return encodedTime + encodedRandom;
};

const serializeVital = (form) => {
    const formData = new FormData(form);
    const operationId = formData.get('operation_id');
    const payload = {};

    formData.forEach((value, key) => {
        if (! ['_token', 'operation_id'].includes(key) && value !== '') {
            payload[key] = value;
        }
    });

    return {
        operation_id: operationId,
        operation_type: 'VITAL_CREATE',
        target_id: form.dataset.encounterId,
        payload,
        captured_at: new Date().toISOString(),
        device_id: 'browser-pwa',
        blocked: false,
    };
};

const saveVitalLocally = async (form) => {
    const operation = serializeVital(form);
    await storeRequest('readwrite', (store) => store.put(operation));
    form.reset();
    form.querySelector('[name=operation_id]').value = newUlid();
    await refreshSyncState();
};

const syncOfflineOperations = async () => {
    if (! navigator.onLine || ! document.querySelector('[data-sync-status]')) return;

    const allOperations = await queuedOperations();
    const operations = allOperations.filter((operation) => ! operation.blocked);

    if (! operations.length) {
        await refreshSyncState();
        return;
    }

    setSyncState('syncing', allOperations.length);

    try {
        const response = await fetch('/api/v1/sync/operations', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                ...(window.Echo?.socketId() ? { 'X-Socket-ID': window.Echo.socketId() } : {}),
            },
            body: JSON.stringify({
                operations: operations.map(({ blocked, ...operation }) => operation),
            }),
        });

        if (! response.ok) throw new Error('Sync failed with HTTP ' + response.status);

        const body = await response.json();

        await Promise.all(body.data.map((result) => {
            if (['ACCEPTED', 'DUPLICATE'].includes(result.outcome)) {
                return storeRequest('readwrite', (store) => store.delete(result.operation_id));
            }

            const operation = operations.find((item) => item.operation_id === result.operation_id);
            return storeRequest('readwrite', (store) => store.put({ ...operation, blocked: true, outcome: result.outcome }));
        }));

        const remaining = await queuedOperations();
        if (! remaining.length) {
            window.location.reload();
            return;
        }

        await refreshSyncState();
    } catch {
        await refreshSyncState();
    }
};

const initializeOfflineVitals = () => {
    if (! ('indexedDB' in window)) return;

    document.querySelectorAll('[data-offline-vital-form]').forEach((form) => {
        form.addEventListener('submit', async (event) => {
            if (navigator.onLine) return;

            event.preventDefault();
            await saveVitalLocally(form);
        });
    });

    refreshSyncState();
    syncOfflineOperations();
};

document.addEventListener('DOMContentLoaded', () => {
    initializeOfflineVitals();

    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('/sw.js');
    }
});

window.addEventListener('online', syncOfflineOperations);
