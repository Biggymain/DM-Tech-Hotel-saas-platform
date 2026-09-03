'use client';

import { useState, useEffect } from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { 
  BarChart, 
  Bar, 
  XAxis, 
  YAxis, 
  CartesianGrid, 
  Tooltip, 
  ResponsiveContainer,
  PieChart,
  Pie,
  Cell
} from 'recharts';
import { 
  Users, 
  TrendingUp, 
  AlertTriangle, 
  Activity,
  Building2,
  Calendar,
  DollarSign
} from 'lucide-react';
import axios from 'axios';
import { toast } from 'sonner';

export default function PlatformDashboard() {
  const [data, setData] = useState<any>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetchAnalytics();
  }, []);

  const fetchAnalytics = async () => {
    try {
      const apiBase = process.env.NEXT_PUBLIC_API_URL || '/api';
      const response = await axios.get(`${apiBase}/v1/admin/platform/analytics`);
      setData(response.data);
    } catch (error) {
      toast.error('Failed to load platform analytics');
    } finally {
      setLoading(false);
    }
  };

  if (loading) return <div className="p-8">Loading Platform Metrics...</div>;

  const stats = data?.stats;
  const hotelList = data?.hotels || [];

  const chartData = [
    { name: 'Active', value: stats.active_subscriptions, color: '#10b981' },
    { name: 'Expired', value: stats.expired_accounts, color: '#ef4444' },
    { name: 'Trials', value: hotelList.filter((h: any) => h.status === 'trial').length, color: '#3b82f6' }
  ];

  return (
    <div className="p-8 space-y-8 animate-in fade-in duration-700">
      <div className="flex flex-col gap-2">
        <h1 className="text-3xl font-bold tracking-tight">Platform Subscription Analytics</h1>
        <p className="text-muted-foreground">Monitor the health and revenue of the DM Tech Hotel SaaS ecosystem.</p>
      </div>

      {/* Metric Cards */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <Card className="border-l-4 border-l-primary shadow-sm">
          <CardHeader className="pb-2">
            <CardDescription className="text-xs uppercase font-bold tracking-wider">Total Hotels</CardDescription>
            <CardTitle className="text-3xl flex items-center justify-between">
              {stats.total_hotels}
              <Building2 className="w-6 h-6 text-muted-foreground opacity-50" />
            </CardTitle>
          </CardHeader>
        </Card>
        <Card className="border-l-4 border-l-green-500 shadow-sm">
          <CardHeader className="pb-2">
            <CardDescription className="text-xs uppercase font-bold tracking-wider">Monthly Recurring Revenue (MRR)</CardDescription>
            <CardTitle className="text-3xl flex items-center justify-between">
              ${stats.mrr}
              <DollarSign className="w-6 h-6 text-green-500/50" />
            </CardTitle>
          </CardHeader>
        </Card>
        <Card className="border-l-4 border-l-blue-500 shadow-sm">
          <CardHeader className="pb-2">
            <CardDescription className="text-xs uppercase font-bold tracking-wider">Active Subscriptions</CardDescription>
            <CardTitle className="text-3xl flex items-center justify-between">
              {stats.active_subscriptions}
              <Activity className="w-6 h-6 text-blue-500/50" />
            </CardTitle>
          </CardHeader>
        </Card>
        <Card className="border-l-4 border-l-red-500 shadow-sm">
          <CardHeader className="pb-2">
            <CardDescription className="text-xs uppercase font-bold tracking-wider">Expired Accounts</CardDescription>
            <CardTitle className="text-3xl flex items-center justify-between">
              {stats.expired_accounts}
              <TrendingUp className="w-6 h-6 text-red-500/50 rotate-180" />
            </CardTitle>
          </CardHeader>
        </Card>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {/* Distribution Chart */}
        <Card className="lg:col-span-1 shadow-md">
          <CardHeader>
            <CardTitle>Subscription Status Distribution</CardTitle>
          </CardHeader>
          <CardContent className="h-[300px]">
            <ResponsiveContainer width="100%" height="100%">
              <PieChart>
                <Pie
                  data={chartData}
                  cx="50%"
                  cy="50%"
                  innerRadius={60}
                  outerRadius={80}
                  paddingAngle={5}
                  dataKey="value"
                >
                  {chartData.map((entry, index) => (
                    <Cell key={`cell-${index}`} fill={entry.color} />
                  ))}
                </Pie>
                <Tooltip />
              </PieChart>
            </ResponsiveContainer>
            <div className="flex justify-center gap-4 text-xs mt-4">
               {chartData.map(c => (
                 <div key={c.name} className="flex items-center gap-1">
                   <div className="w-3 h-3 rounded-full" style={{ backgroundColor: c.color }} />
                   <span>{c.name}</span>
                 </div>
               ))}
            </div>
          </CardContent>
        </Card>

        {/* Recent Hotels Table */}
        <Card className="lg:col-span-2 shadow-md overflow-hidden">
          <CardHeader>
            <CardTitle>Tenants Overview</CardTitle>
            <CardDescription>Real-time status of all hotels on the platform.</CardDescription>
          </CardHeader>
          <CardContent className="p-0">
             <div className="overflow-x-auto">
                <table className="w-full text-left text-sm">
                  <thead className="bg-muted/50 border-y">
                    <tr>
                      <th className="px-6 py-3 font-semibold">Hotel Name</th>
                      <th className="px-6 py-3 font-semibold">Plan</th>
                      <th className="px-6 py-3 font-semibold">Status</th>
                      <th className="px-6 py-3 font-semibold">Next Renewal</th>
                    </tr>
                  </thead>
                  <tbody className="divide-y">
                    {hotelList.map((hotel: any) => (
                      <tr key={hotel.id} className="hover:bg-muted/30 transition-colors">
                        <td className="px-6 py-4 font-medium">{hotel.name}</td>
                        <td className="px-6 py-4">
                          <Badge variant="outline">{hotel.plan}</Badge>
                        </td>
                        <td className="px-6 py-4">
                           <Badge 
                            variant={hotel.status === 'active' || hotel.status === 'trial' ? 'default' : 'destructive'}
                            className="text-[10px] uppercase font-bold"
                           >
                            {hotel.status}
                           </Badge>
                        </td>
                        <td className="px-6 py-4 text-muted-foreground">{hotel.expiry}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
             </div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
