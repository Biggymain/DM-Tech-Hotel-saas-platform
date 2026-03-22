'use client';

import React, { createContext, useContext, useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import api from '@/lib/api';

export interface User {
  id: number;
  name: string;
  email: string;
  hotel_id: number | null;
  hotel_slug: string | null;
  hotel_group_id: number | null;
  outlet_id: number | null;
  is_super_admin: boolean;
  roles: { id: number; name: string; slug: string }[];
  active_modules: string[];
  permissions: string[];
  is_on_duty: boolean;
  last_duty_toggle_at: string | null;
  must_change_password: boolean;
}

interface AuthContextType {
  user: User | null;
  isLoading: boolean;
  login: (credentials: any) => Promise<void>;
  logout: () => Promise<void>;
  checkAuth: () => Promise<void>;
  toggleDuty: () => Promise<void>;
  hasModule: (moduleSlug: string) => boolean;
  hasPermission: (permissionSlug: string) => boolean;
}

const AuthContext = createContext<AuthContextType | undefined>(undefined);

/**
 * Role → Route mapping.
 * After login, users are redirected to the UI that matches their job function.
 * Evaluated in priority order — first match wins.
 */
const ROLE_ROUTES: Array<{ slugs: string[]; path: string }> = [
  { slugs: ['superadmin', 'super-admin'],        path: '/organization' },
  { slugs: ['group-admin'],                       path: '/organization' },
  { slugs: ['hotelowner', 'general-manager', 'generalmanager', 'manager'],     path: '/dashboard' },
  { slugs: ['itspecialist', 'it-specialist'],      path: '/dashboard' },
  { slugs: ['chef', 'kitchen-manager'],           path: '/kds' },
  { slugs: ['waiter', 'steward', 'bartender'],    path: '/pos/mobile' },
  { slugs: ['housekeeping', 'housekeeper'],       path: '/housekeeping' },
  { slugs: ['receptionist', 'front-desk'],        path: '/reception' },
];

function resolveRedirectPath(user: User): string {
  // Force password change if required
  if (user.must_change_password) return '/profile?force_password_change=true';

  // Super Admins go to Platform Management
  if (user.is_super_admin) return '/organization';
  
  // Group Admins go to Group Management
  if (user.hotel_group_id && !user.hotel_id) return '/organization';

  const userSlugs = user.roles.map((r) => r.slug.toLowerCase());

  for (const { slugs, path } of ROLE_ROUTES) {
    if (slugs.some((s) => userSlugs.includes(s))) {
      if (path === '/reception' && user.hotel_slug) {
        return `/reception/${user.hotel_slug}`;
      }
      return path;
    }
  }

  return '/dashboard'; // fallback — standard dashboard
}

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  const isGuestPortal = typeof window !== 'undefined' && window.location.port === '3001';

  const checkAuth = async () => {
    // On the guest portal port, never attempt admin authentication
    if (isGuestPortal) {
      setIsLoading(false);
      return;
    }
    try {
      const { data } = await api.get('/api/v1/auth/me');
      setUser(data);
    } catch {
      setUser(null);
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    checkAuth();
  }, []);

  const login = async (credentials: any) => {
    // If a raw token is passed (from register-group), store it and skip the API call
    if (credentials?.token && credentials?.user) {
      // Store auth token for API interceptor
      if (typeof window !== 'undefined') {
        localStorage.setItem('auth_token', credentials.token);
      }
      setUser(credentials.user);
      return;
    }

    await api.get('/sanctum/csrf-cookie');
    const { data } = await api.post('/api/v1/auth/login', credentials);

    // Store token if response includes one (Sanctum SPA uses cookies, but token API differs)
    if (data?.token && typeof window !== 'undefined') {
      localStorage.setItem('auth_token', data.token);
    }

    await checkAuth();

    // Role-based redirect after login
    const freshUser = data?.user ?? null;
    if (freshUser || user) {
      const redirectTo = resolveRedirectPath(freshUser ?? user!);
      if (typeof window !== 'undefined' && window.location.pathname === '/login') {
        window.location.href = redirectTo;
      }
    }
  };

  const logout = async () => {
    try {
      await api.post('/api/v1/auth/logout');
    } catch {
      // Even if logout API fails, clear local state
    }
    setUser(null);
    if (typeof window !== 'undefined') {
      localStorage.removeItem('auth_token');
      window.location.href = '/login';
    }
  };

  const toggleDuty = async () => {
    try {
      const { data } = await api.post('/api/v1/staff/toggle-duty');
      setUser(prev => prev ? { 
        ...prev, 
        is_on_duty: data.is_on_duty,
        last_duty_toggle_at: data.last_duty_toggle_at 
      } : null);
    } catch (error) {
      console.error('Failed to toggle duty status', error);
    }
  };

  const hasModule = (moduleSlug: string) => {
    if (!user) return false;
    if (user.is_super_admin) return true;
    if (user.hotel_group_id && !user.hotel_id) return true; // Group Admin bypass
    return user.active_modules?.includes(moduleSlug) || false;
  };

  const hasPermission = (permissionSlug: string) => {
    if (!user) return false;
    if (user.is_super_admin) return true;
    return user.permissions?.includes(permissionSlug) || user.permissions?.includes('*') || false;
  };

  return (
    <AuthContext.Provider value={{ user, isLoading, login, logout, checkAuth, toggleDuty, hasModule, hasPermission }}>
      {children}
    </AuthContext.Provider>
  );
}

export const useAuth = () => {
  const context = useContext(AuthContext);
  if (context === undefined) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  return context;
};

/**
 * Hook: returns the redirect path for the currently logged-in user.
 * Useful on post-login or protected page redirects.
 */
export function useRoleRedirect() {
  const { user } = useAuth();
  if (!user) return '/login';
  return resolveRedirectPath(user);
}
