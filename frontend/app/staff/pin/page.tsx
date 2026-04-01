'use client';

import React from 'react';
import KDSPage from '@/app/(dashboard)/kds/page';
import { useAuth } from '@/context/AuthProvider';
import { useRouter } from 'next/navigation';
import { 
  Lock, 
  Key, 
  CheckCircle2, 
  AlertCircle,
  Loader2 
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { toast } from 'sonner';
import api from '@/lib/api';

export default function StaffPinWrapper() {
  const { user, checkAuth } = useAuth();
  const router = useRouter();
  const [pin, setPin] = React.useState('');
  const [isVerifying, setIsVerifying] = React.useState(false);

  // If user is already on-boarded, just show the KDS
  if (user && !user.requires_onboarding) {
    return <KDSPage />;
  }

  const handleSetup = async () => {
    if (pin.length !== 4) {
      toast.error("PIN must be exactly 4 digits.");
      return;
    }

    setIsVerifying(true);
    try {
      await api.post('/api/v1/staff/setup', { pin });
      toast.success("Security PIN set successfully!");
      await checkAuth(); // Refresh user state to clear requires_onboarding
    } catch (error) {
      toast.error("Failed to set PIN. Please try again.");
    } finally {
      setIsVerifying(false);
    }
  };

  return (
    <div className="min-h-screen bg-slate-950 flex items-center justify-center p-4">
      <Card className="w-full max-w-md bg-slate-900 border-slate-800 text-white shadow-2xl">
        <CardHeader className="text-center space-y-2">
          <div className="mx-auto w-12 h-12 bg-indigo-500/20 rounded-full flex items-center justify-center mb-2">
            <Lock className="text-indigo-400" size={24} />
          </div>
          <CardTitle className="text-2xl font-bold tracking-tight">Security Onboarding</CardTitle>
          <CardDescription className="text-slate-400">
            Welcome, {user?.name}. Please set your 4-digit Security PIN to access the Kitchen System.
          </CardDescription>
        </CardHeader>
        <CardContent className="space-y-6">
          <div className="space-y-4">
            <div className="relative">
              <Input
                type="password"
                placeholder="Enter 4-digit PIN"
                value={pin}
                onChange={(e) => setPin(e.target.value.replace(/\D/g, '').slice(0, 4))}
                className="bg-slate-950 border-slate-800 text-white h-14 text-center text-2xl tracking-[1em] focus:ring-indigo-500"
                maxLength={4}
              />
              <Key className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-600" size={20} />
            </div>
            
            <div className="bg-amber-500/10 border border-amber-500/20 rounded-xl p-3 flex gap-3 text-xs text-amber-200/80">
              <AlertCircle className="shrink-0" size={16} />
              <p>This PIN will be required for all future logins on Port 3003. Keep it secret.</p>
            </div>
          </div>

          <Button 
            onClick={handleSetup}
            disabled={pin.length !== 4 || isVerifying}
            className="w-full h-12 bg-indigo-600 hover:bg-indigo-500 text-white font-bold rounded-xl shadow-lg shadow-indigo-600/20"
          >
            {isVerifying ? <Loader2 className="animate-spin mr-2" /> : <CheckCircle2 className="mr-2" size={18} />}
            Activate Staff Profile
          </Button>
        </CardContent>
      </Card>
    </div>
  );
}
