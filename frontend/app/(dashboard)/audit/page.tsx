'use client';

import React from 'react';
import { useQuery } from '@tanstack/react-query';
import api from '@/lib/api';
import PulseMetrics from '@/components/dashboard/PulseMetrics';
import StockMatching from '@/components/dashboard/StockMatching';
import { Loader2, ShieldCheck, RefreshCcw } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';

export default function AuditPage() {
  // 1. Fetch GM Pulse Velocity Metrics
  const { data: velocity, isLoading: loadingPulse, refetch: refetchPulse } = useQuery({
    queryKey: ['audit-velocity'],
    queryFn: async () => {
      const { data } = await api.get('/api/v1/orders/orders/velocity');
      return data;
    },
    refetchInterval: 30000,
  });

  // 2. Fetch Stock Transfers for Audit
  const { data: transfers = [], isLoading: loadingTransfers } = useQuery({
    queryKey: ['audit-transfers'],
    queryFn: async () => {
      const { data } = await api.get('/api/v1/transfers');
      return data.data || [];
    },
  });

  const isLoading = loadingPulse || loadingTransfers;

  if (isLoading && !velocity) {
    return (
      <div className="h-[80vh] flex flex-col items-center justify-center gap-4">
        <Loader2 className="animate-spin text-indigo-500" size={48} />
        <p className="font-black tracking-widest text-xs uppercase text-slate-500 animate-pulse">Synchronizing Audit Engine...</p>
      </div>
    );
  }

  return (
    <div className="p-6 lg:p-12 space-y-12 bg-slate-950 min-h-screen text-white rounded-[40px] border border-white/5 animate-in fade-in duration-700">
      
      {/* Header Section */}
      <div className="flex flex-col md:flex-row md:items-end justify-between gap-8">
        <div className="space-y-4">
          <div className="flex items-center gap-3">
             <div className="w-12 h-12 bg-indigo-600 rounded-2xl flex items-center justify-center shadow-2xl shadow-indigo-600/30">
                <ShieldCheck className="text-white" size={24} />
             </div>
             <div className="px-3 py-1 bg-white/5 border border-white/10 rounded-full">
                <span className="text-[10px] font-black text-indigo-400 uppercase tracking-widest tracking-tighter">SECURE AUDIT NODE</span>
             </div>
          </div>
          <h1 className="text-5xl font-black tracking-tighter uppercase italic">Executive Pulse</h1>
          <p className="text-slate-500 font-bold uppercase tracking-widest text-xs max-w-md leading-relaxed">
            Real-time operational velocity and inventory integrity tracking.
          </p>
        </div>

        <div className="flex items-center gap-4 pb-2">
           <Button 
            variant="outline" 
            onClick={() => refetchPulse()}
            className="rounded-2xl border-white/10 bg-white/5 text-white hover:bg-white/10 font-bold gap-2 h-12 px-6"
           >
             <RefreshCcw size={18} />
             Live Sync
           </Button>
        </div>
      </div>

      {/* GM Velocity Section */}
      <section className="space-y-6">
         <PulseMetrics 
            avgLeadTime={velocity?.avg_lead_time || 0}
            previousAvg={velocity?.previous_avg || 0}
            totalOrders={velocity?.total_served || 0}
            activeOrders={velocity?.active_orders || 0}
         />
      </section>

      {/* Detailed Audit Tabs */}
      <section className="pt-12 border-t border-white/5">
        <Tabs defaultValue="transfers" className="space-y-8">
          <div className="flex items-center justify-between">
            <TabsList className="bg-white/5 border border-white/10 p-1 rounded-2xl h-14">
              <TabsTrigger value="transfers" className="rounded-xl px-6 font-black text-[11px] uppercase tracking-widest data-[state=active]:bg-indigo-600 data-[state=active]:text-white transition-all">
                Inventory Audit
              </TabsTrigger>
              <TabsTrigger value="orders" className="rounded-xl px-6 font-black text-[11px] uppercase tracking-widest data-[state=active]:bg-indigo-600 data-[state=active]:text-white transition-all">
                Velocity Log
              </TabsTrigger>
            </TabsList>
            
            <p className="hidden md:block text-[10px] text-slate-600 font-black tracking-widest uppercase italic">
              Authorized Personnel Only beyond this point
            </p>
          </div>

          <TabsContent value="transfers" className="animate-in fade-in slide-in-from-bottom-4 duration-500">
             <StockMatching transfers={transfers} />
          </TabsContent>

          <TabsContent value="orders" className="animate-in fade-in slide-in-from-bottom-4 duration-500">
             <div className="bg-slate-900/50 border border-slate-800 rounded-[32px] p-24 text-center">
                <p className="text-slate-500 font-bold uppercase tracking-widest text-xs italic">
                  Live order velocity log is being synchronized...
                </p>
             </div>
          </TabsContent>
        </Tabs>
      </section>

    </div>
  );
}
