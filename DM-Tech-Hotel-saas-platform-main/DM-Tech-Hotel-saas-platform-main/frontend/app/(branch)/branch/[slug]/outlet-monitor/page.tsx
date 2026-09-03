'use client';

import * as React from 'react';
import { useQuery } from '@tanstack/react-query';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Clock, Utensils, AlertTriangle, ExternalLink } from 'lucide-react';
import { useParams, useRouter } from 'next/navigation';
import api from '@/lib/api';
import { useKitchenNotifications } from '@/src/hooks/useKitchenNotifications';
import { Button } from '@/components/ui/button';

export default function OutletMonitorPage() {
  const params = useParams();
  const branchSlug = params.slug;
  const router = useRouter();

  const { data: outletsData, refetch } = useQuery({
    queryKey: ['branch-overview', branchSlug],
    queryFn: async () => {
      const { data } = await api.get('/api/v1/sla/branch-overview');
      return data.outlets || [];
    },
    refetchInterval: 30000,
  });

  // Real-time integration
  useKitchenNotifications((event: any) => {
    console.log('Operational event received:', event);
    refetch();
  });

  return (
    <div className="space-y-6">
      <header className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b pb-6">
        <div>
          <h2 className="text-3xl font-bold tracking-tight">Outlet Monitor</h2>
          <p className="text-muted-foreground">Real-time SLA status and operational heartbeat.</p>
        </div>
        <div className="flex items-center gap-2">
            <div className="flex h-2 w-2 rounded-full bg-emerald-500 animate-pulse" />
            <span className="text-xs font-medium text-emerald-600">Live Connection Active</span>
        </div>
      </header>

      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        {outletsData?.map((outlet: any) => (
          <OutletSLACard key={outlet.id} outlet={outlet} branchSlug={branchSlug as string} />
        ))}
      </div>
    </div>
  );
}

function OutletSLACard({ outlet, branchSlug }: { outlet: any, branchSlug: string }) {
  const isLate = outlet.late_tickets > 0;
  
  return (
    <Card className={`transition-all duration-300 shadow-sm border-2 ${isLate ? 'border-red-200 bg-red-50/10' : 'border-border'}`}>
      <CardHeader className="flex flex-row items-center justify-between pb-2">
        <CardTitle className="text-lg font-bold flex items-center gap-2">
          <Utensils className="h-4 w-4 text-primary" />
          {outlet.name}
        </CardTitle>
        {isLate && (
             <Badge variant="destructive" className="animate-pulse">
                {outlet.late_tickets} LATE
             </Badge>
        )}
      </CardHeader>
      <CardContent className="space-y-4">
        <div className="flex justify-between items-end">
            <div className="space-y-1">
                <span className="text-[10px] text-muted-foreground font-bold uppercase tracking-wider">Active Tickets</span>
                <div className="text-3xl font-black">{outlet.active_tickets}</div>
            </div>
            <div className="text-right space-y-1">
                 <span className="text-[10px] text-muted-foreground font-bold flex items-center justify-end gap-1">
                    <Clock className="h-3 w-3" /> AVG PREP
                 </span>
                 <div className="text-xl font-bold text-primary">{outlet.avg_prep_time}m</div>
            </div>
        </div>

        <div className="pt-4 border-t border-border/50">
            {outlet.restock_requests > 0 && (
                <div className="flex items-center gap-2 text-amber-600 bg-amber-50 p-2 rounded-lg text-xs font-semibold mb-3">
                    <AlertTriangle className="h-3 w-3" />
                    {outlet.restock_requests} Restock Alerts
                </div>
            )}
            
            <Button 
                variant="secondary" 
                className="w-full h-9 flex items-center gap-2 text-xs"
                onClick={() => window.open(`/kds/stations/${outlet.id}`, '_blank')}
            >
                Open Terminal
                <ExternalLink className="h-3 w-3" />
            </Button>
        </div>
      </CardContent>
    </Card>
  );
}
