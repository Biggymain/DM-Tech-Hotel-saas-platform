'use client';

import React, { useState, useEffect } from 'react';
import { useAxios } from '@/src/hooks/useAxios';
import { ShieldCheck, ShieldAlert, Activity, RefreshCw } from 'lucide-react';

export const ConnectivityWidget = () => {
  const { data, loading, error, execute } = useAxios({
    url: '/api/v1/developer/status',
    method: 'GET',
  }, true);

  const [status, setStatus] = useState<'pending' | 'secure' | 'breach'>('pending');

  const checkStatus = async () => {
    try {
      await execute();
      setStatus('secure');
    } catch (err: any) {
      if (err.response?.status === 403) {
        setStatus('breach');
      } else {
        // Handle other errors (network, etc.) as breach for safety in Master context
        setStatus('breach');
      }
    }
  };

  useEffect(() => {
    checkStatus();
    const interval = setInterval(checkStatus, 30000); // Heartbeat every 30s
    return () => clearInterval(interval);
  }, []);

  return (
    <div className="rounded-2xl border border-white/10 bg-white/5 p-6 backdrop-blur-xl">
      <div className="flex items-center justify-between mb-6">
        <div className="flex items-center gap-3">
          <div className={`p-2 rounded-lg ${status === 'secure' ? 'bg-emerald-500/20 text-emerald-400' : 'bg-red-500/20 text-red-400'}`}>
            <Activity size={20} />
          </div>
          <h3 className="text-lg font-bold text-white tracking-tight">Master Heartbeat</h3>
        </div>
        <button 
          onClick={checkStatus}
          disabled={loading}
          className="p-2 hover:bg-white/10 rounded-lg transition-colors text-slate-400"
        >
          <RefreshCw size={18} className={loading ? 'animate-spin' : ''} />
        </button>
      </div>

      <div className={`flex flex-col items-center justify-center p-8 rounded-xl border-2 border-dashed transition-all duration-500 ${
        status === 'secure' 
          ? 'bg-emerald-500/5 border-emerald-500/20 text-emerald-400' 
          : 'bg-red-500/5 border-red-500/20 text-red-400'
      }`}>
        {status === 'secure' ? (
          <>
            <ShieldCheck size={48} className="mb-4" />
            <div className="text-center">
              <p className="text-xl font-black tracking-tighter uppercase mb-1">TERMINAL SECURE</p>
              <p className="text-sm font-medium opacity-80">MASTER IDENTITY VERIFIED</p>
            </div>
          </>
        ) : (
          <>
            <ShieldAlert size={48} className="mb-4" />
            <div className="text-center">
              <p className="text-xl font-black tracking-tighter uppercase mb-1">SECURITY BREACH</p>
              <p className="text-sm font-medium opacity-80">UNAUTHORIZED HARDWARE</p>
            </div>
          </>
        )}
      </div>

      <div className="mt-6 grid grid-cols-2 gap-4">
        <div className="p-3 rounded-xl bg-white/5 border border-white/10">
          <p className="text-[10px] text-slate-500 uppercase font-bold mb-1">Hardware ID</p>
          <p className="text-xs font-mono text-white truncate">
            {typeof window !== 'undefined' ? localStorage.getItem('hardware_id')?.substring(0, 16) + '...' : 'CAPTURE_PENDING'}
          </p>
        </div>
        <div className="p-3 rounded-xl bg-white/5 border border-white/10">
          <p className="text-[10px] text-slate-500 uppercase font-bold mb-1">Access Level</p>
          <p className="text-xs font-bold text-indigo-400 uppercase">System Master</p>
        </div>
      </div>
    </div>
  );
};
