import axios from 'axios';

const api = axios.create({
  baseURL: process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000',
  headers: {
    'X-Requested-With': 'XMLHttpRequest',
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
  withCredentials: true, // Sanctum session cookies + CORS credentials
});

// ── Request interceptor: attach Bearer token from localStorage ────────────────
// Only attach admin tokens on the admin port (3000). Guest portal (3001) runs
// entirely unauthenticated from the admin perspective.
api.interceptors.request.use((config) => {
  if (typeof window !== 'undefined') {
    const isGuestPortal = window.location.port === '3001';

    if (!isGuestPortal) {
      const token = localStorage.getItem('auth_token');
      if (token) {
        config.headers.Authorization = `Bearer ${token}`;
      }

      const hotelContext = localStorage.getItem('hotel_context');
      if (hotelContext) {
        config.headers['X-Hotel-Context'] = hotelContext;
      }
    }
  }
  return config;
});

// ── Response interceptor: redirect to login on 401 ───────────────────────────
// On the guest portal port (3001) NEVER redirect to /login.
// On the admin port, only redirect for protected routes.
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      if (typeof window !== 'undefined') {
        const isGuestPortal = window.location.port === '3001';
        if (isGuestPortal) {
          // Guest portal: silently reject — no login redirect ever
          return Promise.reject(error);
        }

        const protectedPrefixes = ['/dashboard', '/reception', '/organization', '/kds', '/pos', '/housekeeping', '/profile'];
        const path = window.location.pathname;
        const isProtected = protectedPrefixes.some(p => path.startsWith(p));

        if (isProtected) {
          localStorage.removeItem('auth_token');
          window.location.href = '/login';
        }
      }
    }
    return Promise.reject(error);
  }
);

export default api;
