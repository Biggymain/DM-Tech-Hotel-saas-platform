'use client';

import React from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import { 
  ConciergeBell, 
  Loader2, 
  ArrowLeft,
  Navigation,
  CheckCircle2,
  AlertCircle
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardFooter } from '@/components/ui/card';
import GuestServiceHub from '../services/page';
import ThemeLoader from '../../components/ThemeLoader';

// This is the thin wrapper for Port 3005 (Booking)
// It applies tenant-specific themes and renders the Booking/Service component.
function GuestBookingContent() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const branchId = searchParams.get('branch');
  const [isThemeLoading, setIsThemeLoading] = React.useState(true);

  return (
    <div className="min-h-screen transition-colors duration-700 bg-background text-foreground">
      {/* Dynamic Theme Engine Loader */}
      <ThemeLoader onLoaded={() => setIsThemeLoading(false)} />

      {isThemeLoading ? (
        <div className="flex h-screen items-center justify-center bg-slate-950 text-white gap-3">
          <Loader2 className="animate-spin text-indigo-500" /> 
          <span className="font-semibold tracking-wider">Syncing Hotel Assets...</span>
        </div>
      ) : (
        <div className="animate-in fade-in zoom-in-95 duration-500">
           <GuestServiceHub />
        </div>
      )}
    </div>
  );
}

export default function GuestBookingWrapper() {
  return (
    <React.Suspense fallback={<div className="flex h-screen items-center justify-center bg-slate-950 text-white"><Loader2 className="animate-spin text-indigo-500" /></div>}>
      <GuestBookingContent />
    </React.Suspense>
  );
}
