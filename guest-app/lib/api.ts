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
        
        if (error.response?.status === 403) {
             localStorage.removeItem(`guest_token_${port}`);
        }

        // Port 3004 (In-Hotel tablet): Lost session implies tablet needs re-pairing
        if (port === '3004') {
             console.warn("Tablet disconnected from room context or forbidden!");
             window.location.href = '/pairing-error';
        }
        // Port 3005 (Booking engine): Guest booking session expired or forbidden
        else if (port === '3005') {
             window.location.href = '/booking/timeout';
        }
    }
    return Promise.reject(error);
  }
);

export default api;
