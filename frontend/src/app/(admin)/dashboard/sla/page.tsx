'use client';

import { useQuery } from '@tanstack/react-query';
import axios from 'axios';
import { useTicketTimer } from '@/src/hooks/useTicketTimer';
import { useKitchenNotifications } from '@/src/hooks/useKitchenNotifications';
import { ShieldAlert, BarChart3, Clock, Map as MapIcon } from 'lucide-react';
import { toast } from 'sonner';

function SLACard({ ticket }: { ticket: any }) {
    const { elapsed, statusColor } = useTicketTimer(ticket.created_at);

    return (
        <div className="bg-card p-4 rounded-xl border border-border shadow-sm flex items-center justify-between">
            <div className="flex items-center gap-4">
                <div className={`w-2 h-12 rounded-full ${statusColor.replace('text', 'bg')}`} />
                <div>
                    <h4 className="font-bold text-lg">#{ticket.ticket_number}</h4>
                    <p className="text-sm text-muted-foreground">{ticket.kitchen_station?.name} • Table {ticket.order?.table_number}</p>
                </div>
            </div>
            <div className="text-right">
                <p className={`text-2xl font-black ${statusColor}`}>{elapsed}m</p>
                <p className="text-[10px] uppercase font-bold text-muted-foreground">Elapsed Time</p>
            </div>
        </div>
    );
}

export default function SLADashboard() {
    const { data: activeTickets, isLoading } = useQuery({
        queryKey: ['active-sla-tickets'],
        queryFn: async () => {
            const res = await axios.get('/api/v1/kds/sla/active');
            return res.data;
        },
        refetchInterval: 10000,
    });

    const { data: report } = useQuery({
        queryKey: ['sla-report'],
        queryFn: async () => {
            const res = await axios.get('/api/v1/kds/sla/report');
            return res.data;
        },
    });

    // Real-time escalation listener
    // Note: In a real app, this would be scoped to the manager's branch admin channel
    useKitchenNotifications({ id: 'manager', role: 'branch-manager', token: '...' });

    return (
        <div className="p-6 space-y-8 max-w-7xl mx-auto">
            <header className="flex justify-between items-center">
                <div>
                    <h1 className="text-3xl font-black tracking-tight flex items-center gap-2">
                        <ShieldAlert className="text-primary" /> SLA COMMAND CENTER
                    </h1>
                    <p className="text-muted-foreground">Live operational oversight across all stations</p>
                </div>
                <div className="flex gap-2">
                    <button className="bg-accent px-4 py-2 rounded-lg text-sm font-bold flex items-center gap-2">
                        <BarChart3 size={16} /> Export Report
                    </button>
                </div>
            </header>

            <div className="grid md:grid-cols-3 gap-6">
                <div className="md:col-span-2 space-y-4">
                    <div className="flex items-center gap-2 mb-2">
                        <MapIcon className="text-muted-foreground" size={18} />
                        <h2 className="font-bold text-muted-foreground uppercase text-xs tracking-widest">Live Heat Map</h2>
                    </div>
                    <div className="grid gap-3">
                        {activeTickets?.map((ticket: any) => (
                            <SLACard key={ticket.id} ticket={ticket} />
                        ))}
                        {activeTickets?.length === 0 && (
                            <div className="p-12 border-2 border-dashed rounded-3xl text-center text-muted-foreground">
                                All stations performing within SLA limits
                            </div>
                        )}
                    </div>
                </div>

                <div className="space-y-6">
                    <div className="bg-slate-900 text-white p-6 rounded-3xl shadow-xl">
                        <h3 className="font-bold mb-4 flex items-center gap-2 uppercase text-xs tracking-widest opacity-60">
                            <Clock size={16} /> 24H Success Rate
                        </h3>
                        <div className="space-y-4">
                            {report?.map((stat: any) => (
                                <div key={stat.station_name}>
                                    <div className="flex justify-between text-sm mb-1">
                                        <span>{stat.station_name}</span>
                                        <span className="font-bold">{stat.success_rate}%</span>
                                    </div>
                                    <div className="w-full bg-white/10 h-2 rounded-full overflow-hidden">
                                        <div 
                                            className={`h-full transition-all duration-500 ${stat.success_rate > 90 ? 'bg-emerald-400' : 'bg-amber-400'}`}
                                            style={{ width: `${stat.success_rate}%` }}
                                        />
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}
