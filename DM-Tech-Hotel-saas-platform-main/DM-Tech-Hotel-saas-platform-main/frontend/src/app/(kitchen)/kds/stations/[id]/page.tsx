'use client';

import { useParams } from 'next/navigation';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import axios from 'axios';
import { useKitchenNotifications } from '@/src/hooks/useKitchenNotifications';
import { Loader2, CheckCircle2, Utensils, Clock } from 'lucide-react';
import { formatDistanceToNow } from 'date-fns';

export default function KDSBoard() {
  const { id: stationId } = useParams();
  const queryClient = useQueryClient();
  
  // Assuming user data is available (e.g., from a context or auth hook)
  const user = { 
    id: 1, 
    token: 'static-for-demo', 
    hotel_id: 1, 
    branch_id: 1, 
    kitchen_station_id: stationId,
    role: 'kitchen-staff'
  };

  useKitchenNotifications(user);

  const { data: tickets, isLoading } = useQuery({
    queryKey: ['kds-tickets', stationId],
    queryFn: async () => {
      const res = await axios.get('/api/v1/kds/tickets');
      return res.data;
    },
    refetchInterval: 30000, // Fallback polling
  });

  const updateStatusMutation = useMutation({
    mutationFn: ({ id, status }: { id: number; status: string }) => 
      axios.put(`/api/v1/kds/tickets/${id}/status`, { status }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['kds-tickets', stationId] });
    },
  });

  if (isLoading) return <div className="flex h-screen items-center justify-center"><Loader2 className="animate-spin" /></div>;

  return (
    <div className="h-screen bg-slate-900 text-white p-6 overflow-hidden flex flex-col">
      <header className="flex justify-between items-center mb-6">
        <h1 className="text-3xl font-black tracking-tighter flex items-center gap-3">
          <Utensils className="text-primary" /> KDS STATION: {stationId}
        </h1>
        <div className="flex gap-4 items-center">
            <span className="bg-emerald-500/20 text-emerald-400 px-3 py-1 rounded-full text-sm font-bold border border-emerald-500/30">
                LIVE CONNECTED
            </span>
        </div>
      </header>

      <div className="flex-1 overflow-x-auto pb-4 custom-scrollbar">
        <div className="flex gap-6 h-full min-w-max">
          {tickets?.map((ticket: any) => (
            <div key={ticket.id} className={`w-80 flex flex-col bg-slate-800 rounded-2xl border shadow-2xl overflow-hidden animate-in fade-in slide-in-from-bottom-4 ${ticket.is_late ? 'border-rose-500 ring-2 ring-rose-500/20' : 'border-slate-700'}`}>
              <div className={`p-4 ${ticket.is_late ? 'bg-rose-600' : (ticket.priority > 0 ? 'bg-amber-500' : 'bg-slate-700')} flex justify-between items-center`}>
                <div className="flex items-center gap-2">
                    <span className="font-bold text-lg">#{ticket.ticket_number}</span>
                    {ticket.is_late && <span className="bg-white text-rose-600 px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-tighter">LATE</span>}
                </div>
                <span className="bg-black/20 px-2 py-0.5 rounded text-xs font-mono">
                  {formatDistanceToNow(new Date(ticket.created_at))}
                </span>
              </div>
              
              <div className="p-4 flex-1 flex flex-col">
                <div className="mb-4">
                    <p className="text-slate-400 text-xs uppercase font-bold mb-1">Location</p>
                    <p className="text-xl font-bold">Table {ticket.order?.table_number || 'Takeaway'}</p>
                </div>

                <div className="flex-1 space-y-3">
                   {ticket.items?.map((item: any) => (
                     <div key={item.id} className="flex justify-between items-start border-b border-slate-700/50 pb-2">
                        <div className="flex-1">
                            <p className="font-bold text-slate-100">{item.quantity}x {item.item_name}</p>
                            {item.special_instructions && (
                                <p className="text-xs text-rose-400 font-medium mt-1 italic">
                                    * {item.special_instructions}
                                </p>
                            )}
                        </div>
                        <button 
                            onClick={() => {/* Toggle availability placeholder */}}
                            className="text-[10px] text-slate-400 hover:text-rose-400 border border-slate-700 px-2 py-1 rounded"
                        >
                            86 IT
                        </button>
                     </div>
                   ))}
                </div>

                <div className="mt-6 flex gap-2">
                    {ticket.status === 'queued' ? (
                        <button 
                            onClick={() => updateStatusMutation.mutate({ id: ticket.id, status: 'preparing' })}
                            className="flex-1 bg-primary text-white py-3 rounded-xl font-bold hover:bg-primary/90 transition-colors"
                        >
                            START COOKING
                        </button>
                    ) : (
                        <button 
                            onClick={() => updateStatusMutation.mutate({ id: ticket.id, status: 'ready' })}
                            className="flex-1 bg-emerald-500 text-white py-3 rounded-xl font-bold hover:bg-emerald-600 transition-colors flex items-center justify-center gap-2"
                        >
                            <CheckCircle2 size={20} /> MARK READY
                        </button>
                    )}
                </div>
              </div>
            </div>
          ))}

          {(!tickets || tickets.length === 0) && (
            <div className="flex flex-col items-center justify-center w-full text-slate-500 opacity-50">
              <Utensils size={64} className="mb-4" />
              <p className="text-2xl font-bold">No active orders</p>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
