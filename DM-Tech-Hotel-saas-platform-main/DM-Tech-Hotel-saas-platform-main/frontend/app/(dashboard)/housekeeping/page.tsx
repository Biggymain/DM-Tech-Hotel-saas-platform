'use client';

import React, { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useAuth } from '@/context/AuthProvider';
import api from '@/lib/api';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { BedDouble, Sparkles, AlertTriangle, CheckCircle2, Clock, Loader2, Search } from 'lucide-react';
import { toast } from 'sonner';

type HKStatus = 'clean' | 'dirty' | 'in_progress' | 'inspected' | 'do_not_disturb';

interface HKRoom {
  id: number;
  room_number: string;
  room_type: { name: string };
  housekeeping_status: HKStatus;
  status: string; // occupancy: available | occupied | maintenance
  guest_name?: string;
  checkout_today?: boolean;
  priority?: boolean;
}

const HK_CONFIG: Record<HKStatus, { label: string; icon: React.ReactNode; badgeClass: string; bg: string }> = {
  clean:         { label: 'Clean',         icon: <CheckCircle2 className="h-4 w-4" />,   badgeClass: 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30', bg: 'border-emerald-500/20' },
  dirty:         { label: 'Dirty',         icon: <AlertTriangle className="h-4 w-4" />,  badgeClass: 'bg-red-500/15 text-red-400 border-red-500/30',           bg: 'border-red-500/20' },
  in_progress:   { label: 'In Progress',   icon: <Sparkles className="h-4 w-4" />,       badgeClass: 'bg-blue-500/15 text-blue-400 border-blue-500/30',         bg: 'border-blue-500/20' },
  inspected:     { label: 'Inspected',     icon: <CheckCircle2 className="h-4 w-4" />,   badgeClass: 'bg-violet-500/15 text-violet-400 border-violet-500/30',   bg: 'border-violet-500/20' },
  do_not_disturb:{ label: 'DND',           icon: <Clock className="h-4 w-4" />,          badgeClass: 'bg-amber-500/15 text-amber-400 border-amber-500/30',      bg: 'border-amber-500/20' },
};

const NEXT_STATUS: Partial<Record<HKStatus, HKStatus>> = {
  dirty:       'in_progress',
  in_progress: 'inspected',
  inspected:   'clean',
};

export default function HousekeepingPage() {
  const { user } = useAuth();
  const qc = useQueryClient();
  const [search, setSearch] = useState('');
  const [filterStatus, setFilterStatus] = useState<HKStatus | 'all'>('all');

  const { data: rooms = [], isLoading } = useQuery<HKRoom[]>({
    queryKey: ['hk-rooms'],
    queryFn: async () => {
      const { data } = await api.get('/api/v1/pms/rooms?include=room_type,reservations');
      return data.data ?? [];
    },
    refetchInterval: 30_000,
  });

  const updateStatus = useMutation({
    mutationFn: ({ id, status }: { id: number; status: HKStatus }) =>
      api.patch(`/api/v1/pms/rooms/${id}/housekeeping-status`, { housekeeping_status: status }),
    onSuccess: (_, vars) => {
      toast.success(`Room updated to ${HK_CONFIG[vars.status].label}`);
      qc.invalidateQueries({ queryKey: ['hk-rooms'] });
    },
    onError: () => toast.error('Failed to update room status.'),
  });

  const filtered = rooms.filter((r) => {
    const matchSearch = !search || r.room_number.toLowerCase().includes(search.toLowerCase());
    const matchFilter = filterStatus === 'all' || r.housekeeping_status === filterStatus;
    return matchSearch && matchFilter;
  });

  const stats = {
    clean:       rooms.filter((r) => r.housekeeping_status === 'clean').length,
    dirty:       rooms.filter((r) => r.housekeeping_status === 'dirty').length,
    in_progress: rooms.filter((r) => r.housekeeping_status === 'in_progress').length,
  };

  return (
    <div className="min-h-screen bg-background">
      {/* Header */}
      <div className="sticky top-0 z-30 bg-card border-b px-4 py-3 shadow-sm">
        <div className="flex items-center justify-between mb-3">
          <div className="flex items-center gap-2">
            <div className="bg-gradient-to-br from-teal-500 to-emerald-600 p-2 rounded-xl">
              <BedDouble className="h-4 w-4 text-white" />
            </div>
            <div>
              <h1 className="font-bold leading-none">Housekeeping</h1>
              <p className="text-xs text-muted-foreground">{user?.name}</p>
            </div>
          </div>
          <div className="flex gap-4 text-center">
            {[
              { label: 'Dirty',    count: stats.dirty,       color: 'text-red-500' },
              { label: 'Active',   count: stats.in_progress, color: 'text-blue-500' },
              { label: 'Clean',    count: stats.clean,       color: 'text-emerald-500' },
            ].map(({ label, count, color }) => (
              <div key={label}>
                <div className={`text-lg font-extrabold ${color}`}>{count}</div>
                <div className="text-[10px] uppercase text-muted-foreground tracking-wider">{label}</div>
              </div>
            ))}
          </div>
        </div>

        {/* Search + filter */}
        <div className="flex gap-2">
          <div className="relative flex-1">
            <Search className="absolute left-2.5 top-2.5 h-3.5 w-3.5 text-muted-foreground" />
            <input
              className="w-full pl-8 pr-3 py-2 text-sm rounded-lg border bg-background"
              placeholder="Search room…"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
            />
          </div>
          <select
            className="px-3 py-2 text-sm rounded-lg border bg-background text-foreground"
            value={filterStatus}
            onChange={(e) => setFilterStatus(e.target.value as HKStatus | 'all')}
          >
            <option value="all">All Rooms</option>
            {Object.entries(HK_CONFIG).map(([key, c]) => (
              <option key={key} value={key}>{c.label}</option>
            ))}
          </select>
        </div>
      </div>

      {/* Room Cards */}
      {isLoading ? (
        <div className="flex justify-center py-16"><Loader2 className="animate-spin h-8 w-8 text-muted-foreground" /></div>
      ) : (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 p-4">
          {filtered.map((room) => {
            const hkConf = HK_CONFIG[room.housekeeping_status];
            const nextStat = NEXT_STATUS[room.housekeeping_status];
            return (
              <div
                key={room.id}
                className={`rounded-2xl border p-4 space-y-3 transition-all ${hkConf.bg} bg-card hover:shadow-md`}
              >
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <span className="text-lg font-extrabold">{room.room_number}</span>
                    {room.checkout_today && (
                      <span className="text-[10px] font-bold uppercase tracking-wider text-amber-400 bg-amber-400/10 px-1.5 py-0.5 rounded-full">
                        Checkout Today
                      </span>
                    )}
                    {room.priority && (
                      <span className="text-[10px] font-bold uppercase text-red-400 bg-red-400/10 px-1.5 py-0.5 rounded-full">
                        Priority
                      </span>
                    )}
                  </div>
                  <Badge className={`text-[10px] font-bold border ${hkConf.badgeClass}`}>
                    <span className="mr-1">{hkConf.icon}</span>
                    {hkConf.label}
                  </Badge>
                </div>

                <div className="text-xs text-muted-foreground space-y-0.5">
                  <p>{room.room_type?.name}</p>
                  {room.guest_name && <p>👤 {room.guest_name}</p>}
                  <p>Occupancy: <span className={room.status === 'occupied' ? 'text-red-400' : 'text-emerald-400'}>
                    {room.status.charAt(0).toUpperCase() + room.status.slice(1)}
                  </span></p>
                </div>

                {nextStat && (
                  <Button
                    size="sm"
                    variant={nextStat === 'in_progress' ? 'default' : 'outline'}
                    className="w-full h-9 text-xs font-bold"
                    disabled={updateStatus.isPending}
                    onClick={() => updateStatus.mutate({ id: room.id, status: nextStat })}
                  >
                    {updateStatus.isPending ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : (
                      nextStat === 'in_progress' ? '🧹 Start Cleaning'
                      : nextStat === 'inspected' ? '🔍 Mark Inspected'
                      : '✅ Mark Clean'
                    )}
                  </Button>
                )}
              </div>
            );
          })}
        </div>
      )}
    </div>
  );
}
