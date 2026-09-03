'use client';

import React, { createContext, useContext, useState, useEffect } from 'react';
import { useRouter } from 'next/navigation';
import api from '@/lib/api';
import portStorage from '@/lib/portStorage';

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
  password_changed_at: string | null;
  requires_onboarding?: boolean;
  license?: {
    status: string;
    expires_at: string | null;
    manager_email: string | null;
    owner_email: string | null;
    days_remaining: number;
  };
  hotel?: {
    id: number;
    name: string;
    departments?: { id: number; name: string }[];
  } | null;
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

const ROLE_ROUTES: Array<{ slugs: string[]; path: string }> = [
  { slugs: ['superadmin', 'super-admin'],        path: '/master-control' },
  { slugs: ['group-admin'],                       path: '/organization' },
  { slugs: ['hotelowner', 'general-manager', 'generalmanager', 'manager'],     path: '/dashboard' },
  { slugs: ['itspecialist', 'it-specialist'],      path: '/dashboard' },
  { slugs: ['chef', 'kitchen-manager'],           path: '/kds' },
  { slugs: ['waiter', 'steward', 'bartender'],    path: '/pos' },
  { slugs: ['housekeeping', 'housekeeper'],       path: '/housekeeping' },
  { slugs: ['receptionist', 'front-desk'],        path: '/reception' },
];

function resolveRedirectPath(user: User): string {
  const isPort3003 = typeof window !== 'undefined' && portStorage.getPort() === '3003';

  if (user.must_change_password) {
    if (isPort3003) {
      return '/staff/security-setup';
    }
    return '/profile?force_password_change=true';
  }

  if (user.is_super_admin) return '/master-control';
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

  return '/dashboard';
}

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<User | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  const checkAuth = async () => {
    if (typeof window === 'undefined') {
      setIsLoading(false);
      return;
    }

    const port = portStorage.getPort();

    // Port 3001 is public onboarding
    if (port === '3001') {
      setIsLoading(false);
      return;
    }

    // Check if token exists in port storage
    const token = portStorage.getItem('auth_token');
    if (!token) {
      setUser(null);
      setIsLoading(false);
      return;
    }

    try {
      const { data } = await api.get('/api/v1/auth/me');
      setUser(data);
      if (data?.hotel_slug) {
        portStorage.setItem('hotel_slug', data.hotel_slug);
      }
      if (data?.hotel_id) {
        portStorage.setItem('hotel_id', String(data.hotel_id));
      }
    } catch {
      setUser(null);
      portStorage.removeItem('auth_token');
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    checkAuth();
  }, []);

  const login = async (credentials: any) => {
    if (credentials?.token && credentials?.user) {
      portStorage.setItem('auth_token', credentials.token);
      if (credentials.user.hotel_slug) {
        portStorage.setItem('hotel_slug', credentials.user.hotel_slug);
      }
      setUser(credentials.user);
      return;
    }

    await api.get('/sanctum/csrf-cookie').catch(() => {});
    const { data } = await api.post('/api/v1/auth/login', credentials);

    if (data?.token) {
      portStorage.setItem('auth_token', data.token);
    }
    if (data?.user?.hotel_slug) {
      portStorage.setItem('hotel_slug', data.user.hotel_slug);
    }
    if (data?.user?.hotel_id) {
      portStorage.setItem('hotel_id', String(data.user.hotel_id));
    }

    const freshUser = data?.user ?? null;
    setUser(freshUser);

    if (freshUser) {
      const redirectTo = resolveRedirectPath(freshUser);
      if (typeof window !== 'undefined') {
        window.location.href = redirectTo;
      }
    }
  };

  const logout = async () => {
    try {
      await api.post('/api/v1/auth/logout');
    } catch {
      // Ignore API logout errors
    }
    setUser(null);
    portStorage.clearPortSession();

    if (typeof window !== 'undefined') {
      const port = portStorage.getPort();
      switch (port) {
        case '3000': window.location.href = '/master-control'; break;
        case '3002': window.location.href = '/manager/login'; break;
        case '3003': window.location.href = '/staff/pin'; break;
        default: window.location.href = '/login';
      }
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
    if (user.hotel_group_id && !user.hotel_id) return true;
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

export function useRoleRedirect() {
  const { user } = useAuth();
  if (!user) return '/login';
  return resolveRedirectPath(user);
}
