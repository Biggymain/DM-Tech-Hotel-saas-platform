'use client';

import React, { useEffect, useState } from 'react';
import portStorage from '@/lib/portStorage';

interface PortGateProps {
  allowedPorts?: string[];
  fallbackUrl?: string;
  children: React.ReactNode;
}

/**
 * PortGate Component
 *
 * Prevents dynamic render bleed and layout flashing across ports (3000-3005).
 * Guards routes so that only authorized ports render the child UI.
 * Uses a clean neutral skeleton loader before client hydration to prevent SSR mismatch.
 */
export function PortGate({
  allowedPorts = [],
  fallbackUrl,
  children,
}: PortGateProps) {
  const [isMounted, setIsMounted] = useState(false);
  const [isAllowed, setIsAllowed] = useState(false);

  useEffect(() => {
    setIsMounted(true);
    const activePort = portStorage.getPort();

    if (allowedPorts.length === 0 || allowedPorts.includes(activePort)) {
      setIsAllowed(true);
    } else {
      setIsAllowed(false);
      if (fallbackUrl) {
        window.location.href = fallbackUrl;
      }
    }
  }, [allowedPorts, fallbackUrl]);

  // Pre-hydration / mounting state: Neutral skeleton to prevent SSR mismatch or UI flashes
  if (!isMounted) {
    return (
      <div className="min-h-screen w-full flex items-center justify-center bg-background text-foreground">
        <div className="flex flex-col items-center gap-4">
          <div className="h-10 w-10 rounded-full border-2 border-primary border-t-transparent animate-spin" />
          <p className="text-xs text-muted-foreground animate-pulse">Initializing Port Context...</p>
        </div>
      </div>
    );
  }

  if (!isAllowed) {
    return (
      <div className="min-h-screen w-full flex items-center justify-center bg-background text-foreground p-6">
        <div className="max-w-md w-full p-8 rounded-2xl border border-border bg-card text-center shadow-xl">
          <div className="h-12 w-12 rounded-full bg-destructive/10 text-destructive flex items-center justify-center mx-auto mb-4 font-bold text-xl">
            !
          </div>
          <h2 className="text-xl font-bold mb-2">Access Restricted</h2>
          <p className="text-sm text-muted-foreground mb-6">
            This operational module is not available on Port {portStorage.getPort()}.
          </p>
          {fallbackUrl && (
            <a
              href={fallbackUrl}
              className="inline-flex items-center justify-center px-6 py-2.5 rounded-lg bg-primary text-primary-foreground font-medium text-sm hover:bg-primary/90 transition-colors"
            >
              Go to Portal Home
            </a>
          )}
        </div>
      </div>
    );
  }

  return <>{children}</>;
}

export default PortGate;
