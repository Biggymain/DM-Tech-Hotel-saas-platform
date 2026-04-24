'use client';

import React from 'react';
import { useRouter } from 'next/navigation';
import { ShieldAlert, RefreshCw, Home } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';

export default function UnauthorizedPage() {
  const router = useRouter();

  return (
    <div className="min-h-screen flex items-center justify-center p-4 bg-slate-950">
      <Card className="w-full max-w-md bg-white/5 border-white/10 backdrop-blur-xl shadow-2xl">
        <CardHeader className="text-center">
          <div className="flex justify-center mb-4">
            <div className="p-3 rounded-full bg-red-500/20 text-red-500">
              <ShieldAlert size={48} />
            </div>
          </div>
          <CardTitle className="text-2xl font-bold text-white">Unauthorized Access</CardTitle>
          <CardDescription className="text-slate-400">
            A secure QR scan is required to access guest services.
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-6">
          <div className="p-4 rounded-xl bg-white/5 border border-white/10 text-sm text-slate-300">
            <p>For your security, our system requires a cryptographically signed QR code to verify your location and session.</p>
            <p className="mt-2 font-medium text-indigo-400">Please rescan the QR code provided in your room or at your table.</p>
          </div>
          
          <div className="grid gap-3">
            <Button 
              onClick={() => window.location.reload()}
              className="w-full h-12 bg-white/10 hover:bg-white/20 text-white border-white/10"
            >
              <RefreshCw className="mr-2 h-4 w-4" /> Try Again
            </Button>
            <Button 
              variant="link"
              onClick={() => router.push('/')}
              className="text-slate-500 hover:text-white"
            >
              <Home className="mr-2 h-4 w-4" /> Return Home
            </Button>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
