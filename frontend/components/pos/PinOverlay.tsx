'use client';

import React, { useState } from 'react';
import { 
  X, 
  Delete, 
  Lock, 
  Loader2, 
  ShieldCheck,
  AlertCircle 
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { toast } from 'sonner';
import api from '@/lib/api';

interface PinOverlayProps {
  staffMember: { id: number; name: string };
  onSuccess: (token: string) => void;
  onCancel: () => void;
}

export default function PinOverlay({ staffMember, onSuccess, onCancel }: PinOverlayProps) {
  const [pin, setPin] = useState('');
  const [isVerifying, setIsVerifying] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleNumberClick = (num: string) => {
    if (pin.length < 6) {
      setPin(prev => prev + num);
      setError(null);
    }
  };

  const handleDelete = () => {
    setPin(prev => prev.slice(0, -1));
  };

  const handleVerify = async () => {
    if (pin.length < 4) return;
    
    setIsVerifying(true);
    setError(null);
    
    try {
      // Using the existing staff-pin login endpoint
      const { data } = await api.post('/api/v1/auth/staff-pin', {
        staff_id: staffMember.id,
        pin: pin
      }, {
        headers: { 'X-Frontend-Port': '3003' }
      });

      toast.success(`Welcome back, ${staffMember.name}`);
      onSuccess(data.token);
    } catch (err: any) {
      setError(err.response?.data?.message || 'Invalid PIN');
      setPin(''); // Clear PIN on error for security
      toast.error('Identity verification failed.');
    } finally {
      setIsVerifying(false);
    }
  };

  // Keyboard support
  React.useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.key >= '0' && e.key <= '9') handleNumberClick(e.key);
      if (e.key === 'Backspace') handleDelete();
      if (e.key === 'Enter') handleVerify();
      if (e.key === 'Escape') onCancel();
    };
    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [pin]);

  return (
    <div className="fixed inset-0 z-[100] flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-md animate-in fade-in duration-300">
      <div className="w-full max-w-sm bg-slate-900 border border-slate-800 rounded-3xl shadow-2xl overflow-hidden animate-in zoom-in-95 duration-300">
        
        {/* Header */}
        <div className="p-6 text-center space-y-2 border-b border-white/5">
          <div className="flex justify-end -mt-2 -mr-2">
            <button onClick={onCancel} className="p-2 text-slate-500 hover:text-white transition-colors">
              <X size={20} />
            </button>
          </div>
          <div className="mx-auto w-16 h-16 bg-indigo-500/10 rounded-2xl flex items-center justify-center mb-2">
            <ShieldCheck className="text-indigo-400" size={32} />
          </div>
          <h2 className="text-xl font-bold text-white uppercase tracking-tight">{staffMember.name}</h2>
          <p className="text-xs text-slate-400 font-medium">ENTER PIN TO UNLOCK WORKSPACE</p>
        </div>

        {/* PIN Display */}
        <div className="p-8 flex flex-col items-center gap-6">
          <div className="flex gap-4">
            {[...Array(4)].map((_, i) => (
              <div 
                key={i} 
                className={`w-4 h-4 rounded-full border-2 transition-all duration-200 ${
                  pin.length > i 
                    ? 'bg-indigo-500 border-indigo-400 scale-125 shadow-[0_0_15px_rgba(99,102,241,0.5)]' 
                    : 'border-slate-700'
                }`}
              />
            ))}
          </div>

          {error && (
            <div className="flex items-center gap-2 text-rose-400 text-xs font-bold animate-bounce">
              <AlertCircle size={14} />
              {error}
            </div>
          )}

          {/* Keypad */}
          <div className="grid grid-cols-3 gap-4 w-full">
            {[1, 2, 3, 4, 5, 6, 7, 8, 9].map(num => (
              <button
                key={num}
                onClick={() => handleNumberClick(num.toString())}
                className="h-16 rounded-2xl bg-white/5 border border-white/5 text-xl font-bold text-white hover:bg-white/10 active:scale-90 transition-all"
              >
                {num}
              </button>
            ))}
            <button
              onClick={onCancel}
              className="h-16 rounded-2xl flex items-center justify-center text-slate-400 hover:text-white hover:bg-white/5 active:scale-90 transition-all font-bold text-xs"
            >
              CANCEL
            </button>
            <button
              onClick={() => handleNumberClick('0')}
              className="h-16 rounded-2xl bg-white/5 border border-white/5 text-xl font-bold text-white hover:bg-white/10 active:scale-90 transition-all"
            >
              0
            </button>
            <button
              onClick={handleDelete}
              className="h-16 rounded-2xl flex items-center justify-center text-slate-400 hover:text-rose-400 hover:bg-rose-500/10 active:scale-90 transition-all"
            >
              <Delete size={24} />
            </button>
          </div>

          <Button
            onClick={handleVerify}
            disabled={pin.length < 4 || isVerifying}
            className="w-full h-14 bg-indigo-600 hover:bg-indigo-500 text-white font-black text-sm tracking-widest rounded-2xl shadow-xl shadow-indigo-600/20 disabled:opacity-20"
          >
            {isVerifying ? (
              <Loader2 className="animate-spin mr-2" />
            ) : (
              <Lock className="mr-2" size={18} />
            )}
            UNLOCK SESSION
          </Button>
        </div>
      </div>
    </div>
  );
}
