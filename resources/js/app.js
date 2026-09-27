import Alpine from 'alpinejs';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { createIcons, icons } from 'lucide';

window.Alpine = Alpine;
window.Pusher = Pusher;

const statusNodes = () => document.querySelectorAll('[data-realtime-status]');
const setRealtimeState = (state) => statusNodes().forEach((node) => {
    node.dataset.state = state.toLowerCase();
    node.querySelector('[data-label]').textContent = state;
});

const initializeRealtime = () => {
    if (!navigator.onLine) setRealtimeState('OFFLINE');
    else setRealtimeState('RECONNECTING');

    const key = import.meta.env.VITE_REVERB_APP_KEY;
    if (!key) { setRealtimeState('DEGRADED'); return; }

    const echo = new Echo({
        broadcaster: 'reverb', key,
        wsHost: import.meta.env.VITE_REVERB_HOST || window.location.hostname,
        wsPort: Number(import.meta.env.VITE_REVERB_PORT || 80),
        wssPort: Number(import.meta.env.VITE_REVERB_PORT || 443),
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME || 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
    });
    window.Echo = echo;
    const connection = echo.connector.pusher.connection;
    connection.bind('connecting', () => setRealtimeState(navigator.onLine ? 'RECONNECTING' : 'OFFLINE'));
    connection.bind('connected', () => setRealtimeState('LIVE'));
    connection.bind('unavailable', () => setRealtimeState(navigator.onLine ? 'DEGRADED' : 'OFFLINE'));
    connection.bind('failed', () => setRealtimeState('DEGRADED'));
    connection.bind('disconnected', () => setRealtimeState(navigator.onLine ? 'RECONNECTING' : 'OFFLINE'));
    const organizationId = document.body.dataset.organizationId;
    if (organizationId) echo.private(`organization.${organizationId}`).listen('.organization.operational.notice', (event) => window.dispatchEvent(new CustomEvent('medgrid-notice', { detail: event.data })));
};

window.addEventListener('online', () => setRealtimeState('RECONNECTING'));
window.addEventListener('offline', () => setRealtimeState('OFFLINE'));
document.addEventListener('DOMContentLoaded', () => { createIcons({ icons }); initializeRealtime(); });
Alpine.start();