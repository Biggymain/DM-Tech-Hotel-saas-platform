import axios from 'axios';

const api = axios.create({
  baseURL: process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000',
  headers: {
    'X-Requested-With': 'XMLHttpRequest',
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
  withCredentials: true, // Matches Backend Sanctum port-domain cookies 
});

// ── Strict Port-Aware Request Interceptor ────────────────
api.interceptors.request.use((config) => {
  if (typeof window !== 'undefined') {
    const port = window.location.port;
    config.headers['X-Frontend-Port'] = port;
    
    // Do not attach admin tokens if we are on the group landing pages
    if (port !== '3001') {
      const token = localStorage.getItem(`auth_token_${port}`); // Isolate storage globally
      if (token) {
        config.headers.Authorization = `Bearer ${token}`;
      }
      
      const hotelContext = localStorage.getItem(`hotel_context_${port}`);
      if (hotelContext) {
        config.headers['X-Hotel-Context'] = hotelContext;
      }
    }
  }
  return config;
});

// ── Strict Port-Aware Response Interceptor ────────────────
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401 || error.response?.status === 403) {
      if (typeof window !== 'undefined') {
        const port = window.location.port;

        // Port 3001 never redirects (public Group Landing)
        if (port === '3001') return Promise.reject(error);

        // Protected Routes list
        const protectedPrefixes = ['/dashboard', '/reception', '/organization', '/kds', '/pos', '/housekeeping', '/profile'];
        const isProtected = protectedPrefixes.some(p => window.location.pathname.startsWith(p));

        if (isProtected || error.response?.status === 403) {
          localStorage.removeItem(`auth_token_${port}`);
          switch (port) {
            case '3000': window.location.href = '/admin/login'; break; // Admin Auth
            case '3002': window.location.href = '/manager/login'; break; // Branch Dashboard
            case '3003': window.location.href = '/staff/pin'; break; // Staff Operations (PIN)
            default: window.location.href = '/login';
          }
        }
      }
    }
    return Promise.reject(error);
  }
);

export default api;
