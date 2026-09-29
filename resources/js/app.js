import './offline';
import Alpine from 'alpinejs';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import {
    Activity, Ambulance, ArrowLeft, ArrowRight, Ban, Building2, Check, ChevronDown,
    ClipboardCheck, ClipboardPlus, Clock3, CloudUpload, createIcons, Eye, Grid2X2, Hospital, KeyRound, LayoutDashboard,
    LogIn, LogOut, MapPin, Navigation, Pencil, Plus, Radio, RadioTower, RefreshCw, Save, ScrollText,
    Settings2, ShieldAlert, Stethoscope, TriangleAlert, UserPlus, UserRound, Users, X,
} from 'lucide';

window.Alpine = Alpine;
window.Pusher = Pusher;

const icons = {
    Activity, Ambulance, ArrowLeft, ArrowRight, Ban, Building2, Check, ChevronDown,
    ClipboardCheck, ClipboardPlus, Clock3, CloudUpload, Eye, Grid2X2, Hospital, KeyRound, LayoutDashboard, LogIn, LogOut,
    MapPin, Navigation, Pencil, Plus, Radio, RadioTower, RefreshCw, Save, ScrollText, Settings2, ShieldAlert,
    Stethoscope, TriangleAlert, UserPlus, UserRound, Users, X,
};
const statusNodes = () => document.querySelectorAll('[data-realtime-status]');
const setRealtimeState = (state) => statusNodes().forEach((node) => {
    node.dataset.state = state.toLowerCase();
    node.querySelector('[data-label]').textContent = state;
});
const initializeRealtime = () => {
    setRealtimeState(navigator.onLine ? 'RECONNECTING' : 'OFFLINE');
    const key = import.meta.env.VITE_REVERB_APP_KEY;
    if (! key) {
        setRealtimeState('DEGRADED');
        return;
    }
    const echo = new Echo({
        broadcaster: 'reverb',
        key,
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
    if (organizationId) {
        echo.private(`organization.${organizationId}`).listen('.organization.operational.notice', (event) => {
            window.dispatchEvent(new CustomEvent('medgrid-notice', { detail: event.data }));
        });
    }

    const refresh = () => window.location.reload();
    const operationalBoard = document.querySelector('[data-dispatch-board], [data-ambulance-board]');
    const hospitalBoard = document.querySelector('[data-hospital-board]');
    const medicalBoard = document.querySelector('[data-medical-board]');
    if (organizationId && operationalBoard) {
        echo.private(`dispatch.${organizationId}`).listen('.dispatch.state.changed', refresh);
        if (operationalBoard.dataset.encounterId) {
            echo.private(`encounter.${operationalBoard.dataset.encounterId}`).listen('.clinical.state.changed', refresh);
        }
    }
    if (hospitalBoard?.dataset.hospitalId) {
        echo.private(`hospital.${hospitalBoard.dataset.hospitalId}`).listen('.hospital.state.changed', refresh);
    }
    if (medicalBoard?.dataset.encounterId) {
        echo.private(`encounter.${medicalBoard.dataset.encounterId}`).listen('.destination.state.changed', refresh);
    }
    if (operationalBoard?.dataset.encounterId) {
        echo.private(`encounter.${operationalBoard.dataset.encounterId}`).listen('.destination.state.changed', refresh);
    }
    if (operationalBoard || hospitalBoard || medicalBoard) {
        let connected = false;
        connection.bind('connected', () => {
            if (connected) {
                refresh();
            }
            connected = true;
        });
    }
};
window.addEventListener('online', () => setRealtimeState('RECONNECTING'));
window.addEventListener('offline', () => setRealtimeState('OFFLINE'));
document.addEventListener('DOMContentLoaded', () => {
    createIcons({ icons });
    document.querySelectorAll('[data-resource-form]').forEach((form) => {
        const select = form.querySelector('[data-resource-select]');
        select?.addEventListener('change', () => {
            form.action = select.value;
        });
        if (select?.value) {
            form.action = select.value;
        }
    });
    initializeRealtime();
});
Alpine.start();