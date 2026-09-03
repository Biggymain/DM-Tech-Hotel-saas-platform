'use client';

import React from 'react';
import { useRouter, useSearchParams } from 'next/navigation';
import { 
  QrCode, 
  ChefHat, 
  Loader2, 
  Gamepad2, 
  Key, 
  Smartphone,
  Star,
  CheckCircle2,
  AlertCircle
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardDescription, CardHeader, CardTitle, CardFooter } from '@/components/ui/card';
import { toast } from 'sonner';
import api from '@/lib/api';

// This is the thin wrapper for Port 3004 (Menu)
function GuestMenuContent() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const [pin, setPin] = React.useState('');
  const [isVerifying, setIsVerifying] = React.useState(false);
  const [hasSession, setHasSession] = React.useState(false);

  // Grab booking ID from URL if present
  const urlBookingId = searchParams.get('booking_id') || searchParams.get('reservation_id');
  
  React.useEffect(() => {
    if (urlBookingId && typeof window !== 'undefined') {
      localStorage.setItem('booking_id', urlBookingId);
    }
  }, [urlBookingId]);

  // Priority: URL > localStorage
  const bookingId = typeof window !== 'undefined' ? (urlBookingId || localStorage.getItem('booking_id')) : null;

  React.useEffect(() => {
    if (typeof window !== 'undefined' && bookingId) {
        const storedPin = localStorage.getItem(`session_pin_${bookingId}`);
        if (storedPin) {
            setHasSession(true);
        }
    }
  }, [bookingId]);

  if (hasSession) {
    // If they have a session, we redirect to the actual order flow
    router.push(`/orders/${bookingId}`);
    return <div className="flex h-screen items-center justify-center bg-slate-950 text-white gap-2">
      <Loader2 className="animate-spin" /> Loading your menu...
    </div>;
  }

  const handleClaimRoom = async () => {
    if (pin.length !== 4) {
      toast.error("PIN must be exactly 4 digits.");
      return;
    }

    setIsVerifying(true);
    try {
      await api.post('/api/v1/guest/claim-room', { 
        reservation_id: bookingId, 
        pin 
      });
      
      localStorage.setItem(`session_pin_${bookingId}`, pin);
      toast.success("Room claimed! Enjoy your stay.");
      setHasSession(true);
    } catch (error) {
      toast.error("Failed to claim room. Invalid booking context.");
    } finally {
      setIsVerifying(false);
    }
  };

  return (
    <div className="min-h-[100dvh] bg-slate-950 flex flex-col items-center justify-center p-4">
      <div className="w-full max-w-md space-y-8 animate-in fade-in slide-in-from-bottom-8 duration-700">
        <div className="text-center space-y-4">
          <div className="mx-auto w-16 h-16 bg-gradient-to-br from-orange-500 to-red-600 rounded-2xl flex items-center justify-center p-0.5 shadow-xl shadow-orange-600/20 rotate-3">
             <div className="w-full h-full bg-slate-950 rounded-[14px] flex items-center justify-center">
                <Smartphone className="text-orange-500" size={32} />
             </div>
          </div>
          <div className="space-y-1">
            <h1 className="text-4xl font-extrabold tracking-tight text-white">DM Hotel</h1>
            <p className="text-slate-400 font-medium">Digital Guest Portal</p>
          </div>
        </div>

        <Card className="bg-white/5 border-white/10 backdrop-blur-2xl shadow-2xl overflow-hidden relative border-t-white/20">
          <CardHeader className="pb-2">
            <CardTitle className="text-2xl text-white flex items-center gap-2">
               <Key className="text-orange-500" size={20} />
               Instant PIN Creation
            </CardTitle>
            <CardDescription className="text-slate-400">
               Secure your in-hospitality orders. Create a 4-digit PIN for this device session.
            </CardDescription>
          </CardHeader>
          <CardContent className="space-y-6 pt-4">
            <div className="space-y-2">
              <Input
                type="password"
                placeholder="0000"
                value={pin}
                onChange={(e) => setPin(e.target.value.replace(/\D/g, '').slice(0, 4))}
                className="bg-black/40 border-white/10 text-white h-16 text-center text-4xl tracking-[0.5em] font-black focus:ring-orange-500 rounded-2xl"
                maxLength={4}
              />
            </div>
            
            <div className="flex items-center gap-3 p-4 bg-orange-500/10 border border-orange-500/20 rounded-2xl text-xs text-orange-200/80 leading-relaxed font-medium">
               <AlertCircle className="shrink-0 text-orange-500" size={18} />
               You will need this PIN to confirm orders and service requests from this device.
            </div>
          </CardContent>
          <CardFooter>
            <Button 
              onClick={handleClaimRoom}
              disabled={pin.length !== 4 || isVerifying}
              className="w-full h-14 bg-orange-600 hover:bg-orange-500 text-white text-lg font-bold rounded-2xl shadow-lg shadow-orange-600/30 transition-all hover:scale-[1.02] active:scale-[0.98]"
            >
              {isVerifying ? <Loader2 className="animate-spin mr-2" /> : <CheckCircle2 className="mr-2" size={20} />}
              Claim My Room
            </Button>
          </CardFooter>
        </Card>

        <div className="text-center text-slate-500 text-xs font-medium">
           Secured by DM-Tech Node v3.4.1
        </div>
      </div>
    </div>
  );
}

export default function GuestMenuWrapper() {
  return (
    <React.Suspense fallback={<div className="flex h-screen items-center justify-center bg-slate-950 text-white"><Loader2 className="animate-spin" /></div>}>
      <GuestMenuContent />
    </React.Suspense>
  );
}
