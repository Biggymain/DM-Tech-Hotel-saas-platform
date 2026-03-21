'use client';

import * as React from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle, CardFooter } from '@/components/ui/card';
import { Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis, CartesianGrid } from 'recharts';
import { SparklesIcon, TrendingUpIcon, AlertTriangleIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';

export default function RevenueIntelligencePage() {
  const demandData = [
    { date: 'Mon', demand_index: 60, current_occupancy: 55 },
    { date: 'Tue', demand_index: 65, current_occupancy: 58 },
    { date: 'Wed', demand_index: 80, current_occupancy: 62 },
    { date: 'Thu', demand_index: 85, current_occupancy: 70 },
    { date: 'Fri', demand_index: 110, current_occupancy: 88 },
    { date: 'Sat', demand_index: 125, current_occupancy: 95 },
    { date: 'Sun', demand_index: 90, current_occupancy: 78 },
  ];

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h2 className="text-3xl font-bold tracking-tight">Revenue Intelligence</h2>
          <p className="text-muted-foreground">AI-driven insights for demand planning and algorithmic price optimization.</p>
        </div>
      </div>

      <div className="grid gap-6 md:grid-cols-3">
        <Card className="col-span-3 md:col-span-2">
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <TrendingUpIcon className="h-5 w-5 text-primary" /> 
              Demand Velocity
            </CardTitle>
            <CardDescription>7-day forward-looking booking demand vs actual occupancy.</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="h-[300px]">
              <ResponsiveContainer width="100%" height="100%">
                <LineChart data={demandData} margin={{ top: 5, right: 20, left: 0, bottom: 5 }}>
                  <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="var(--muted)" />
                  <XAxis dataKey="date" stroke="var(--muted-foreground)" fontSize={12} tickLine={false} axisLine={false} />
                  <YAxis stroke="var(--muted-foreground)" fontSize={12} tickLine={false} axisLine={false} />
                  <Tooltip contentStyle={{ backgroundColor: 'var(--card)', borderRadius: '8px', border: '1px solid var(--border)' }} />
                  <Line type="monotone" name="Demand Index" dataKey="demand_index" stroke="var(--primary)" strokeWidth={3} />
                  <Line type="monotone" name="Current Occupancy" dataKey="current_occupancy" stroke="var(--muted-foreground)" strokeDasharray="5 5" strokeWidth={2} />
                </LineChart>
              </ResponsiveContainer>
            </div>
          </CardContent>
        </Card>

        <div className="col-span-3 md:col-span-1 space-y-4">
          <Card className="border-primary bg-primary/5">
            <CardHeader className="pb-2">
              <CardTitle className="text-lg flex items-center gap-2">
                <SparklesIcon className="h-4 w-4 text-primary" />
                Recommendation
              </CardTitle>
            </CardHeader>
            <CardContent>
              <p className="text-sm font-medium mb-1">Increase Default Weekend Rate</p>
              <p className="text-xs text-muted-foreground mb-4">
                Demand index for Friday and Saturday peaks at 125%. Algorithms suggest a rate markup of 15% will maintain 90%+ occupancy while maximizing RevPAR.
              </p>
            </CardContent>
            <CardFooter>
              <Button size="sm" className="w-full">Apply +15% Markup</Button>
            </CardFooter>
          </Card>

          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="text-lg flex items-center gap-2">
                <AlertTriangleIcon className="h-4 w-4 text-destructive" />
                Underpacing Alert
              </CardTitle>
            </CardHeader>
            <CardContent>
              <p className="text-sm font-medium mb-1">Corporate Block 'SpringTech'</p>
              <p className="text-xs text-muted-foreground mb-4">
                Group reservation #X712 is underpacing historical pickup by 32%. Consider offering discounted upgrades to guarantee revenue.
              </p>
            </CardContent>
            <CardFooter>
              <Button size="sm" variant="outline" className="w-full">View Reservation</Button>
            </CardFooter>
          </Card>
        </div>
      </div>
    </div>
  );
}
