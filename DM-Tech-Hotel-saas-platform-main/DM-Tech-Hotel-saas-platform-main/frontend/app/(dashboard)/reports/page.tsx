'use client';

import * as React from 'react';
import { useQuery } from '@tanstack/react-query';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Bar, BarChart, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis, CartesianGrid } from 'recharts';
import { Loader2, DownloadIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import api from '@/lib/api';

export default function ReportsPage() {
  const { data: reports, isLoading } = useQuery({
    queryKey: ['financial-reports'],
    queryFn: async () => {
      try {
        const { data } = await api.get('/api/v1/reports/financial');
        return data.data;
      } catch (error) {
        // Fallback for UI demonstration
        return {
          revenue_trends: [
            { date: 'Jan', reservations: 4000, pos_sales: 2400 },
            { date: 'Feb', reservations: 3000, pos_sales: 1398 },
            { date: 'Mar', reservations: 2000, pos_sales: 9800 },
            { date: 'Apr', reservations: 2780, pos_sales: 3908 },
            { date: 'May', reservations: 1890, pos_sales: 4800 },
            { date: 'Jun', reservations: 2390, pos_sales: 3800 },
            { date: 'Jul', reservations: 3490, pos_sales: 4300 },
          ],
          outlet_performance: [
            { name: 'Restaurant', sales: 15400 },
            { name: 'Spa', sales: 9800 },
            { name: 'Room Service', sales: 4300 },
            { name: 'Bar', sales: 8700 },
          ],
        };
      }
    },
  });

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h2 className="text-3xl font-bold tracking-tight">Financial & Analytics Reports</h2>
          <p className="text-muted-foreground">Comprehensive overview of revenue streams and hotel performance.</p>
        </div>
        <div className="flex gap-2">
          <Button variant="outline">
            <DownloadIcon className="mr-2 h-4 w-4" />
            Export CSV
          </Button>
        </div>
      </div>

      {isLoading ? (
        <div className="flex justify-center p-12"><Loader2 className="animate-spin text-muted-foreground w-8 h-8" /></div>
      ) : (
        <div className="grid gap-6 md:grid-cols-2">
          <Card className="col-span-2">
            <CardHeader>
              <CardTitle>Revenue Trends</CardTitle>
              <CardDescription>Monthly comparison between Room Reservations and POS Outlets.</CardDescription>
            </CardHeader>
            <CardContent>
              <div className="h-[350px]">
                <ResponsiveContainer width="100%" height="100%">
                  <LineChart data={reports?.revenue_trends} margin={{ top: 5, right: 30, left: 20, bottom: 5 }}>
                    <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="var(--muted)" />
                    <XAxis dataKey="date" stroke="var(--muted-foreground)" fontSize={12} tickLine={false} axisLine={false} />
                    <YAxis stroke="var(--muted-foreground)" fontSize={12} tickLine={false} axisLine={false} tickFormatter={(value) => `$${value}`} />
                    <Tooltip contentStyle={{ backgroundColor: 'var(--card)', borderRadius: '8px', border: '1px solid var(--border)' }} />
                    <Line type="monotone" name="Reservations" dataKey="reservations" stroke="var(--primary)" strokeWidth={3} activeDot={{ r: 8 }} />
                    <Line type="monotone" name="POS Sales" dataKey="pos_sales" stroke="var(--chart-2)" strokeWidth={3} />
                  </LineChart>
                </ResponsiveContainer>
              </div>
            </CardContent>
          </Card>

          <Card className="col-span-2 md:col-span-1">
            <CardHeader>
              <CardTitle>POS Outlet Performance</CardTitle>
              <CardDescription>Top grossing internal departments.</CardDescription>
            </CardHeader>
            <CardContent>
              <div className="h-[300px]">
                <ResponsiveContainer width="100%" height="100%">
                  <BarChart data={reports?.outlet_performance} margin={{ top: 5, right: 0, left: 0, bottom: 5 }} layout="vertical">
                    <CartesianGrid strokeDasharray="3 3" horizontal={false} stroke="var(--muted)" />
                    <XAxis type="number" stroke="var(--muted-foreground)" fontSize={12} tickLine={false} axisLine={false} tickFormatter={(value) => `$${value}`} />
                    <YAxis dataKey="name" type="category" stroke="var(--muted-foreground)" fontSize={12} tickLine={false} axisLine={false} width={100} />
                    <Tooltip cursor={{ fill: 'transparent' }} contentStyle={{ backgroundColor: 'var(--card)', borderRadius: '8px', border: '1px solid var(--border)' }} />
                    <Bar name="Total Sales" dataKey="sales" fill="var(--chart-3)" radius={[0, 4, 4, 0]} barSize={30} />
                  </BarChart>
                </ResponsiveContainer>
              </div>
            </CardContent>
          </Card>
        </div>
      )}
    </div>
  );
}
