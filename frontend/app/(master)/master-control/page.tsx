'use client';

import React from 'react';
import { ConnectivityWidget } from '@/src/components/master/ConnectivityWidget';
import { 
  Shield, 
  AlertTriangle, 
  Users, 
  Server, 
  Terminal,
  Activity,
  Lock
} from 'lucide-react';
import { useAxios } from '@/src/hooks/useAxios';

export default function MasterDashboardPage() {
  // SIEM Alerts Feed (Simulated via useAxios hitting /api/v1/siem/alerts)
  const { data: alerts, loading: alertsLoading } = useAxios({
    url: '/api/v1/siem/alerts',
    method: 'GET'
  }, false);

  const stats = [
    { label: 'Active Terminals', value: '42', icon: Terminal, color: 'text-blue-400' },
    { label: 'Security Score', value: '98%', icon: Shield, color: 'text-emerald-400' },
    { label: 'Pending Alerts', value: alerts?.total || '0', icon: AlertTriangle, color: 'text-amber-400' },
    { label: 'System Health', value: 'Nominal', icon: Activity, color: 'text-indigo-400' },
  ];

  return (
    <div className="p-6 space-y-6 bg-slate-950 min-h-screen text-slate-200">
      {/* Header */}
      <div className="flex items-center justify-between mb-8">
        <div>
          <h1 className="text-3xl font-black tracking-tighter text-white uppercase flex items-center gap-3">
            <Server className="text-indigo-500" />
            Master Control Room
          </h1>
          <p className="text-slate-400 mt-1">System-wide monitoring & hardware security enforcement.</p>
        </div>
        <div className="flex gap-3">
           <div className="px-4 py-2 rounded-xl bg-white/5 border border-white/10 flex items-center gap-2">
             <div className="w-2 h-2 rounded-full bg-emerald-500 animate-pulse" />
             <span className="text-xs font-bold uppercase tracking-widest text-emerald-500">Live Feed</span>
           </div>
        </div>
      </div>

      {/* Top Stats Grid */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        {stats.map((stat, i) => (
          <div key={i} className="p-6 rounded-2xl bg-white/5 border border-white/10 backdrop-blur-xl">
            <div className="flex items-center gap-4">
              <div className={`p-3 rounded-xl bg-white/5 ${stat.color}`}>
                <stat.icon size={24} />
              </div>
              <div>
                <p className="text-xs font-bold text-slate-500 uppercase tracking-widest">{stat.label}</p>
                <p className="text-2xl font-black text-white">{stat.value}</p>
              </div>
            </div>
          </div>
        ))}
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Heartbeat Widget */}
        <div className="lg:col-span-1">
          <ConnectivityWidget />
          
          <div className="mt-6 p-6 rounded-2xl bg-white/5 border border-white/10 space-y-4">
             <h3 className="text-sm font-bold text-white uppercase tracking-widest flex items-center gap-2">
               <Lock size={16} className="text-indigo-400" />
               Security Protocols
             </h3>
             <ul className="space-y-3">
                {[
                  'Argon2id Hardware Marriage',
                  'Cross-Port Isolation',
                  'SIEM Real-time Watchdog',
                  'Tenant Global Scoping'
                ].map((item, i) => (
                  <li key={i} className="flex items-center gap-2 text-xs text-slate-400">
                    <div className="w-1 h-1 rounded-full bg-indigo-500" />
                    {item}
                  </li>
                ))}
             </ul>
          </div>
        </div>

        {/* SIEM Alerts Feed */}
        <div className="lg:col-span-2 space-y-6">
          <div className="rounded-2xl border border-white/10 bg-white/5 overflow-hidden">
            <div className="p-6 border-b border-white/10 flex items-center justify-between">
               <h3 className="text-lg font-bold text-white">SIEM Priority Alerts</h3>
               <button className="text-xs font-bold text-indigo-400 uppercase">View All</button>
            </div>
            <div className="overflow-x-auto">
              <table className="w-full text-left">
                <thead className="bg-white/5 text-[10px] uppercase font-bold text-slate-500">
                  <tr>
                    <th className="px-6 py-4">Event</th>
                    <th className="px-6 py-4">Severity</th>
                    <th className="px-6 py-4">Source</th>
                    <th className="px-6 py-4">Timestamp</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-white/10">
                  {alerts?.data?.map((alert: any, i: number) => (
                    <tr key={i} className="hover:bg-white/5 transition-colors">
                      <td className="px-6 py-4">
                        <p className="text-sm font-medium text-white">{alert.message || 'System Event'}</p>
                        <p className="text-[10px] text-slate-500 font-mono">{alert.id}</p>
                      </td>
                      <td className="px-6 py-4">
                        <span className={`px-2 py-1 rounded text-[10px] font-bold uppercase ${
                          alert.severity >= 10 ? 'bg-red-500/20 text-red-500' : 'bg-amber-500/20 text-amber-500'
                        }`}>
                          Lv. {alert.severity || 1}
                        </span>
                      </td>
                      <td className="px-6 py-4 text-xs text-slate-400">{alert.source || 'Kernel'}</td>
                      <td className="px-6 py-4 text-xs text-slate-500">{alert.created_at || 'Just now'}</td>
                    </tr>
                  )) || (
                    <tr>
                      <td colSpan={4} className="px-6 py-12 text-center text-slate-500 italic">
                        No critical alerts detected in the last 24 hours.
                      </td>
                    </tr>
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
