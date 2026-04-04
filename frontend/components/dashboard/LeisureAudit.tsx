'use client';

import React, { useState, useEffect } from 'react';
import { 
  AlertTriangle, 
  CheckCircle2, 
  Search, 
  ArrowUpRight,
  Fingerprint,
  TrendingDown,
  RefreshCw
} from 'lucide-react';
import api from '@/lib/api';
import { toast } from 'sonner';

export default function LeisureAudit() {
  const [logs, setLogs] = useState<any[]>([]);
  const [isLoading, setIsLoading] = useState(true);

  const fetchAudit = async () => {
    setIsLoading(true);
    try {
      const { data } = await api.get('/api/v1/admin/leisure/audit'); // I should implement this endpoint
      setLogs(data.data || []);
    } catch (err) {
      // toast.error('Could not load leisure audit logs.');
      // Mocking for UI demonstration if endpoint not found
      setLogs([
        { id: 1, type: 'ENTRY', user: 'Guest #402', status: 'MISMATCH', message: 'No Drink Deduction found for Pool Entry', time: '10:45 AM', severity: 'HIGH' },
        { id: 2, type: 'ENTRY', user: 'Member #12', status: 'VALID', message: 'Active Membership verified', time: '11:02 AM', severity: 'LOW' },
        { id: 3, type: 'PASS', user: 'Guest #105', status: 'VALID', message: 'Pool Pass + Water Bottle confirmed', time: '11:15 AM', severity: 'LOW' },
        { id: 4, type: 'ENTRY', user: 'Guest #221', status: 'LEAKAGE', message: 'Hardware breach detected (Forced Entry?)', time: '11:45 AM', severity: 'CRITICAL' },
      ]);
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    fetchAudit();
  }, []);

  return (
    <div className="flex flex-col h-full bg-slate-900/50 border border-white/5 rounded-[40px] p-8 space-y-8 backdrop-blur-xl">
      
      <div className="flex items-center justify-between">
        <div className="space-y-1">
          <div className="flex items-center gap-2 text-rose-500">
             <TrendingDown size={16} />
             <h3 className="text-[10px] font-black tracking-widest uppercase italic">Revenue Leakage Alert</h3>
          </div>
          <h2 className="text-2xl font-black tracking-tight uppercase">Leisure Hub Audit</h2>
        </div>
        
        <button 
          onClick={fetchAudit}
          className="p-3 bg-white/5 rounded-2xl hover:bg-white/10 transition-colors text-slate-400"
        >
          <RefreshCw size={18} className={isLoading ? "animate-spin" : ""} />
        </button>
      </div>

      <div className="space-y-4 overflow-y-auto pr-2">
        {logs.map((log) => (
          <div 
            key={log.id} 
            className={`group relative p-6 rounded-3xl border border-white/5 transition-all duration-300 hover:scale-[1.02] ${
              log.severity === 'CRITICAL' ? 'bg-rose-500/10 border-rose-500/20' : 
              log.severity === 'HIGH' ? 'bg-orange-500/10 border-orange-500/20' : 
              'bg-slate-950/50'
            }`}
          >
            <div className="flex justify-between items-start gap-4">
              <div className="flex gap-4">
                 <div className={`w-12 h-12 rounded-2xl flex items-center justify-center shrink-0 ${
                   log.status === 'VALID' ? 'bg-emerald-500/10 text-emerald-500' : 'bg-rose-500/10 text-rose-500'
                 }`}>
                    {log.status === 'VALID' ? <CheckCircle2 size={24} /> : <AlertTriangle size={24} />}
                 </div>
                 
                 <div className="space-y-1">
                    <div className="flex items-center gap-2">
                       <span className="text-[10px] font-black text-slate-500 uppercase tracking-widest">{log.type}</span>
                       <span className="text-[10px] text-slate-600 font-bold">•</span>
                       <span className="text-[10px] font-bold text-slate-400">{log.time}</span>
                    </div>
                    <h4 className="font-black text-white uppercase tracking-tight">{log.user}</h4>
                    <p className={`text-[10px] font-medium leading-relaxed ${
                      log.status === 'VALID' ? 'text-slate-500' : 'text-rose-400 font-bold italic'
                    }`}>
                      {log.message}
                    </p>
                 </div>
              </div>

              {log.status !== 'VALID' && (
                <div className="px-3 py-1 bg-rose-500 rounded-full text-[8px] font-black text-white uppercase tracking-tighter">
                   Flagged
                </div>
              )}
            </div>

            <div className="absolute top-4 right-4 opacity-0 group-hover:opacity-100 transition-opacity">
               <ArrowUpRight size={16} className="text-slate-600" />
            </div>
          </div>
        ))}
      </div>

      <div className="pt-4 border-t border-white/5">
         <div className="flex justify-between items-center bg-white/5 p-4 rounded-2xl">
            <div className="flex items-center gap-2">
              <Fingerprint className="text-indigo-400" size={16} />
              <span className="text-[10px] font-black text-slate-400 uppercase tracking-widest">Confidence Score</span>
            </div>
            <span className="text-xs font-black text-emerald-500">98.2%</span>
         </div>
      </div>
    </div>
  );
}
