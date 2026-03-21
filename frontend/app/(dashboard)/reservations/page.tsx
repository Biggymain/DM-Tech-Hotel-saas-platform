'use client';

import * as React from 'react';
import { useQuery } from '@tanstack/react-query';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { PlusIcon, CalendarDaysIcon, Loader2 } from 'lucide-react';
import api from '@/lib/api';

export default function ReservationsPage() {
  const { data: reservations, isLoading } = useQuery({
    queryKey: ['reservations', 'group'],
    queryFn: () => api.get('/api/v1/pms/reservations?scope=group').then(res => res.data.data),
  });

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h2 className="text-3xl font-bold tracking-tight">Group Reservations</h2>
          <p className="text-muted-foreground">Unified view of all bookings across your branches.</p>
        </div>
        <div className="flex gap-2">
          <Button variant="outline">
            <CalendarDaysIcon className="mr-2 h-4 w-4" />
            Calendar View
          </Button>
          <Button>
            <PlusIcon className="mr-2 h-4 w-4" />
            New Reservation
          </Button>
        </div>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>All Branch Bookings</CardTitle>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="flex justify-center p-8"><Loader2 className="animate-spin text-muted-foreground" /></div>
          ) : (
            <div className="rounded-md border">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Branch</TableHead>
                    <TableHead>Confirmation</TableHead>
                    <TableHead>Guest Name</TableHead>
                    <TableHead>Room</TableHead>
                    <TableHead>Check-in</TableHead>
                    <TableHead>Check-out</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead className="text-right">Actions</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {reservations?.map((res: any) => (
                    <TableRow key={res.id}>
                      <TableCell>
                        <Badge variant="secondary" className="font-bold">
                          {res.hotel?.name || 'Main'}
                        </Badge>
                      </TableCell>
                      <TableCell className="font-medium">{res.reservation_number}</TableCell>
                      <TableCell>{res.guest?.first_name} {res.guest?.last_name}</TableCell>
                      <TableCell>
                        {res.rooms?.map((r: any) => r.room_number).join(', ') || 'Unassigned'}
                      </TableCell>
                      <TableCell>{res.check_in_date?.split('T')[0]}</TableCell>
                      <TableCell>{res.check_out_date?.split('T')[0]}</TableCell>
                      <TableCell>
                        <Badge variant={res.status === 'checked_in' ? 'default' : res.status === 'confirmed' ? 'outline' : 'secondary'}>
                          {res.status.replace('_', ' ').toUpperCase()}
                        </Badge>
                      </TableCell>
                      <TableCell className="text-right">
                        <Button variant="ghost" size="sm">Manage</Button>
                      </TableCell>
                    </TableRow>
                  ))}
                  {reservations?.length === 0 && (
                    <TableRow>
                      <TableCell colSpan={7} className="text-center py-6 text-muted-foreground">
                        No reservations found.
                      </TableCell>
                    </TableRow>
                  )}
                </TableBody>
              </Table>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
