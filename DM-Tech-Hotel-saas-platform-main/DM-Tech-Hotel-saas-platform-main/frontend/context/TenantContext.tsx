'use client';

import React, { createContext, useContext, useState, useEffect } from 'react';
import { usePathname, useParams } from 'next/navigation';
import portStorage from '@/lib/portStorage';

interface TenantContextType {
  tenantSlug: string | null;
  hotelId: number | null;
  groupId: number | null;
  setTenantSlug: (slug: string) => void;
  setHotelId: (id: number) => void;
}

const TenantContext = createContext<TenantContextType | undefined>(undefined);

export function TenantProvider({ children }: { children: React.ReactNode }) {
  const pathname = usePathname();
  const params = useParams();

  const [tenantSlug, setTenantSlugState] = useState<string | null>(() => {
    if (typeof window !== 'undefined') {
      return portStorage.getItem('tenant_slug') || portStorage.getItem('hotel_slug');
    }
    return null;
  });

  const [hotelId, setHotelIdState] = useState<number | null>(() => {
    if (typeof window !== 'undefined') {
      const id = portStorage.getItem('hotel_id');
      return id ? parseInt(id, 10) : null;
    }
    return null;
  });

  const [groupId, setGroupIdState] = useState<number | null>(() => {
    if (typeof window !== 'undefined') {
      const id = portStorage.getItem('group_id');
      return id ? parseInt(id, 10) : null;
    }
    return null;
  });

  useEffect(() => {
    if (typeof window === 'undefined') return;

    // 1. Extract slug from route params e.g. /branch/[slug] or /group/[slug]
    const slugFromParams = params?.slug as string | undefined;
    if (slugFromParams && slugFromParams !== tenantSlug) {
      setTenantSlugState(slugFromParams);
      portStorage.setItem('tenant_slug', slugFromParams);
    } else if (!slugFromParams && pathname) {
      // 2. Extract from pathname pattern /branch/xyz/... or /reception/xyz/...
      const match = pathname.match(/\/(?:branch|reception|group)\/([^/?#]+)/);
      if (match && match[1] && match[1] !== tenantSlug) {
        setTenantSlugState(match[1]);
        portStorage.setItem('tenant_slug', match[1]);
      }
    }
  }, [pathname, params, tenantSlug]);

  const setTenantSlug = (slug: string) => {
    setTenantSlugState(slug);
    if (typeof window !== 'undefined') {
      portStorage.setItem('tenant_slug', slug);
    }
  };

  const setHotelId = (id: number) => {
    setHotelIdState(id);
    if (typeof window !== 'undefined') {
      portStorage.setItem('hotel_id', String(id));
    }
  };

  return (
    <TenantContext.Provider value={{ tenantSlug, hotelId, groupId, setTenantSlug, setHotelId }}>
      {children}
    </TenantContext.Provider>
  );
}

export const useTenant = () => {
  const context = useContext(TenantContext);
  if (context === undefined) {
    throw new Error('useTenant must be used within a TenantProvider');
  }
  return context;
};
