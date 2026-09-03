'use client';

import * as React from 'react';
import { useQuery } from '@tanstack/react-query';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { BuildingIcon, LogInIcon, DollarSignIcon, ActivityIcon, HotelIcon } from 'lucide-react';
import api from '@/lib/api';
import { useAuth } from '@/context/AuthProvider';
import { Bar, BarChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

export default function GroupDashboardPage() {
  const { user } = useAuth();
  
  const { data: metrics, isLoading } = useQuery({
    queryKey: ['group-dashboard-metrics'],
    queryFn: async () => {
      // Mocking for now, will connect to multi-tenant API
      return {
        total_hotels: 3,
        overall_occupancy: 74,
        total_reservations: 145,
        aggregate_daily_revenue: 38500,
        hotel_performance: [
          { name: 'Hotel Alpha', occupancy: 78, revenue: 12500, reservations: 45 },
          { name: 'Hotel Beta', occupancy: 64, revenue: 9800, reservations: 32 },
          { name: 'Hotel Gamma', occupancy: 82, revenue: 16200, reservations: 68 },
        ],
      };
    },
  });

  if (isLoading) {
    return <div className="p-8"><ActivityIcon className="animate-spin" /></div>;
  }

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-3xl font-bold tracking-tight">Group Dashboard</h2>
        <p className="text-muted-foreground">
          Multi-property overview for {user?.name || 'Admin'}
        </p>
      </div>

      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <Card>
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-sm font-medium">Total Properties</CardTitle>
            <HotelIcon className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{metrics?.total_hotels}</div>
            <p className="text-xs text-muted-foreground">Active in your portfolio</p>
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-sm font-medium">Aggregate Occupancy</CardTitle>
            <BuildingIcon className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{metrics?.overall_occupancy}%</div>
            <p className="text-xs text-muted-foreground">Average across all properties</p>
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-sm font-medium">Total Active Reservations</CardTitle>
            <LogInIcon className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{metrics?.total_reservations}</div>
            <p className="text-xs text-muted-foreground">Current ongoing bookings</p>
          </CardContent>
        </Card>
        <Card>
          <CardHeader className="flex flex-row items-center justify-between pb-2">
            <CardTitle className="text-sm font-medium">Aggregate Daily Revenue</CardTitle>
            <DollarSignIcon className="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">${metrics?.aggregate_daily_revenue.toLocaleString()}</div>
            <p className="text-xs text-muted-foreground">Today's settled total</p>
          </CardContent>
        </Card>
      </div>

      <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-7">
        <Card className="col-span-4">
          <CardHeader>
            <CardTitle>Occupancy Comparison</CardTitle>
            <CardDescription>Occupancy rates (%) across properties.</CardDescription>
          </CardHeader>
          <CardContent className="pl-2">
            <div className="h-[300px]">
              <ResponsiveContainer width="100%" height="100%">
                <BarChart data={metrics?.hotel_performance}>
                  <XAxis dataKey="name" stroke="#888888" fontSize={12} tickLine={false} axisLine={false} />
                  <YAxis stroke="#888888" fontSize={12} tickLine={false} axisLine={false} tickFormatter={(value) => `${value}%`} />
                  <Tooltip cursor={{ fill: 'transparent' }} contentStyle={{ backgroundColor: 'var(--background)' }} />
                  <Bar dataKey="occupancy" fill="currentColor" radius={[4, 4, 0, 0]} className="fill-primary" />
                </BarChart>
              </ResponsiveContainer>
            </div>
          </CardContent>
        </Card>
        
        <Card className="col-span-3">
          <CardHeader>
            <CardTitle>Property Performance</CardTitle>
            <CardDescription>Revenue ranking by property</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="space-y-4">
              {metrics?.hotel_performance.sort((a: any, b: any) => b.revenue - a.revenue).map((hotel: any, idx: number) => (
                <div key={idx} className="flex items-center justify-between">
                  <div className="flex items-center gap-4">
                    <div className="flex h-8 w-8 items-center justify-center rounded-full bg-primary/10 text-primary font-bold">
                      {idx + 1}
                    </div>
                    <div className="grid gap-1">
                      <p className="text-sm font-medium leading-none">{hotel.name}</p>
                      <p className="text-xs text-muted-foreground">
                        {hotel.reservations} reservations
                      </p>
                    </div>
                  </div>
                  <div className="font-bold">${hotel.revenue.toLocaleString()}</div>
                </div>
              ))}
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  );
}
