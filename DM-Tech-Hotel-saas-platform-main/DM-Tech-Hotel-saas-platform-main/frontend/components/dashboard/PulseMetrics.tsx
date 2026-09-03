'use client';

import React from 'react';
import { 
  Timer, 
  TrendingDown, 
  TrendingUp, 
  Zap, 
  Activity,
  History,
  Clock
} from 'lucide-react';
import { Card, CardHeader, CardTitle, CardContent } from '@/components/ui/card';

interface PulseMetricsProps {
  avgLeadTime: number; // In minutes
  previousAvg: number;
  totalOrders: number;
  activeOrders: number;
}

export default function PulseMetrics({ 
  avgLeadTime, 
  previousAvg, 
  totalOrders, 
  activeOrders 
}: PulseMetricsProps) {
  const diff = avgLeadTime - previousAvg;
  const isImproving = diff <= 0;

  return (
    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
      
      {/* Velocity Card */}
      <Card className="bg-slate-900 border-indigo-500/20 shadow-2xl shadow-indigo-500/10 overflow-hidden relative group">
        <div className="absolute top-0 right-0 p-4 opacity-10 group-hover:opacity-20 transition-opacity">
          <Zap size={64} className="text-indigo-400" />
        </div>
        <CardHeader className="pb-2">
          <CardTitle className="text-[10px] font-black text-indigo-400 tracking-[0.2em] uppercase flex items-center gap-2">
            <Activity size={14} />
            ORDER VELOCITY
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div className="flex items-baseline gap-2">
             <span className="text-4xl font-black text-white tracking-tighter">{avgLeadTime}</span>
             <span className="text-slate-500 font-bold text-xs uppercase">MINS</span>
          </div>
          
          <div className="mt-4 flex items-center gap-2">
            <div className={`flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold ${
              isImproving ? 'bg-emerald-500/10 text-emerald-500' : 'bg-rose-500/10 text-rose-500'
            }`}>
              {isImproving ? <TrendingDown size={12} /> : <TrendingUp size={12} />}
              {Math.abs(diff).toFixed(1)}m from last shift
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Pending Throughput */}
      <Card className="bg-slate-900 border-slate-800 shadow-xl overflow-hidden relative group">
        <CardHeader className="pb-2">
          <CardTitle className="text-[10px] font-black text-amber-500 tracking-[0.2em] uppercase flex items-center gap-2">
            <Clock size={14} />
            PENDING LOAD
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div className="flex items-baseline gap-2">
             <span className="text-4xl font-black text-white tracking-tighter">{activeOrders}</span>
             <span className="text-slate-500 font-bold text-xs uppercase">TICKETS</span>
          </div>
          <p className="mt-4 text-[10px] font-bold text-slate-600 tracking-wider">ACROSS ALL OUTLETS</p>
        </CardContent>
      </Card>

      {/* Total Volume */}
      <Card className="bg-slate-900 border-slate-800 shadow-xl overflow-hidden relative group">
        <CardHeader className="pb-2">
          <CardTitle className="text-[10px] font-black text-slate-500 tracking-[0.2em] uppercase flex items-center gap-2">
            <History size={14} />
            TOTAL VOLUME
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div className="flex items-baseline gap-2">
             <span className="text-4xl font-black text-white tracking-tighter">{totalOrders}</span>
             <span className="text-slate-500 font-bold text-xs uppercase">SERVED</span>
          </div>
          <p className="mt-4 text-[10px] font-bold text-slate-600 tracking-wider">FOR CURRENT OPERATIONAL CYCLE</p>
        </CardContent>
      </Card>

      {/* Efficiency Score */}
      <Card className="bg-slate-900 border-emerald-500/20 shadow-xl overflow-hidden relative group">
        <CardHeader className="pb-2">
          <CardTitle className="text-[10px] font-black text-emerald-500 tracking-[0.2em] uppercase flex items-center gap-2">
            <Timer size={14} />
            KITCHEN EFFICIENCY
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div className="flex items-baseline gap-2">
             <span className="text-4xl font-black text-white tracking-tighter">
               {Math.max(0, 100 - (avgLeadTime * 2)).toFixed(0)}%
             </span>
          </div>
          <div className="mt-4 h-1.5 w-full bg-slate-800 rounded-full overflow-hidden">
             <div 
               className="h-full bg-emerald-500 transition-all duration-1000" 
               style={{ width: `${Math.max(0, 100 - (avgLeadTime * 2))}%` }} 
             />
          </div>
        </CardContent>
      </Card>

    </div>
  );
}
