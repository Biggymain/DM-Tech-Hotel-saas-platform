import axios from 'axios';

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
    const port = window.location.port; // 3004 or 3005
    config.headers['X-Frontend-Port'] = port;

    // Inject multi-tenant context from port-isolated local storage
    const tenantId = localStorage.getItem('tenant_id');
    const branchId = localStorage.getItem('branch_id');
    const groupId  = localStorage.getItem('group_id');
    const hotelContext = localStorage.getItem('hotel_id');
    
    // Only fetch room metadata if on the in-hotel port (3004)
    if (port === '3004') {
        const roomId = localStorage.getItem('room_id');
        const outletId = localStorage.getItem('outlet_id');
        const tableNumber = localStorage.getItem('table_number');
        if (roomId) config.headers['X-Room-ID'] = roomId;
        if (outletId) config.headers['X-Outlet-ID'] = outletId;
        if (tableNumber) config.headers['X-Table-Number'] = tableNumber;
    }

    if (tenantId) config.headers['X-Tenant-ID'] = tenantId;
    if (branchId) config.headers['X-Branch-ID'] = branchId;
    if (groupId) config.headers['X-Group-ID'] = groupId;
    if (hotelContext) config.headers['X-Hotel-Context'] = hotelContext;

    // Attach guest token (often given upon room QR scan or booking completion)
    const token = localStorage.getItem(`guest_token_${port}`);
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
        const port = window.location.port;
        
        // Security Purge: Clear all session-specific data to prevent zombie sessions
        localStorage.removeItem(`guest_token_${port}`);
        localStorage.removeItem('tenant_id');
        localStorage.removeItem('branch_id');
        localStorage.removeItem('group_id');
        localStorage.removeItem('hotel_id');
        localStorage.removeItem('room_id');
        localStorage.removeItem('outlet_id');
        localStorage.removeItem('table_number');

        // Redirect to a professional session-end landing page
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
    const port = typeof window !== 'undefined' ? window.location.port : '3004';
    localStorage.setItem(`guest_token_${port}`, response.data.session_token);
  }
  return response.data;
};

export default api;
