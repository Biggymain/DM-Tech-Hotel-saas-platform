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

// ── Hardware Bridge ID Cache ──────────────────────────
let cachedHardwareId: string | null = typeof window !== 'undefined' ? localStorage.getItem('hardware_id') : null;

const fetchHardwareId = async () => {
  if (cachedHardwareId) return cachedHardwareId;
  try {
    // Using a direct axios call to avoid interceptor recursion
    const response = await axios.get(`${process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000'}/api/v1/hardware-id`);
    if (response.data.hardware_id) {
      cachedHardwareId = response.data.hardware_id;
      localStorage.setItem('hardware_id', cachedHardwareId!);
      return cachedHardwareId;
    }
  } catch (e) {
    console.error("Hardware Bridge unreachable:", e);
  }
  return null;
};

// ── Strict Port-Aware Request Interceptor ────────────────
api.interceptors.request.use(async (config) => {
  if (typeof window !== 'undefined') {
    const port = window.location.port;
    config.headers['X-Frontend-Port'] = port;
    
    const hId = await fetchHardwareId();
    if (hId) config.headers['X-Hardware-Id'] = hId;

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

        const errorCode = error.response?.data?.code;

        if (errorCode === 'LICENSE_UNREGISTERED') {
          window.location.href = '/activate';
          return Promise.reject(error);
        }

        if (errorCode === 'LICENSE_LOCKED' || errorCode === 'LICENSE_EXPIRED') {
          const params = new URLSearchParams();
          if (error.response?.data?.manager_email) params.set('manager', error.response.data.manager_email);
          if (error.response?.data?.owner_email) params.set('owner', error.response.data.owner_email);
          window.location.href = `/subscription-expired?${params.toString()}`;
          return Promise.reject(error);
        }

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
