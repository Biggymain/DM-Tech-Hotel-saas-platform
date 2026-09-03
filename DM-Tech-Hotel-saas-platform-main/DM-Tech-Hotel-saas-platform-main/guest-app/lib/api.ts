import axios from 'axios';
import portStorage from './portStorage';

// Configure Axios explicitly for Guest contexts (Ports 3004 - 3005)
const api = axios.create({
  baseURL: process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
  withCredentials: true,
});

// ── Guest Application Request Interceptor ────────────────
api.interceptors.request.use((config) => {
  if (typeof window !== 'undefined') {
    const port = portStorage.getPort();
    config.headers['X-Frontend-Port'] = port;
    config.headers['X-App-Port'] = port;

    // Inject multi-tenant context from port-isolated local storage
    const tenantId = portStorage.getItem('tenant_id');
    const tenantSlug = portStorage.getItem('tenant_slug');
    const branchId = portStorage.getItem('branch_id');
    const groupId  = portStorage.getItem('group_id');
    const hotelContext = portStorage.getItem('hotel_id');
    
    // Only fetch room metadata if on the in-hotel port (3004)
    if (port === '3004') {
      const roomId = portStorage.getItem('room_id');
      const outletId = portStorage.getItem('outlet_id');
      const tableNumber = portStorage.getItem('table_number');
      if (roomId) config.headers['X-Room-ID'] = roomId;
      if (outletId) config.headers['X-Outlet-ID'] = outletId;
      if (tableNumber) config.headers['X-Table-Number'] = tableNumber;
    }

    if (tenantId) config.headers['X-Tenant-ID'] = tenantId;
    if (tenantSlug) config.headers['X-Tenant-Slug'] = tenantSlug;
    if (branchId) config.headers['X-Branch-ID'] = branchId;
    if (groupId) config.headers['X-Group-ID'] = groupId;
    if (hotelContext) config.headers['X-Hotel-Context'] = hotelContext;

    // Attach guest token
    const token = portStorage.getItem('guest_token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
  }
  return config;
});

// ── Guest Response Interceptor for 401 Expirations ────────────────
api.interceptors.response.use(
  (response) => response,
  (error) => {
    if ((error.response?.status === 401 || error.response?.status === 403) && typeof window !== 'undefined') {
      // Security Purge: Clear strictly active port session keys
      portStorage.clearPortSession();

      // Redirect to session-expired landing page
      window.location.href = '/session-expired';
    }
    return Promise.reject(error);
  }
);

export const startSession = async (payload: {
  hotel_id: string;
  context_type: 'room' | 'outlet' | 'table';
  context_id: string | number;
  signature: string;
  device_info?: string;
}) => {
  const response = await api.post('/api/v1/guest/session/start', payload);
  if (response.data.session_token) {
    portStorage.setItem('guest_token', response.data.session_token);
  }
  return response.data;
};

export default api;
