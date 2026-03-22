import axios from 'axios';

// Configure Axios instance for the guest app
const api = axios.create({
  baseURL: process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
  withCredentials: true,
});

api.interceptors.request.use((config) => {
  if (typeof window !== 'undefined') {
    // Inject multi-tenant context from local storage
    const tenantId = localStorage.getItem('tenant_id');
    const branchId = localStorage.getItem('branch_id');
    const roomId = localStorage.getItem('room_id');
    const outletId = localStorage.getItem('outlet_id');
    const tableNumber = localStorage.getItem('table_number');

    if (tenantId) config.headers['X-Tenant-ID'] = tenantId;
    if (branchId) config.headers['X-Branch-ID'] = branchId;
    if (roomId) config.headers['X-Room-ID'] = roomId;
    if (outletId) config.headers['X-Outlet-ID'] = outletId;
    if (tableNumber) config.headers['X-Table-Number'] = tableNumber;

    // Support standard session tokens if applicable
    const token = localStorage.getItem('guest_token');
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }
  }
  return config;
});

export default api;
