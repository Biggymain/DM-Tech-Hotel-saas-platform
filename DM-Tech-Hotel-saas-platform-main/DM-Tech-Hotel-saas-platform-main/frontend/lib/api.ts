import axios from 'axios';
import portStorage from './portStorage';

const api = axios.create({
  baseURL: process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000',
  headers: {
    'X-Requested-With': 'XMLHttpRequest',
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
  withCredentials: true,
});

// ── Hardware Bridge ID Cache ──────────────────────────
let cachedHardwareId: string | null = typeof window !== 'undefined' ? portStorage.getItem('hardware_id') : null;

const fetchHardwareId = async () => {
  if (cachedHardwareId) return cachedHardwareId;
  try {
    const response = await axios.get(`${process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000'}/api/v1/hardware-id`);
    if (response.data.hardware_id) {
      cachedHardwareId = response.data.hardware_id;
      portStorage.setItem('hardware_id', cachedHardwareId!);
      return cachedHardwareId;
    }
  } catch (e) {
    // Gracefully handle local dev where hardware-id endpoint is optional
  }
  return null;
};

// ── Strict Port-Aware Request Interceptor ────────────────
api.interceptors.request.use(async (config) => {
  if (typeof window !== 'undefined') {
    const port = portStorage.getPort();
    config.headers['X-Frontend-Port'] = port;
    config.headers['X-App-Port'] = port;

    const hId = await fetchHardwareId();
    if (hId) config.headers['X-Hardware-Id'] = hId;

    // Attach dynamic tenant slug / context if present in port storage
    const tenantSlug = portStorage.getItem('tenant_slug') || portStorage.getItem('hotel_slug');
    if (tenantSlug) {
      config.headers['X-Tenant-Slug'] = tenantSlug;
    }

    const hotelContext = portStorage.getItem('hotel_context') || portStorage.getItem('hotel_id');
    if (hotelContext) {
      config.headers['X-Hotel-Context'] = hotelContext;
    }

    // Do not attach admin tokens if we are on the public group landing port
    if (port !== '3001') {
      const token = portStorage.getItem('auth_token');
      if (token) {
        config.headers.Authorization = `Bearer ${token}`;
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
        const port = portStorage.getPort();

        // Port 3001 never automatically redirects (public Group Landing)
        if (port === '3001') return Promise.reject(error);

        const protectedPrefixes = ['/dashboard', '/reception', '/organization', '/kds', '/pos', '/housekeeping', '/profile', '/branch'];
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

        if (isProtected || error.response?.status === 401) {
          // Clear only this port's session keys
          portStorage.removeItem('auth_token');
          portStorage.removeItem('hotel_context');

          switch (port) {
            case '3000': window.location.href = '/master-control'; break;
            case '3002': window.location.href = '/manager/login'; break;
            case '3003': window.location.href = '/staff/pin'; break;
            default: window.location.href = '/login';
          }
        }
      }
    }
    return Promise.reject(error);
  }
);

export default api;
