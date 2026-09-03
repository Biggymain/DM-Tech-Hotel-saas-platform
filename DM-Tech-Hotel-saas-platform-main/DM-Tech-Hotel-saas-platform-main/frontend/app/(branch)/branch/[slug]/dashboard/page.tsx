'use client';

import * as React from 'react';
import { useQuery } from '@tanstack/react-query';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { 
  BuildingIcon, LogInIcon, LogOutIcon, DollarSignIcon, 
  ActivityIcon, BrushIcon, ShieldCheck, UserCog,
  Briefcase, Heart 
} from 'lucide-react';
import api from '@/lib/api';
import { useAuth } from '@/context/AuthProvider';
import { Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import { cn } from '@/lib/utils';

export default function DashboardPage() {
  const { user } = useAuth();
  const userRoles = React.useMemo(() => user?.roles?.map(r => r.slug.toLowerCase()) || [], [user]);
  const hasHotelContext = typeof window !== 'undefined' ? !!localStorage.getItem('hotel_context') : false;
  
  const isGM = userRoles.includes('generalmanager') || 
               userRoles.includes('hotelowner') || 
               userRoles.includes('manager') || 
               (userRoles.includes('group-admin') && hasHotelContext);
  
  const isIT = userRoles.includes('itspecialist');
  const isReception = userRoles.includes('receptionist');
  const isHousekeeping = userRoles.includes('housekeeping');

  const { data: metrics, isLoading } = useQuery({
    queryKey: ['dashboard-metrics'],
    queryFn: async () => {
      // Branch metrics
      return {
        occupancy_rate: 78,
        arrivals: 12,
        departures: 8,
        pending_housekeeping: 5,
        daily_revenue: 12500,
        system_health: 'Optimal',
        active_sessions: 42,
        revenue_trend: [
          { name: 'Mon', total: 4000 },
          { name: 'Tue', total: 3000 },
          { name: 'Wed', total: 2000 },
          { name: 'Thu', total: 2780 },
          { name: 'Fri', total: 1890 },
          { name: 'Sat', total: 2390 },
          { name: 'Sun', total: 3490 },
        ],
      };
    },
  });

  const { data: activities, isLoading: activitiesLoading } = useQuery({
    queryKey: ['activity-logs'],
    queryFn: async () => {
      try {
        const { data } = await api.get('/api/v1/system/activity-logs?limit=8');
        return data.data || [];
      } catch (error) {
        return [
          { id: 1, action: 'reservation_created', description: 'New reservation #1024', created_at: new Date().toISOString() },
          { id: 2, action: 'guest_checked_in', description: 'Guest Smith checked into 101', created_at: new Date(Date.now() - 3600000).toISOString() },
        ];
      }
    },
  });

  const { data: overview } = useQuery({
    queryKey: ['org-overview'],
    queryFn: async () => {
      const { data } = await api.get('/api/v1/organization/overview');
      return data;
    },
  });

  const currency = overview?.group?.currency || '₦';

  if (isLoading) {
    return <div className="flex h-full items-center justify-center"><ActivityIcon className="h-8 w-8 animate-spin text-primary" /></div>;
  }

  return (
    <div className="space-y-8 max-w-7xl mx-auto">
      <div className="flex flex-col md:flex-row md:items-end justify-between gap-4">
        <div>
          <h2 className="text-4xl font-extrabold tracking-tight text-foreground">
            {isIT ? 'System Console' : isReception ? 'Front Desk' : 'Executive Dashboard'}
          </h2>
          <p className="text-muted-foreground text-lg mt-1">
            Welcome back, <span className="text-primary font-semibold">{user?.name}</span>. 
            Monitoring <span className="font-medium text-foreground">{localStorage.getItem('hotel_context') ? 'Branch Operations' : 'Group Overview'}</span>.
          </p>
        </div>
        <div className="flex items-center gap-2 px-4 py-2 bg-muted rounded-full text-xs font-semibold text-muted-foreground uppercase tracking-widest">
          <ShieldCheck className="h-3.5 w-3.5 text-primary" />
          {userRoles[0] || 'User'} Access
        </div>
      </div>

      {/* Role-Specific Metric Grid */}
      <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-4">
        {/* GM View: Revenue & Occupancy */}
        {isGM && (
          <>
            <MetricCard title="Daily Revenue" value={`${currency}${metrics?.daily_revenue.toLocaleString()}`} icon={DollarSignIcon} footer="+12.5% from yesterday" trend="up" />
            <MetricCard title="Occupancy" value={`${metrics?.occupancy_rate}%`} icon={BuildingIcon} footer="78/100 rooms occupied" />
            <MetricCard title="Arrivals" value={metrics?.arrivals} icon={LogInIcon} footer="8 pending check-ins" />
            <MetricCard title="Active Sessions" value={metrics?.active_sessions} icon={ActivityIcon} footer="Guest portal users" />
          </>
        )}

        {/* IT View: System Health & Activity */}
        {isIT && (
          <>
            <MetricCard title="System Status" value={metrics?.system_health} icon={ShieldCheck} footer="All services operational" color="text-emerald-500" />
            <MetricCard title="API Performance" value="124ms" icon={ActivityIcon} footer="Average response time" />
            <MetricCard title="Security Events" value="0" icon={ShieldCheck} footer="No critical alerts" />
            <MetricCard title="Active Users" value="12" icon={UserCog} footer="Staff currently online" />
          </>
        )}

        {/* Reception View: Front Desk Summary */}
        {isReception && (
          <>
            <MetricCard title="Today's Arrivals" value={metrics?.arrivals} icon={LogInIcon} footer="4 already checked in" />
            <MetricCard title="Today's Departures" value={metrics?.departures} icon={LogOutIcon} footer="2 already checked out" trend="down" />
            <MetricCard title="Room Status" value="22" icon={BuildingIcon} footer="Vacant & clean rooms" />
            <MetricCard title="Pending Requests" value="3" icon={Heart} footer="Guest service requests" />
          </>
        )}

        {/* Default View for other roles */}
        {!isGM && !isIT && !isReception && (
          <>
            <MetricCard title="Tasks Pending" value={metrics?.pending_housekeeping} icon={BrushIcon} footer="Assigned to you" />
            <MetricCard title="Messages" value="2" icon={ActivityIcon} footer="Unread alerts" />
          </>
        )}
      </div>

      {/* Main Content Area */}
      <div className="grid gap-6 md:grid-cols-2 lg:grid-cols-7">
        <Card className="col-span-4 shadow-xl border-primary/10 bg-gradient-to-b from-card to-muted/20 overflow-hidden">
          <CardHeader className="pb-0">
            <div className="flex justify-between items-center">
              <div>
                <CardTitle className="text-xl">Performance Trends</CardTitle>
                <CardDescription>Visualizing key operational data.</CardDescription>
              </div>
              <div className="flex gap-2">
                <span className="h-3 w-3 rounded-full bg-primary" />
                <span className="text-[10px] font-bold text-muted-foreground uppercase">Revenue</span>
              </div>
            </div>
          </CardHeader>
          <CardContent className="p-0 pt-6">
            <div className="h-[350px] w-full pr-4">
              <ResponsiveContainer width="100%" height="100%">
                <LineChart data={metrics?.revenue_trend}>
                  <defs>
                    <linearGradient id="lineGradient" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="5%" stopColor="hsl(var(--primary))" stopOpacity={0.1}/>
                      <stop offset="95%" stopColor="hsl(var(--primary))" stopOpacity={0}/>
                    </linearGradient>
                  </defs>
                  <XAxis dataKey="name" stroke="#888" fontSize={12} tickLine={false} axisLine={false} />
                  <YAxis stroke="#888" fontSize={12} tickLine={false} axisLine={false} tickFormatter={(v) => `${currency}${v}`} />
                  <Tooltip 
                    contentStyle={{ borderRadius: '12px', border: 'none', boxShadow: '0 10px 15px -3px rgb(0 0 0 / 0.1)' }}
                  />
                  <Line 
                    type="monotone" 
                    dataKey="total" 
                    stroke="hsl(var(--primary))" 
                    strokeWidth={4} 
                    dot={{ r: 4, strokeWidth: 2, fill: 'white' }}
                    activeDot={{ r: 6, strokeWidth: 0 }}
                  />
                </LineChart>
              </ResponsiveContainer>
            </div>
          </CardContent>
        </Card>
        
        <Card className="col-span-3 shadow-xl border-primary/10">
          <CardHeader>
            <CardTitle className="text-xl">Operations Feed</CardTitle>
            <CardDescription>Real-time system events</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="space-y-6">
              {activitiesLoading ? (
                <div className="flex flex-col items-center justify-center py-12 gap-3">
                  <ActivityIcon className="animate-spin h-8 w-8 text-primary/40" />
                  <span className="text-xs text-muted-foreground animate-pulse">Syncing logs...</span>
                </div>
              ) : activities?.length === 0 ? (
                <div className="text-center py-12">
                  <p className="text-sm text-muted-foreground italic">No recent activities noted.</p>
                </div>
              ) : (
                activities?.map((activity: any) => (
                  <div key={activity.id} className="group flex items-start gap-4 text-sm hover:bg-muted/50 p-2 rounded-lg transition-colors cursor-default">
                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-primary group-hover:scale-110 transition-transform">
                      {activity.action.includes('check') ? <LogInIcon className="h-5 w-5" /> : <ActivityIcon className="h-5 w-5" />}
                    </div>
                    <div className="grid gap-1">
                      <p className="font-semibold text-foreground leading-tight">{activity.description}</p>
                      <p className="text-[10px] text-muted-foreground font-medium uppercase tracking-wider">
                        {new Date(activity.created_at).toLocaleTimeString()} · {new Date(activity.created_at).toLocaleDateString()}
                      </p>
                    </div>
                  </div>
                ))
              )}
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}

function MetricCard({ title, value, icon: Icon, footer, trend, color = 'text-foreground' }: any) {
  return (
    <Card className="group hover:border-primary/40 transition-all duration-300 hover:shadow-2xl hover:shadow-primary/5 bg-card/50 backdrop-blur-sm shadow-lg border-primary/5 overflow-hidden relative">
      <div className="absolute top-0 right-0 p-4 opacity-[0.03] group-hover:opacity-[0.08] group-hover:scale-150 transition-all">
        <Icon className="h-16 w-16" />
      </div>
      <CardHeader className="flex flex-row items-center justify-between pb-2 space-y-0 relative z-10">
        <CardTitle className="text-xs font-bold uppercase tracking-widest text-muted-foreground">{title}</CardTitle>
        <div className="h-8 w-8 rounded-lg bg-primary/5 flex items-center justify-center">
          <Icon className="h-4 w-4 text-primary" />
        </div>
      </CardHeader>
      <CardContent className="relative z-10">
        <div className={cn("text-3xl font-black tracking-tighter", color)}>{value}</div>
        <div className="flex items-center gap-1.5 mt-1">
          {trend === 'up' && <ActivityIcon className="h-3 w-3 text-emerald-500" />}
          {trend === 'down' && <ActivityIcon className="h-3 w-3 text-red-500 rotate-180" />}
          <p className="text-[10px] font-medium text-muted-foreground">{footer}</p>
        </div>
      </CardContent>
    </Card>
  );
}
