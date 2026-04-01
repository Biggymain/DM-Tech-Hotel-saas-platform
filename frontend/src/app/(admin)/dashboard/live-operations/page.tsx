"use client";

import { useQuery } from "@tanstack/react-query";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Badge } from "@/components/ui/badge";
import { AlertCircle, Clock, Utensils, AlertTriangle } from "lucide-react";
import { useTicketTimer } from "@/src/hooks/useTicketTimer";
import axios from "axios";
import { useEffect, useState } from "react";
import { initEcho } from "@/src/lib/echo";

interface OutletSLA {
    id: number;
    name: string;
    active_tickets: number;
    late_tickets: number;
    avg_prep_time: number;
    restock_requests: number;
}

export default function LiveOperationsPage() {
    const { data: outlets, refetch } = useQuery<OutletSLA[]>({
        queryKey: ["live-outlets"],
        queryFn: async () => {
            const res = await axios.get("/api/v1/sla/branch-overview");
            return res.data.outlets;
        },
        refetchInterval: 30000,
    });

    useEffect(() => {
        const port = typeof window !== 'undefined' ? window.location.port : '3000';
        const token = localStorage.getItem(`auth_token_${port}`);
        if (!token) return;

        const echo = initEcho(token);
        const channel = echo.private(`hotel.branch.sla`);
        channel.listen("SlaThresholdExceeded", () => refetch());
        channel.listen("KitchenTicketStatusUpdated", () => refetch());
        
        return () => echo.disconnect();
    }, [refetch]);

    return (
        <div className="p-6 space-y-6">
            <header className="flex justify-between items-center bg-white p-4 rounded-xl shadow-sm border border-slate-100">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900">Live Outlet Command Center</h1>
                    <p className="text-slate-500">Real-time SLA monitoring and operational oversight.</p>
                </div>
                <div className="flex gap-4">
                    <Badge variant="outline" className="px-3 py-1 bg-green-50 text-green-700 border-green-200">
                        System Online
                    </Badge>
                </div>
            </header>

            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                {outlets?.map((outlet) => (
                    <OutletCard key={outlet.id} outlet={outlet} />
                ))}
            </div>
        </div>
    );
}

function OutletCard({ outlet }: { outlet: OutletSLA }) {
    const statusColor = outlet.late_tickets > 0 ? "border-red-500 bg-red-50/30" : "border-slate-100 bg-white";
    
    return (
        <Card className={`transition-all duration-300 shadow-md border-2 ${statusColor}`}>
            <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0">
                <CardTitle className="text-lg font-bold flex items-center gap-2">
                    <Utensils className="h-5 w-5 text-indigo-500" />
                    {outlet.name}
                </CardTitle>
                {outlet.late_tickets > 0 && (
                    <Badge variant="destructive" className="animate-pulse">
                        {outlet.late_tickets} LATE
                    </Badge>
                )}
            </CardHeader>
            <CardContent>
                <div className="space-y-4">
                    <div className="flex justify-between items-end">
                        <div className="space-y-1">
                            <span className="text-sm text-slate-500 font-medium uppercase tracking-wider">Active Tickets</span>
                            <div className="text-3xl font-black text-slate-900">{outlet.active_tickets}</div>
                        </div>
                        <div className="text-right space-y-1">
                             <span className="text-xs text-slate-400 font-medium flex items-center justify-end gap-1">
                                <Clock className="h-3 w-3" /> AVG PREP
                             </span>
                             <div className="text-xl font-bold text-slate-700">{outlet.avg_prep_time}m</div>
                        </div>
                    </div>

                    <div className="pt-4 border-t border-slate-100 space-y-2">
                        {outlet.restock_requests > 0 && (
                            <div className="flex items-center gap-2 text-amber-600 bg-amber-50 p-2 rounded-lg text-sm font-semibold">
                                <AlertTriangle className="h-4 w-4" />
                                {outlet.restock_requests} Pending Restocks
                            </div>
                        )}
                        <button 
                            className="w-full py-2 bg-slate-900 text-white rounded-lg text-sm font-bold hover:bg-slate-800 transition-colors"
                            onClick={() => window.location.href = `/admin/dashboard/kitchen-stations?id=${outlet.id}`}
                        >
                            Manage Station
                        </button>
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}
