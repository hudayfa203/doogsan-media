import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

/**
 * Connect to Reverb using runtime config injected by the server
 * (so the same build works on staging and production).
 * Returns null when real-time is disabled.
 */
export function connectEcho(cfg) {
  if (!cfg || !cfg.key) return null;
  window.Pusher = Pusher;
  try {
    return new Echo({
      broadcaster: 'reverb',
      key: cfg.key,
      wsHost: cfg.host || location.hostname,
      wsPort: cfg.port || 80,
      wssPort: cfg.port || 443,
      forceTLS: (cfg.scheme || location.protocol.replace(':', '')) === 'https',
      enabledTransports: ['ws', 'wss'],
      authEndpoint: '/broadcasting/auth',
      withCredentials: true,
    });
  } catch (e) {
    console.warn('Real-time unavailable, using polling', e);
    return null;
  }
}
