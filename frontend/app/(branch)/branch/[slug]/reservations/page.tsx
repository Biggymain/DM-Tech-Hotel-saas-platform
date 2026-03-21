'use client';

import * as React from 'react';
import { useQuery } from '@tanstack/react-query';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { PlusIcon, CalendarDaysIcon, Loader2, Search, Filter } from 'lucide-react';
import { 
  Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogFooter, DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { toast } from 'sonner';
import api from '@/lib/api';
import { useMutation, useQueryClient } from '@tanstack/react-query';
import { format, addDays, differenceInCalendarDays, parseISO } from 'date-fns';

export default function ReservationsPage() {
  const queryClient = useQueryClient();
  const [open, setOpen] = React.useState(false);
  const [form, setForm] = React.useState({
    guest_first_name: '',
    guest_last_name: '',
    guest_email: '',
    room_type_id: '',
    rooms: [] as any[],
    check_in_date: format(new Date(), 'yyyy-MM-dd'),
    check_out_date: format(addDays(new Date(), 1), 'yyyy-MM-dd'),
    number_of_days: '1',
  });

  const { data: overview } = useQuery({
    queryKey: ['org-overview'],
    queryFn: async () => {
      const { data } = await api.get('/api/v1/organization/overview');
      return data;
    },
  });

  const currency = overview?.group?.currency || '₦';


  const { data: roomTypes = [] } = useQuery({
    queryKey: ['room-types'],
    queryFn: () => api.get('/api/v1/pms/room-types').then(res => res.data.data || []),
  });

  const mutation = useMutation({
    mutationFn: (data: any) => api.post('/api/v1/pms/reservations', data),
    onSuccess: () => {
      toast.success('Reservation created successfully!');
      queryClient.invalidateQueries({ queryKey: ['reservations'] });
      setOpen(false);
      setForm({
        guest_first_name: '',
        guest_last_name: '',
        guest_email: '',
        room_type_id: '',
        rooms: [],
        check_in_date: format(new Date(), 'yyyy-MM-dd'),
        check_out_date: format(addDays(new Date(), 1), 'yyyy-MM-dd'),
        number_of_days: '1',
      });
    }
  });

  const extendMutation = useMutation({
    mutationFn: ({ id, days }: { id: number, days: number }) => 
      api.post(`/api/v1/pms/reservations/${id}/extend`, { days }),
    onSuccess: () => {
      toast.success('Stay extended successfully!');
      queryClient.invalidateQueries({ queryKey: ['reservations'] });
    },
    onError: (err: any) => {
      toast.error(err?.response?.data?.message || 'Failed to extend stay');
    }
  });

  const { data: reservations, isLoading } = useQuery({
    queryKey: ['reservations'],
    queryFn: () => api.get('/api/v1/pms/reservations').then(res => res.data.data),
  });

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h2 className="text-3xl font-bold tracking-tight">Reservations</h2>
          <p className="text-muted-foreground">Manage your bookings, arrivals, and departures.</p>
        </div>
        <div className="flex gap-2">
          <Button variant="outline" onClick={() => toast.info('Switching to Calendar View...')}>
            <CalendarDaysIcon className="mr-2 h-4 w-4" />
            Calendar View
          </Button>
          <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger>
              <Button>
                <PlusIcon className="mr-2 h-4 w-4" />
                New Reservation
              </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-md">
              <DialogHeader>
                <DialogTitle>New Reservation</DialogTitle>
                <DialogDescription>Enter guest details and stay dates.</DialogDescription>
              </DialogHeader>
              <div className="grid gap-4 py-4">
                <div className="grid grid-cols-2 gap-4">
                  <div className="space-y-2">
                    <Label>First Name</Label>
                    <Input value={form.guest_first_name} onChange={e => setForm({...form, guest_first_name: e.target.value})} />
                  </div>
                  <div className="space-y-2">
                    <Label>Last Name</Label>
                    <Input value={form.guest_last_name} onChange={e => setForm({...form, guest_last_name: e.target.value})} />
                  </div>
                </div>
                <div className="space-y-2">
                  <Label>Email</Label>
                  <Input type="email" value={form.guest_email} onChange={e => setForm({...form, guest_email: e.target.value})} />
                </div>
                <div className="space-y-2">
                  <Label>Room Type</Label>
                  <Select onValueChange={v => setForm({...form, room_type_id: v as string})} value={form.room_type_id || ""}>
                    <SelectTrigger>
                      <SelectValue placeholder="Select type" />
                    </SelectTrigger>
                    <SelectContent>
                      {roomTypes.map((rt: any) => (
                        <SelectItem key={rt.id} value={rt.id.toString()}>{rt.name}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
                <div className="grid grid-cols-2 gap-4">
                  <div className="space-y-2">
                    <Label>Check-in Date</Label>
                    <Input type="date" value={form.check_in_date} onChange={e => {
                      const newIn = e.target.value;
                      setForm(prev => {
                        const days = parseInt(prev.number_of_days || '0');
                        if (days > 0 && newIn) {
                          const out = new Date(newIn);
                          out.setDate(out.getDate() + days);
                          return { ...prev, check_in_date: newIn, check_out_date: out.toISOString().split('T')[0] };
                        }
                        return { ...prev, check_in_date: newIn };
                      });
                    }} />
                  </div>
                  <div className="space-y-2">
                    <Label>Stay Duration (Days)</Label>
                    <Input type="number" min="1" value={form.number_of_days} onChange={e => {
                      const days = parseInt(e.target.value || '0');
                      setForm(prev => {
                        if (days > 0 && prev.check_in_date) {
                          const out = addDays(parseISO(prev.check_in_date), days);
                          return { ...prev, number_of_days: e.target.value, check_out_date: format(out, 'yyyy-MM-dd') };
                        }
                        return { ...prev, number_of_days: e.target.value };
                      });
                    }} />
                  </div>
                </div>
                <div className="space-y-2">
                  <Label>Check-out Date (Standard 12:00 PM)</Label>
                  <Input type="date" value={form.check_out_date} onChange={e => {
                    const newOut = e.target.value;
                    setForm(prev => {
                      if (newOut && prev.check_in_date) {
                        const dIn = parseISO(prev.check_in_date);
                        const dOut = parseISO(newOut);
                        const diffDays = differenceInCalendarDays(dOut, dIn);
                        if (diffDays > 0) {
                          return { ...prev, check_out_date: newOut, number_of_days: diffDays.toString() };
                        }
                      }
                      return { ...prev, check_out_date: newOut };
                    });
                  }} />
                  <p className="text-[10px] text-muted-foreground italic">Guests have until 12:00 PM on this date to check out.</p>
                </div>
              </div>
              <DialogFooter>
                <Button variant="outline" onClick={() => setOpen(false)}>Cancel</Button>
                <Button onClick={() => mutation.mutate(form)} disabled={mutation.isPending}>
                  {mutation.isPending && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                  Confirm & Pay
                </Button>
              </DialogFooter>
            </DialogContent>
          </Dialog>
        </div>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Recent Bookings</CardTitle>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="flex justify-center p-8"><Loader2 className="animate-spin text-muted-foreground" /></div>
          ) : (
            <div className="rounded-md border">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Confirmation</TableHead>
                    <TableHead>Guest Name</TableHead>
                    <TableHead>Room</TableHead>
                    <TableHead>Room Type</TableHead>
                    <TableHead>Check-in</TableHead>
                    <TableHead>Check-out</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead className="text-right">Actions</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {reservations?.map((res: any) => (
                    <TableRow key={res.id}>
                      <TableCell className="font-medium">{res.confirmation_number}</TableCell>
                      <TableCell>{res.guest?.first_name} {res.guest?.last_name}</TableCell>
                      <TableCell>
                        {res.rooms?.length > 0 ? (
                          <div className="font-bold text-sm text-primary">
                            {res.rooms.map((r: any) => r.room_number).join(', ')}
                          </div>
                        ) : (
                          <span className="text-muted-foreground text-xs italic">Unassigned</span>
                        )}
                      </TableCell>
                      <TableCell>
                        <Badge variant="outline" className="text-[10px] font-semibold uppercase text-muted-foreground border-muted-foreground/20">
                          {res.rooms?.[0]?.room_type?.name || 'Standard'}
                        </Badge>
                      </TableCell>
                      <TableCell className="text-sm">{res.check_in_date?.split('T')[0]}</TableCell>
                      <TableCell className="text-sm">{res.check_out_date?.split('T')[0]}</TableCell>
                      <TableCell>
                        <Badge variant={res.status === 'checked_in' ? 'default' : res.status === 'confirmed' ? 'outline' : 'secondary'}>
                          {res.status.replace('_', ' ').toUpperCase()}
                        </Badge>
                      </TableCell>
                      <TableCell className="text-right flex items-center justify-end gap-2">
                        <Button 
                          variant="outline" 
                          size="sm"
                          className="h-8 text-xs font-bold hover:bg-primary/10 hover:text-primary transition-colors disabled:opacity-50"
                          disabled={res.status === 'checked_out' || res.status === 'cancelled'}
                          onClick={() => {
                            const days = window.prompt("Enter number of additional days to extend stay:");
                            if (days && !isNaN(parseInt(days))) {
                              extendMutation.mutate({ id: res.id, days: parseInt(days) });
                            }
                          }}
                        >
                          Extend
                        </Button>
                        <Button variant="ghost" size="sm" className="h-8">Manage</Button>
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
