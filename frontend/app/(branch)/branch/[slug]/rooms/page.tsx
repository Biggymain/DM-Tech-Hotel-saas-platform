'use client';

import * as React from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Card, CardContent, CardDescription, CardHeader, CardTitle, CardFooter } from '@/components/ui/card';
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogTrigger,
} from '@/components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Label } from '@/components/ui/label';
import {
  BedDouble,
  PlusIcon,
  Search,
  Filter,
  DollarSign,
  Loader2,
  TrendingUp,
  HotelIcon,
  SparklesIcon,
  PrinterIcon
} from 'lucide-react';
import Link from 'next/link';
import api from '@/lib/api';
import { useParams } from 'next/navigation';

export default function BranchRoomsPage() {
  const params = useParams();
  const branchSlug = params.slug;
  const queryClient = useQueryClient();
  const [open, setOpen] = React.useState(false);
  const [form, setForm] = React.useState({ room_type_id: '', room_number: '', floor: '' });

  const { data: roomTypes = [] } = useQuery({
    queryKey: ['branch-room-types', branchSlug],
    queryFn: async () => {
      const { data } = await api.get('/api/v1/pms/room-types');
      return data.data || [];
    }
  });

  const { data: rooms = [], isLoading: loadingRooms } = useQuery({
    queryKey: ['branch-rooms-list', branchSlug],
    queryFn: async () => {
      const { data } = await api.get('/api/v1/pms/rooms');
      return data.data || [];
    }
  });

  const { data: overview } = useQuery({
    queryKey: ['org-overview'],
    queryFn: async () => {
      const { data } = await api.get('/api/v1/organization/overview');
      return data;
    },
  });

  const currency = overview?.group?.currency || '₦';

  const createMutation = useMutation({
    mutationFn: (data: any) => api.post('/api/v1/pms/rooms', data),
    onSuccess: (response: any) => {
      const roomNum = response.data?.data?.room_number || 'Room';
      toast.success(`${roomNum} created successfully!`);
      queryClient.invalidateQueries({ queryKey: ['branch-rooms-list'] });
      setOpen(false);
      setForm({ room_type_id: '', room_number: '', floor: '' });
    },
    onError: (err: any) => {
      toast.error(err?.response?.data?.message ?? 'Failed to create room.');
    },
  });

  const printRoomQr = (room: any) => {
    // Determine branch and tenant IDs from overview
    const tenantId = overview?.group?.id || '';
    const branchId = overview?.hotel?.id || '';
    
    // Direct link to the Guest Portal (port 3002) with deep context
    const url = `http://localhost:3002/?tenant=${tenantId}&branch=${branchId}&room_id=${room.id}`;
    const qrImgUrl = `https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=${encodeURIComponent(url)}`;
    
    const win = window.open('', '_blank');
    if (win) {
      win.document.write(`<html><head><title>Print QR - Room ${room.room_number}</title><style>
          body { font-family: sans-serif; display:flex; flex-direction:column; align-items:center; justify-content:center; height:100vh; margin:0; }
          .qr-card { border: 2px solid #000; border-radius: 12px; padding: 24px; text-align: center; }
          .label { font-weight: bold; font-size: 24px; margin-top: 15px; }
          .sub-label { font-size: 14px; color: #666; margin-top: 5px; }
          @media print { .no-print { display: none; } }
      </style></head><body>`);
      win.document.write(`<div class="no-print" style="margin-bottom: 20px;"><button onclick="window.print()" style="padding:10px 20px; font-size:16px; cursor:pointer;">Print Now</button></div>`);
      win.document.write(`<div class="qr-card">
          <img src="${qrImgUrl}" width="250" height="250" />
          <div class="label">ROOM ${room.room_number}</div>
          <div class="sub-label">Guest Portal Access</div>
      </div>`);
      win.document.write('</body></html>');
      win.document.close();
    }
  };

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h2 className="text-3xl font-bold tracking-tight">Room Management & Pricing</h2>
          <p className="text-muted-foreground">Manage your property's room inventory and localized seasonal rates.</p>
        </div>
        <div className="flex gap-2">
          <Link href={`/branch/${params.slug}/room-types`}>
            <Button variant="outline">
              <HotelIcon className="mr-2 h-4 w-4" />
              Room Types
            </Button>
          </Link>
          <Button variant="outline" className="gap-2">
            <TrendingUp className="h-4 w-4" />
            Seasonal Rates
          </Button>
          <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger render={<Button><PlusIcon className="mr-2 h-4 w-4" />New Room</Button>} />
            <DialogContent className="sm:max-w-md">
              <DialogHeader>
                <DialogTitle>Create New Room</DialogTitle>
              </DialogHeader>
              <div className="space-y-4 py-4">
                <div className="space-y-1.5">
                  <Label>Room Number</Label>
                  <Input
                    placeholder="e.g. 101, RM-01"
                    value={form.room_number}
                    onChange={(e) => setForm(f => ({ ...f, room_number: e.target.value }))}
                  />
                </div>
                <div className="space-y-1.5">
                  <Label>Room Type</Label>
                  {roomTypes.length === 0 ? (
                    <div className="text-xs text-destructive bg-destructive/5 p-2 rounded border border-destructive/20">
                      No room types found. Please create a room type in Pricing section first.
                    </div>
                  ) : (
                    <Select
                      onValueChange={(val: string | null) => setForm(f => ({ ...f, room_type_id: val ?? '' }))}
                      value={form.room_type_id}
                    >
                      <SelectTrigger>
                        <SelectValue placeholder="Select type" />
                      </SelectTrigger>
                      <SelectContent>
                        {roomTypes.map((rt: any) => (
                          <SelectItem key={rt.id} value={rt.id.toString()}>{rt.name}</SelectItem>
                        ))}
                      </SelectContent>
                    </Select>
                  )}
                </div>
                <div className="space-y-1.5">
                  <Label>Floor</Label>
                  <Input
                    placeholder="e.g. Ground, Floor 1"
                    value={form.floor}
                    onChange={(e) => setForm(f => ({ ...f, floor: e.target.value }))}
                  />
                </div>
              </div>
              <DialogFooter>
                <Button variant="outline" onClick={() => setOpen(false)}>Cancel</Button>
                <div className="flex flex-col gap-1 w-full">
                  <Button
                    disabled={!form.room_number || !form.room_type_id || createMutation.isPending || roomTypes.length === 0}
                    onClick={() => createMutation.mutate(form)}
                    className="w-full"
                  >
                    {createMutation.isPending ? (
                      <><Loader2 className="mr-2 h-4 w-4 animate-spin" /> Creating...</>
                    ) : (
                      'Create Room'
                    )}
                  </Button>
                  {roomTypes.length === 0 && (
                    <p className="text-[10px] text-destructive font-medium text-center">
                      Please set up Room Types in Pricing first.
                    </p>
                  )}
                </div>
              </DialogFooter>
            </DialogContent>
          </Dialog>
        </div>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        <Card className="bg-gradient-to-br from-blue-500/5 to-cyan-500/5 border-blue-200/50">
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium text-blue-600">Total Rooms</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{rooms.length}</div>
            <p className="text-xs text-muted-foreground">Properties active in inventory</p>
          </CardContent>
        </Card>
        <Card className="bg-gradient-to-br from-emerald-500/5 to-teal-500/5 border-emerald-200/50">
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium text-emerald-600">Available Today</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold">{rooms.filter((r: any) => r.status === 'available').length}</div>
            <p className="text-xs text-muted-foreground">Ready for check-in</p>
          </CardContent>
        </Card>
        <Card className="bg-gradient-to-br from-violet-500/5 to-fuchsia-500/5 border-violet-200/50">
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium text-violet-600">Avg. Daily Rate</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-violet-700">{currency}24,500</div>
            <p className="text-xs text-muted-foreground">Current monthly average</p>
          </CardContent>
        </Card>
      </div>

      <Card>
        <CardHeader>
          <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
              <CardTitle>Room Inventory</CardTitle>
              <CardDescription>Adjust base pricing and status for each room unit.</CardDescription>
            </div>
            <div className="flex items-center gap-2">
              <div className="relative">
                <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                <Input placeholder="Filter rooms..." className="pl-9 w-[200px]" />
              </div>
              <Button variant="outline" size="icon"><Filter className="h-4 w-4" /></Button>
            </div>
          </div>
        </CardHeader>
        <CardContent>
          <div className="rounded-xl border border-border/40 overflow-hidden">
            <Table>
              <TableHeader className="bg-muted/30">
                <TableRow>
                  <TableHead className="w-[120px]">Room Number</TableHead>
                  <TableHead>Room Type</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead>Housekeeping</TableHead>
                  <TableHead>Base Price</TableHead>
                  <TableHead className="text-right">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {loadingRooms ? [...Array(5)].map((_, i) => (
                   <TableRow key={i}><TableCell colSpan={5} className="h-14 animate-pulse bg-muted/20" /></TableRow>
                )) : rooms.map((room: any) => (
                  <TableRow key={room.id} className="hover:bg-muted/5">
                    <TableCell className="font-bold text-primary">
                      {room.room_number || 'N/A'}
                    </TableCell>
                    <TableCell>
                      <Badge variant="outline" className="font-medium uppercase text-[10px]">
                        {room.room_type?.name || 'Standard Room'}
                      </Badge>
                    </TableCell>
                    <TableCell>
                      <Badge variant={room.status === 'available' ? 'default' : 'secondary'} className={room.status === 'available' ? 'bg-emerald-500/10 text-emerald-600 border-emerald-500/20' : ''}>
                        {room.status.toUpperCase()}
                      </Badge>
                    </TableCell>
                    <TableCell>
                       <Badge variant="outline" className="text-[10px] font-medium border-violet-200 text-violet-600 bg-violet-50">
                        {room.housekeeping_status.toUpperCase()}
                      </Badge>
                    </TableCell>
                    <TableCell>
                      <div className="flex items-center gap-1 font-bold">
                        <span className="text-muted-foreground text-xs">{currency}</span>
                        {Number(room.room_type?.base_price || 15000).toLocaleString()}
                      </div>
                    </TableCell>
                    <TableCell className="text-right flex items-center justify-end gap-2">
                      <Button variant="outline" size="sm" onClick={() => printRoomQr(room)}>
                        <PrinterIcon className="h-4 w-4 sm:mr-2" />
                        <span className="hidden sm:inline">QR Code</span>
                      </Button>
                      <Button variant="ghost" size="sm">Modify</Button>
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
