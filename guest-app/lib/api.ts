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
  // Add authentication or guest tokens here if required later
  // const token = localStorage.getItem('guest_token');
  // if (token) {
  //   config.headers.Authorization = `Bearer ${token}`;
  // }
  return config;
});

export default api;
