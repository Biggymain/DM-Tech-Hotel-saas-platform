'use client';

import * as React from 'react';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { PlusIcon, StoreIcon } from 'lucide-react';

export default function OutletConfigurationPage() {
  const outlets = [
    { id: 1, name: 'Main Restaurant', type: 'restaurant', is_active: true },
    { id: 2, name: 'Poolside Bar', type: 'bar', is_active: true },
    { id: 3, name: 'Luxury Spa', type: 'spa', is_active: false },
    { id: 4, name: 'In-Room Dining', type: 'room_service', is_active: true },
    { id: 5, name: 'Guest Laundry', type: 'laundry', is_active: true },
  ];

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h2 className="text-3xl font-bold tracking-tight">Point of Sale Outlets</h2>
          <p className="text-muted-foreground">Define internal hotel departments that generate service or product billing.</p>
        </div>
        <div className="flex gap-2">
          <Button>
            <PlusIcon className="mr-2 h-4 w-4" />
            Add Outlet
          </Button>
        </div>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Configured Outlets</CardTitle>
          <CardDescription>All billing nodes actively tracked by the POS and Guest Folio systems.</CardDescription>
        </CardHeader>
        <CardContent>
          <div className="rounded-md border">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Outlet Name</TableHead>
                  <TableHead>Service Type</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead className="text-right">Manage</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {outlets.map((outlet) => (
                  <TableRow key={outlet.id}>
                    <TableCell className="font-medium flex items-center gap-2">
                      <StoreIcon className="h-4 w-4 text-muted-foreground" />
                      {outlet.name}
                    </TableCell>
                    <TableCell className="capitalize text-muted-foreground">{outlet.type.replace('_', ' ')}</TableCell>
                    <TableCell>
                      {outlet.is_active ? 
                        <Badge variant="default" className="bg-emerald-500 hover:bg-emerald-600">Active</Badge> 
                        : 
                        <Badge variant="secondary">Closed</Badge>
                      }
                    </TableCell>
                    <TableCell className="text-right space-x-2">
                      <Button variant="outline" size="sm">Catalog</Button>
                      <Button variant="ghost" size="sm">Edit</Button>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}
