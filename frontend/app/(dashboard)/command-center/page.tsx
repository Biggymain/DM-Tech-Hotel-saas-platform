'use client';

import * as React from 'react';
import { motion } from 'framer-motion';
import { 
  BarChart3, 
  Users, 
  Utensils, 
  Wind, 
  Wrench, 
  TrendingUp, 
  Clock,
  LayoutDashboard,
  Bell,
  MoreVertical,
  RefreshCcw,
  CheckCircle2,
  AlertCircle
} from 'lucide-react';
import { 
  Card, 
  CardContent, 
  CardHeader, 
  CardTitle, 
  CardDescription 
} from '@/components/ui/card';
import { Button } from '@/components/ui/button';
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
import { useQuery } from '@tanstack/react-query';
import axios from 'axios';
import { toast } from 'sonner';

// API Client for Admin (Assume token handled by middleware/interceptor)
const adminApi = axios.create({
  baseURL: process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000',
  headers: {
    'Accept': 'application/json',
    'Authorization': `Bearer ${typeof window !== 'undefined' ? localStorage.getItem('token') : ''}`
  }
});

const REFRESH_INTERVAL = 30000; // 30 seconds

export default function CommandCenter() {
  // 1. Occupancy Data
  const { data: occupancy, isLoading: loadingOcc } = useQuery({
    queryKey: ['admin-occupancy'],
    queryFn: () => adminApi.get('/api/v1/admin/dashboard/occupancy').then(res => res.data),
    refetchInterval: REFRESH_INTERVAL,
  });

  // 2. Revenue Data
  const { data: revenue, isLoading: loadingRev } = useQuery({
    queryKey: ['admin-revenue'],
    queryFn: () => adminApi.get('/api/v1/admin/dashboard/revenue').then(res => res.data),
    refetchInterval: REFRESH_INTERVAL,
  });

  // 3. Live Orders
  const { data: liveOrders, isLoading: loadingOrders } = useQuery({
    queryKey: ['admin-live-orders'],
    queryFn: () => adminApi.get('/api/v1/admin/orders/live').then(res => res.data),
    refetchInterval: REFRESH_INTERVAL,
  });

  // 4. Service Requests
  const { data: serviceRequests, isLoading: loadingReqs } = useQuery({
    queryKey: ['admin-service-requests'],
    queryFn: () => adminApi.get('/api/v1/admin/service-requests').then(res => res.data),
    refetchInterval: REFRESH_INTERVAL,
  });

  const pieData = occupancy ? [
    { name: 'Occupied', value: occupancy.occupied_rooms, color: '#6366f1' },
    { name: 'Available', value: occupancy.available_rooms, color: '#10b981' },
    { name: 'Maintenance', value: occupancy.maintenance_rooms, color: '#f59e0b' },
  ] : [];

  const revData = revenue ? [
    { name: 'Total', amount: revenue.today_revenue },
    { name: 'Outlet', amount: revenue.restaurant_revenue },
    { name: 'Room', amount: revenue.room_revenue },
    { name: 'Pending', amount: revenue.pending_payments },
  ] : [];

  return (
    <div className="p-6 lg:p-10 space-y-8 bg-slate-50 min-h-screen">
      <header className="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h1 className="text-3xl font-bold tracking-tight text-slate-900 flex items-center">
            <LayoutDashboard className="mr-3 text-indigo-600" size={32} />
            Hotel Command Center
          </h1>
          <p className="text-slate-500 mt-1">Real-time operational visibility & guest activity.</p>
        </div>
        <div className="flex items-center space-x-3">
          <Badge variant="outline" className="bg-white border-slate-200 text-slate-600 px-3 py-1">
            <Clock size={14} className="mr-2" />
            Last Updated: {new Date().toLocaleTimeString()}
          </Badge>
          <Button variant="outline" size="icon" className="bg-white border-slate-200">
            <RefreshCcw size={18} className="text-slate-600" />
          </Button>
          <Button className="bg-indigo-600 hover:bg-indigo-700 text-white">
            <Bell size={18} className="mr-2" />
            Alerts
          </Button>
        </div>
      </header>

      {/* Top Stats Row */}
      <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
        <StatCard title="Total Revenue" value={`$${revenue?.today_revenue || 0}`} change="+12.5%" icon={TrendingUp} color="indigo" />
        <StatCard title="Active Occupancy" value={`${occupancy?.occupied_rooms || 0}/${occupancy?.total_rooms || 0}`} change="68%" icon={Users} color="emerald" />
        <StatCard title="Live Orders" value={liveOrders?.length || 0} change="Last 2hrs" icon={Utensils} color="orange" />
        <StatCard title="Pending Requests" value={serviceRequests?.data?.length || 0} change="High Priority" icon={Wind} color="pink" />
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        {/* Occupancy Analysis */}
        <Card className="lg:col-span-1 shadow-sm border-slate-200">
          <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
            <div>
              <CardTitle className="text-lg font-bold">Occupancy Overview</CardTitle>
              <CardDescription>Live room status breakdown</CardDescription>
            </div>
            <MoreVertical size={18} className="text-slate-400" />
          </CardHeader>
          <CardContent>
            <div className="h-[280px] w-full">
              <ResponsiveContainer width="100%" height="100%">
                <PieChart>
                  <Pie
                    data={pieData}
                    cx="50%"
                    cy="50%"
                    innerRadius={60}
                    outerRadius={80}
                    paddingAngle={5}
                    dataKey="value"
                  >
                    {pieData.map((entry, index) => (
                      <Cell key={`cell-${index}`} fill={entry.color} />
                    ))}
                  </Pie>
                  <Tooltip />
                </PieChart>
              </ResponsiveContainer>
            </div>
            <div className="mt-4 grid grid-cols-3 gap-2 text-center text-sm">
              {pieData.map((d) => (
                <div key={d.name}>
                  <p className="font-bold text-slate-900">{d.value}</p>
                  <p className="text-xs text-slate-500">{d.name}</p>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>

        {/* Revenue Performance */}
        <Card className="lg:col-span-2 shadow-sm border-slate-200">
          <CardHeader>
            <CardTitle className="text-lg font-bold">Revenue Snapshot</CardTitle>
            <CardDescription>Daily financial performance by department</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="h-[300px] w-full">
              <ResponsiveContainer width="100%" height="100%">
                <BarChart data={revData}>
                  <CartesianGrid strokeDasharray="3 3" vertical={false} />
                  <XAxis dataKey="name" axisLine={false} tickLine={false} />
                  <YAxis axisLine={false} tickLine={false} tickFormatter={(v) => `$${v}`} />
                  <Tooltip />
                  <Bar dataKey="amount" fill="#6366f1" radius={[4, 4, 0, 0]} barSize={40} />
                </BarChart>
              </ResponsiveContainer>
            </div>
          </CardContent>
        </Card>
      </div>

      <div className="grid grid-cols-1 xl:grid-cols-2 gap-8">
        {/* Active Guest Orders */}
        <Card className="shadow-sm border-slate-200">
          <CardHeader className="flex flex-row items-center justify-between">
            <div>
              <CardTitle className="text-lg font-bold">Active Guest Orders</CardTitle>
              <CardDescription>Real-time feed from Room Service & Outlets</CardDescription>
            </div>
            <Button variant="ghost" className="text-indigo-600 font-medium">View All</Button>
          </CardHeader>
          <CardContent>
            <div className="space-y-4 max-h-[400px] overflow-y-auto pr-2 custom-scrollbar">
              {liveOrders?.map((order: any) => (
                <div key={order.id} className="flex items-center p-4 rounded-xl border border-slate-100 hover:bg-slate-50 transition-colors">
                  <div className="w-12 h-12 rounded-full bg-orange-100 flex items-center justify-center text-orange-600 mr-4">
                    <Utensils size={20} />
                  </div>
                  <div className="flex-1">
                    <div className="flex justify-between">
                      <h4 className="font-semibold text-slate-800">Order #{order.order_number}</h4>
                      <Badge className={getStatusColor(order.status)}>{order.status}</Badge>
                    </div>
                    <p className="text-sm text-slate-500 mt-0.5">Room {order.room_id || 'Table ' + order.table_number}</p>
                  </div>
                  <div className="ml-4 text-right">
                    <p className="font-bold text-slate-900">${order.total_amount}</p>
                    <p className="text-xs text-slate-400">{new Date(order.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</p>
                  </div>
                </div>
              ))}
              {(!liveOrders || liveOrders.length === 0) && (
                <div className="text-center py-12 text-slate-400">
                  <CheckCircle2 className="mx-auto mb-2 opacity-20" size={48} />
                  <p>All orders fulfilled.</p>
                </div>
              )}
            </div>
          </CardContent>
        </Card>

        {/* Service Requests */}
        <Card className="shadow-sm border-slate-200">
          <CardHeader className="flex flex-row items-center justify-between">
            <div>
              <CardTitle className="text-lg font-bold">Service Requests</CardTitle>
              <CardDescription>Unresolved guest requests that need attention</CardDescription>
            </div>
            <Button variant="ghost" className="text-indigo-600 font-medium">View All</Button>
          </CardHeader>
          <CardContent>
            <div className="space-y-4 max-h-[400px] overflow-y-auto pr-2 custom-scrollbar">
              {serviceRequests?.data?.map((req: any) => (
                <div key={req.id} className="flex items-center p-4 rounded-xl border border-slate-100 hover:bg-slate-50 transition-colors">
                  <div className="w-12 h-12 rounded-full bg-indigo-100 flex items-center justify-center text-indigo-600 mr-4">
                    <Wind size={20} />
                  </div>
                  <div className="flex-1">
                    <div className="flex justify-between">
                      <h4 className="font-semibold text-slate-800">{req.request_type.replace('_', ' ').toUpperCase()}</h4>
                      <Badge variant="outline" className="border-indigo-200 text-indigo-600">{req.priority || 'Normal'}</Badge>
                    </div>
                    <p className="text-sm text-slate-500 mt-0.5 truncate max-w-[200px]">{req.description}</p>
                  </div>
                  <div className="ml-4 text-right">
                    <p className="text-sm font-medium text-slate-800">Room {req.room_id}</p>
                    <p className="text-xs text-slate-400">{new Date(req.created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</p>
                  </div>
                </div>
              ))}
              {(!serviceRequests?.data || serviceRequests.data.length === 0) && (
                <div className="text-center py-12 text-slate-400">
                  <AlertCircle className="mx-auto mb-2 opacity-20" size={48} />
                  <p>No active service requests.</p>
                </div>
              )}
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}

function StatCard({ title, value, change, icon: Icon, color }: any) {
  const colorMap: any = {
    indigo: 'bg-indigo-500 text-indigo-500',
    emerald: 'bg-emerald-500 text-emerald-500',
    orange: 'bg-orange-500 text-orange-500',
    pink: 'bg-pink-500 text-pink-500',
  };

  return (
    <Card className="shadow-sm border-slate-200 overflow-hidden relative group">
      <CardContent className="p-6">
        <div className="flex items-center justify-between">
          <div>
            <p className="text-sm font-medium text-slate-500">{title}</p>
            <h3 className="text-2xl font-bold text-slate-900 mt-1">{value}</h3>
            <span className={`text-xs font-medium mt-2 flex items-center ${colorMap[color].split(' ')[1]}`}>
              {change} from yesterday
            </span>
          </div>
          <div className={`p-3 rounded-2xl ${colorMap[color].split(' ')[0]} bg-opacity-10 group-hover:scale-110 transition-transform`}>
            <Icon size={24} className={colorMap[color].split(' ')[1]} />
          </div>
        </div>
      </CardContent>
      <div className={`absolute bottom-0 left-0 right-0 h-1 ${colorMap[color].split(' ')[0]}`} />
    </Card>
  );
}

function getStatusColor(status: string) {
  switch (status.toLowerCase()) {
    case 'pending': return 'bg-slate-100 text-slate-600 border-none';
    case 'preparing': return 'bg-orange-100 text-orange-600 border-none';
    case 'ready': return 'bg-emerald-100 text-emerald-600 border-none';
    case 'served': return 'bg-blue-100 text-blue-600 border-none';
    case 'closed': return 'bg-slate-100 text-slate-400 border-none';
    default: return 'bg-slate-100 text-slate-600 border-none';
  }
}
