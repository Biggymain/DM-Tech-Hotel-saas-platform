'use client';

import * as React from 'react';
import { useQuery } from '@tanstack/react-query';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { EmptyState } from '@/components/ui/empty-state';
import { PlusIcon, HotelIcon, Loader2, WrenchIcon, MapIcon, BedDouble } from 'lucide-react';
import api from '@/lib/api';
import Link from 'next/link';

import { useMutation, useQueryClient } from '@tanstack/react-query';
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { toast } from 'sonner';

export default function RoomsPage() {
  const queryClient = useQueryClient();
  const [open, setOpen] = React.useState(false);
  const [roomForm, setRoomForm] = React.useState({ room_number: '', room_type_id: '', floor: '' });

  const { data: rooms = [], isLoading } = useQuery({
    queryKey: ['rooms'],
    queryFn: async () => {
      try {
        const { data } = await api.get('/api/v1/pms/rooms');
        return data.data ?? [];
      } catch {
        return [];
      }
    },
  });

  const { data: roomTypes = [] } = useQuery({
    queryKey: ['room-types'],
    queryFn: async () => {
      try {
        const { data } = await api.get('/api/v1/pms/room-types');
        return data.data ?? [];
      } catch {
        return [];
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

  const createRoom = useMutation({
    mutationFn: (data: any) => api.post('/api/v1/pms/rooms', data),
    onSuccess: (response: any) => {
      const roomNum = response.data?.data?.room_number || 'Room';
      toast.success(`${roomNum} created successfully!`);
      queryClient.invalidateQueries({ queryKey: ['rooms'] });
      setOpen(false);
      setRoomForm({ room_number: '', room_type_id: '', floor: '' });
    },
    onError: (err: any) => {
      toast.error(err?.response?.data?.message ?? 'Failed to create room.');
    },
  });

  return (
    <div className="space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h2 className="text-3xl font-bold tracking-tight">Rooms & Inventory</h2>
          <p className="text-muted-foreground">Manage physical room statuses, maintenance blocks, and inventory.</p>
        </div>
        <div className="flex gap-2">
          <Link href="/rooms/map">
            <Button variant="outline">
               <MapIcon className="mr-2 h-4 w-4" />
               Visual Map
            </Button>
          </Link>
          <Button variant="outline">
            <HotelIcon className="mr-2 h-4 w-4" />
            Room Types
          </Button>
          
          <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger
              render={
                <Button id="add-room-btn">
                  <PlusIcon className="mr-2 h-4 w-4" />
                  Add Room
                </Button>
              }
            />
            <DialogContent className="sm:max-w-md">
              <DialogHeader>
                <DialogTitle>Add New Physical Room</DialogTitle>
              </DialogHeader>
              <div className="space-y-4 py-4">
                <div className="space-y-1.5">
                  <label className="text-sm font-medium">Room Number</label>
                  <Input 
                    placeholder="e.g. 101, Suite A" 
                    value={roomForm.room_number}
                    onChange={(e) => setRoomForm(f => ({ ...f, room_number: e.target.value }))}
                  />
                </div>
                <div className="space-y-1.5">
                  <label className="text-sm font-medium">Room Type</label>
                  <Select 
                    onValueChange={(val) => setRoomForm(f => ({ ...f, room_type_id: val as string }))}
                    value={roomForm.room_type_id}
                  >
                    <SelectTrigger>
                      <SelectValue placeholder="Select room type" />
                    </SelectTrigger>
                    <SelectContent>
                      {roomTypes.map((rt: any) => (
                        <SelectItem key={rt.id} value={rt.id.toString()}>{rt.name}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
                <div className="space-y-1.5">
                  <label className="text-sm font-medium">Floor (Optional)</label>
                  <Input 
                    placeholder="e.g. 1, Ground, Penthouse" 
                    value={roomForm.floor}
                    onChange={(e) => setRoomForm(f => ({ ...f, floor: e.target.value }))}
                  />
                </div>
              </div>
              <DialogFooter>
                <Button variant="outline" onClick={() => setOpen(false)}>Cancel</Button>
                <div className="flex flex-col gap-1 w-full">
                  <Button 
                    disabled={!roomForm.room_number || !roomForm.room_type_id || createRoom.isPending || roomTypes.length === 0}
                    onClick={() => createRoom.mutate(roomForm)}
                    className="w-full"
                  >
                    {createRoom.isPending ? (
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

      <Card>
        <CardHeader>
          <CardTitle>Physical Rooms</CardTitle>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="flex justify-center p-8">
              <Loader2 className="animate-spin text-muted-foreground" />
            </div>
          ) : rooms.length === 0 ? (
            // Empty State — guides the user instead of hiding the section
            <EmptyState
              icon={BedDouble}
              title="No Rooms Configured Yet"
              description="Start by adding your first room. You can set the room number, type, and current occupancy status."
              actionLabel="Create First Room"
              onAction={() => document.getElementById('add-room-btn')?.click()}
            />
          ) : (
            <div className="rounded-md border">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Room Number</TableHead>
                    <TableHead>Type</TableHead>
                    <TableHead>Occupancy Status</TableHead>
                    <TableHead>Housekeeping</TableHead>
                    <TableHead className="text-right">Actions</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {rooms.map((room: any) => (
                    <TableRow key={room.id} className="hover:bg-muted/5 transition-colors">
                      <TableCell className="font-bold text-primary">{room.room_number}</TableCell>
                      <TableCell>
                        <Badge variant="outline" className="text-[10px] font-bold uppercase transition-colors">
                          {room.room_type?.name || 'Standard'}
                        </Badge>
                      </TableCell>
                      <TableCell>
                        <Badge
                          variant={
                            room.status === 'available'
                              ? 'outline'
                              : room.status === 'occupied'
                              ? 'default'
                              : 'destructive'
                          }
                        >
                          {room.status.toUpperCase()}
                        </Badge>
                      </TableCell>
                      <TableCell>
                        <Badge
                          variant={room.housekeeping_status === 'clean' ? 'secondary' : 'destructive'}
                          className="flex w-fit items-center"
                        >
                          {room.housekeeping_status.toUpperCase()}
                        </Badge>
                      </TableCell>
                      <TableCell className="text-right">
                        {room.status !== 'maintenance' ? (
                          <Button variant="ghost" size="sm" title="Mark for Maintenance">
                            <WrenchIcon className="h-4 w-4" />
                          </Button>
                        ) : (
                          <Button variant="ghost" size="sm">Resolve</Button>
                        )}
                        <Button variant="ghost" size="sm">Edit</Button>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
