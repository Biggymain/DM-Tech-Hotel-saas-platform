'use client';

import React, { useEffect, useState, useCallback } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useAuth } from '@/context/AuthProvider';
import api from '@/lib/api';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  ChefHat, Clock, Loader2, Flame, CheckCircle2, Utensils, RefreshCw, Wifi,
} from 'lucide-react';
import { toast } from 'sonner';
import { formatDistanceToNow } from 'date-fns';

type OrderStatus = 'pending' | 'cooking' | 'ready' | 'served';

interface OrderItem {
  name: string;
  quantity: number;
  notes?: string;
  station?: string;
}

interface KDSOrder {
  id: number;
  order_number: string;
  table_number: string | null;
  room_id: number | null;
  order_status: OrderStatus;
  elapsed_mins: number;
  created_at: string;
  items: OrderItem[];
}

const STATUS_CONFIG = {
  pending: {
    label: 'New',
    textColor: 'text-red-400',
    borderColor: 'border-red-500/40',
    bg: 'bg-red-500/5',
    nextStatus: 'cooking' as OrderStatus,
    nextLabel: '🔥 Start Cooking',
    nextClass: 'bg-amber-500 hover:bg-amber-600 text-white',
  },
  cooking: {
    label: 'Cooking',
    textColor: 'text-amber-400',
    borderColor: 'border-amber-500/40',
    bg: 'bg-amber-500/5',
    nextStatus: 'ready' as OrderStatus,
    nextLabel: '✅ Mark Ready',
    nextClass: 'bg-emerald-500 hover:bg-emerald-600 text-white',
  },
  ready: {
    label: 'Ready',
    textColor: 'text-emerald-400',
    borderColor: 'border-emerald-500/40',
    bg: 'bg-emerald-500/5',
    nextStatus: 'served' as OrderStatus,
    nextLabel: '🍽️ Mark Served',
    nextClass: 'bg-gray-600 hover:bg-gray-700 text-white',
  },
};

const ELAPSED_COLOR = (m: number) =>
  m >= 15 ? 'text-red-400' : m >= 8 ? 'text-amber-400' : 'text-emerald-400';

declare global {
  interface Window { Echo: any; }
}

export default function KDSPage() {
  const { user } = useAuth();
  const qc = useQueryClient();
  const [wsStatus, setWsStatus] = useState<'connecting' | 'live' | 'fallback'>('connecting');
  const [stationFilter, setStationFilter] = useState<string>('all');
  const [notifyAudio] = useState(() =>
    typeof window !== 'undefined' ? new Audio('/sounds/new-order.mp3') : null
  );

  // ── Polling fallback query (every 8s) ──────────────────────────────────────
  const { data: orders = [], isLoading } = useQuery<KDSOrder[]>({
    queryKey: ['kds-orders', user?.outlet_id, stationFilter],
    queryFn: async () => {
      const params = new URLSearchParams();
      if (user?.outlet_id) params.set('outlet_id', String(user.outlet_id));
      if (stationFilter !== 'all') params.set('station', stationFilter);
      const { data } = await api.get(`/api/v1/orders/kds?${params}`);
      return data.data ?? [];
    },
    refetchInterval: 8000,
  });

  // ── Laravel Echo WebSocket subscription ───────────────────────────────────
  useEffect(() => {
    if (typeof window === 'undefined' || !window.Echo || !user?.hotel_id) return;

    const hotelId  = user.hotel_id;
    
    // Listen to the collective KDS channel (New Requirement)
    const kdsChannel = window.Echo.private(`hotel.${hotelId}.kds`)
      .listen('.new-order', (e: any) => {
        toast.info(`🔔 New order — Table ${e.order.table_number ?? e.order.room_id}`);
        notifyAudio?.play().catch(() => {});
        qc.invalidateQueries({ queryKey: ['kds-orders'] });
      })
      .subscribed(() => setWsStatus('live'))
      .error(() => setWsStatus('fallback'));

    // Existing Station-specific legacy listeners (optional, but keeping for compatibility)
    const stations = stationFilter === 'all'
      ? ['main', 'grill', 'bar', 'pastry', 'cold-kitchen', 'fry']
      : [stationFilter];

    const channels: any[] = [kdsChannel];

    stations.forEach((station) => {
      const ch = window.Echo.private(`hotel.${hotelId}.station.${station}`)
        .listen('.order.fired', (e: any) => {
          // If we already get it from .kds channel, we might deduplicate or just let it refresh
          qc.invalidateQueries({ queryKey: ['kds-orders'] });
        })
        .listen('.order.status.updated', (_e: any) => {
          qc.invalidateQueries({ queryKey: ['kds-orders'] });
        });

      channels.push(ch);
    });

    return () => {
      kdsChannel.stopListening('.new-order');
      channels.forEach((ch) => ch.stopListening('.order.fired').stopListening('.order.status.updated'));
    };
  }, [user?.hotel_id, stationFilter, qc, notifyAudio]);

  // ── Status update mutation ─────────────────────────────────────────────────
  const updateStatus = useMutation({
    mutationFn: ({ id, status }: { id: number; status: OrderStatus }) =>
      api.put(`/api/v1/orders/${id}/status`, { status }),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['kds-orders'] }),
    onError: () => toast.error('Failed to update order status.'),
  });

  const grouped = {
    pending: orders.filter((o) => o.order_status === 'pending'),
    cooking: orders.filter((o) => o.order_status === 'cooking'),
    ready:   orders.filter((o) => o.order_status === 'ready'),
  };

  const stations = ['all', 'main', 'grill', 'bar', 'pastry', 'cold-kitchen'];

  return (
    <div className="min-h-screen bg-[#0a0a0f] text-white">
      {/* ── Header ─── */}
      <div className="sticky top-0 z-30 flex items-center justify-between px-6 py-4 border-b border-white/10 bg-[#0a0a0f]/95 backdrop-blur-sm">
        <div className="flex items-center gap-3">
          <div className="bg-gradient-to-br from-orange-500 to-red-600 p-2 rounded-xl">
            <ChefHat className="h-5 w-5 text-white" />
          </div>
          <div>
            <h1 className="text-lg font-bold">Kitchen Display System</h1>
            <p className="text-xs text-white/40">Outlet #{user?.outlet_id ?? 'All'} · {user?.name}</p>
          </div>
        </div>
        <div className="flex items-center gap-3">
          <div className={`flex items-center gap-1.5 text-xs px-2.5 py-1 rounded-full ${
            wsStatus === 'live' ? 'bg-emerald-500/15 text-emerald-400'
            : wsStatus === 'fallback' ? 'bg-amber-500/15 text-amber-400'
            : 'bg-white/5 text-white/30'
          }`}>
            {wsStatus === 'live' ? <Wifi className="h-3 w-3" /> : <RefreshCw className="h-3 w-3" />}
            {wsStatus === 'live' ? 'Live' : wsStatus === 'fallback' ? 'Polling' : 'Connecting…'}
          </div>
          {isLoading && <Loader2 className="h-4 w-4 animate-spin text-white/30" />}
        </div>
      </div>

      {/* ── Station filter pills ─── */}
      <div className="flex gap-2 px-4 py-3 border-b border-white/5 overflow-x-auto scrollbar-hide">
        {stations.map((s) => (
          <button
            key={s}
            onClick={() => setStationFilter(s)}
            className={`px-3 py-1.5 rounded-full text-xs font-semibold whitespace-nowrap transition-all ${
              stationFilter === s
                ? 'bg-orange-500 text-white'
                : 'bg-white/5 text-white/40 hover:bg-white/10'
            }`}
          >
            {s === 'all' ? '🍽️ All Stations' : s.charAt(0).toUpperCase() + s.slice(1)}
          </button>
        ))}
      </div>

      {/* ── Summary strip ─── */}
      <div className="grid grid-cols-3 border-b border-white/5 bg-white/[0.015]">
        {[
          { label: 'New',     count: grouped.pending.length, color: 'text-red-400' },
          { label: 'Cooking', count: grouped.cooking.length, color: 'text-amber-400' },
          { label: 'Ready',   count: grouped.ready.length,   color: 'text-emerald-400' },
        ].map(({ label, count, color }) => (
          <div key={label} className="text-center py-3 border-r border-white/5 last:border-r-0">
            <div className={`text-2xl font-extrabold tabular-nums ${color}`}>{count}</div>
            <div className="text-[10px] uppercase tracking-wider text-white/25">{label}</div>
          </div>
        ))}
      </div>

      {/* ── Order Columns ─── */}
      <div className="grid md:grid-cols-3 gap-4 p-4 items-start">
        {(['pending', 'cooking', 'ready'] as const).map((status) => {
          const conf = STATUS_CONFIG[status];
          const cols  = grouped[status];
          return (
            <div key={status}>
              <h2 className={`font-bold text-xs uppercase tracking-widest mb-3 ${conf.textColor}`}>
                {conf.label} ({cols.length})
              </h2>
              <div className="space-y-3">
                {cols.length === 0 && (
                  <div className="rounded-2xl border border-white/5 py-8 text-center text-white/20 text-sm">
                    No {conf.label.toLowerCase()} orders
                  </div>
                )}
                {cols.map((order) => (
                  <div
                    key={order.id}
                    className={`rounded-2xl border p-4 space-y-3 transition-all ${conf.borderColor} ${conf.bg}`}
                  >
                    {/* Order meta */}
                    <div className="flex items-center justify-between">
                      <div className="flex items-center gap-2">
                        <span className="font-extrabold text-white">#{order.order_number.replace('ORD-', '')}</span>
                        {order.table_number && (
                          <span className="text-xs text-white/60 bg-white/10 px-2 py-0.5 rounded-full">
                            🪑 {order.table_number}
                          </span>
                        )}
                        {order.room_id && (
                          <span className="text-xs text-white/60 bg-white/10 px-2 py-0.5 rounded-full">
                            🏨 Rm {order.room_id}
                          </span>
                        )}
                      </div>
                      <span className={`text-xs font-mono font-bold ${ELAPSED_COLOR(order.elapsed_mins)}`}>
                        {order.elapsed_mins}m
                      </span>
                    </div>

                    <div className="text-[10px] text-white/30 -mt-1">
                      {new Date(order.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                    </div>

                    {/* Items */}
                    <div className="space-y-1.5">
                      {order.items.map((item, i) => (
                        <div key={i} className="flex items-start gap-2">
                          <span className="min-w-[1.5rem] text-center text-sm font-black bg-white/10 rounded px-1.5 text-white">
                            {item.quantity}
                          </span>
                          <div>
                            <p className="text-sm font-semibold text-white leading-snug">{item.name}</p>
                            {item.notes && <p className="text-xs text-amber-400 italic">{item.notes}</p>}
                            {item.station && item.station !== 'main' && (
                              <span className="text-[10px] text-orange-400 font-medium bg-orange-500/10 px-1.5 rounded">
                                {item.station}
                              </span>
                            )}
                          </div>
                        </div>
                      ))}
                    </div>

                    {/* Action button */}
                    {status !== 'ready' || orders.some((o) => o.id === order.id && o.order_status !== 'served') ? (
                      <button
                        disabled={updateStatus.isPending}
                        onClick={() => updateStatus.mutate({ id: order.id, status: conf.nextStatus })}
                        className={`w-full py-2.5 rounded-xl text-sm font-bold transition-all ${conf.nextClass} disabled:opacity-50`}
                      >
                        {updateStatus.isPending ? <Loader2 className="h-4 w-4 animate-spin mx-auto" /> : conf.nextLabel}
                      </button>
                    ) : null}
                  </div>
                ))}
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}
