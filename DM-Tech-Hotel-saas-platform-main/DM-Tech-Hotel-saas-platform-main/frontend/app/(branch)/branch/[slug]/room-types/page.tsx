'use client';

import * as React from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { PlusIcon, Loader2, Edit2, Trash2, ArrowLeft } from 'lucide-react';
import { useParams, useRouter } from 'next/navigation';
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { toast } from 'sonner';
import api from '@/lib/api';

export default function RoomTypesPage() {
  const params = useParams();
  const router = useRouter();
  const queryClient = useQueryClient();
  const [open, setOpen] = React.useState(false);
  const [editingType, setEditingType] = React.useState<any>(null);
  const [form, setForm] = React.useState({ name: '', description: '', base_price: 0, capacity: 2, amenities: '' });

  const { data: roomTypes, isLoading } = useQuery({
    queryKey: ['roomTypes'],
    queryFn: async () => {
      const { data } = await api.get('/api/v1/pms/room-types');
      return data.data ?? [];
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

  const createMutation = useMutation({
    mutationFn: (data: any) => api.post('/api/v1/pms/room-types', data),
    onSuccess: () => {
      toast.success('Room type created!');
      queryClient.invalidateQueries({ queryKey: ['roomTypes'] });
      setOpen(false);
      resetForm();
    },
    onError: (err: any) => toast.error(err?.response?.data?.message || 'Failed to create.'),
  });

  const updateMutation = useMutation({
    mutationFn: (data: any) => api.put(`/api/v1/pms/room-types/${data.id}`, data),
    onSuccess: () => {
      toast.success('Room type updated!');
      queryClient.invalidateQueries({ queryKey: ['roomTypes'] });
      setOpen(false);
      resetForm();
    },
    onError: (err: any) => toast.error(err?.response?.data?.message || 'Failed to update.'),
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => api.delete(`/api/v1/pms/room-types/${id}`),
    onSuccess: () => {
      toast.success('Room type deleted!');
      queryClient.invalidateQueries({ queryKey: ['roomTypes'] });
    },
    onError: (err: any) => toast.error(err?.response?.data?.message || 'Failed to delete (check for active rooms).'),
  });

  const resetForm = () => {
    setForm({ name: '', description: '', base_price: 0, capacity: 2, amenities: '' });
    setEditingType(null);
  };

  const handleEdit = (type: any) => {
    setEditingType(type);
    setForm({
      name: type.name,
      description: type.description || '',
      base_price: type.base_price,
      capacity: type.capacity,
      amenities: Array.isArray(type.amenities) ? type.amenities.join(', ') : (type.amenities || ''),
    });
    setOpen(true);
  };

  const handleSubmit = () => {
    const payload = {
      ...form,
      amenities: form.amenities.split(',').map(a => a.trim()).filter(a => a),
    };
    if (editingType) {
      updateMutation.mutate({ ...payload, id: editingType.id });
    } else {
      createMutation.mutate(payload);
    }
  };

  return (
    <div className="space-y-6 max-w-5xl mx-auto py-6 px-4">
      <div className="flex items-center justify-between">
        <div className="space-y-1">
          <Button 
            variant="ghost" 
            size="sm" 
            className="pl-0 text-muted-foreground"
            onClick={() => router.back()}
          >
            <ArrowLeft className="mr-2 h-4 w-4" />
            Back to Rooms
          </Button>
          <h2 className="text-3xl font-bold tracking-tight">Room Types</h2>
          <p className="text-muted-foreground">Manage your property's room categories and base pricing.</p>
        </div>
        <Dialog open={open} onOpenChange={(val) => { setOpen(val); if(!val) resetForm(); }}>
          <DialogTrigger render={
            <Button className="bg-emerald-600 hover:bg-emerald-700">
              <PlusIcon className="mr-2 h-4 w-4" />
              Add Room Type
            </Button>
          } />
          <DialogContent className="sm:max-w-[425px]">
            <DialogHeader>
              <DialogTitle>{editingType ? 'Edit Room Type' : 'Create Room Type'}</DialogTitle>
            </DialogHeader>
            <div className="grid gap-4 py-4">
              <div className="space-y-2">
                <label className="text-sm font-medium leading-none">Name</label>
                <Input 
                  value={form.name} 
                  onChange={e => setForm(f => ({ ...f, name: e.target.value }))}
                  placeholder="e.g. Deluxe Suite" 
                />
              </div>
              <div className="space-y-2">
                <label className="text-sm font-medium leading-none">Capacity (Persons)</label>
                <Input 
                  type="number"
                  value={form.capacity} 
                  onChange={e => setForm(f => ({ ...f, capacity: parseInt(e.target.value) }))}
                />
              </div>
              <div className="space-y-2">
                <label className="text-sm font-medium leading-none">Base Price (Per Night)</label>
                <Input 
                  type="number"
                  value={form.base_price} 
                  onChange={e => setForm(f => ({ ...f, base_price: parseFloat(e.target.value) }))}
                />
              </div>
              <div className="space-y-2">
                <label className="text-sm font-medium leading-none">Description</label>
                <Textarea 
                  value={form.description} 
                  onChange={e => setForm(f => ({ ...f, description: e.target.value }))}
                  placeholder="Describe the overall experience..."
                />
              </div>
              <div className="space-y-2">
                <label className="text-sm font-medium leading-none">Amenities (Comma separated)</label>
                <Textarea 
                  value={form.amenities} 
                  onChange={e => setForm(f => ({ ...f, amenities: e.target.value }))}
                  placeholder="e.g. Free WiFi, AC, Breakfast, King Bed"
                />
              </div>
            </div>
            <DialogFooter>
              <Button variant="outline" onClick={() => setOpen(false)}>Cancel</Button>
              <Button 
                onClick={handleSubmit}
                disabled={!form.name || createMutation.isPending || updateMutation.isPending}
                className="bg-emerald-600 hover:bg-emerald-700"
              >
                {(createMutation.isPending || updateMutation.isPending) && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                {editingType ? 'Update' : 'Create'}
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      </div>

      <Card className="border-emerald-100 shadow-sm">
        <CardHeader>
          <CardTitle>Configured Room Categories</CardTitle>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="flex justify-center py-12"><Loader2 className="h-8 w-8 animate-spin text-emerald-500" /></div>
          ) : (
            <div className="rounded-md border border-emerald-50">
              <Table>
                <TableHeader className="bg-emerald-50/50">
                  <TableRow>
                    <TableHead className="font-semibold text-emerald-900">Type Name</TableHead>
                    <TableHead className="font-semibold text-emerald-900">Capacity</TableHead>
                    <TableHead className="font-semibold text-emerald-900">Base Price</TableHead>
                    <TableHead className="font-semibold text-emerald-900 text-right">Actions</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {roomTypes?.length === 0 ? (
                    <TableRow>
                      <TableCell colSpan={4} className="text-center py-8 text-muted-foreground">
                        No room types configured. Add your first one to start creating rooms.
                      </TableCell>
                    </TableRow>
                  ) : (
                    roomTypes?.map((type: any) => (
                      <TableRow key={type.id} className="hover:bg-emerald-50/20 transition-colors">
                        <TableCell className="font-medium text-emerald-950">
                          {type.name}
                          {type.description && <p className="text-xs text-muted-foreground font-normal">{type.description}</p>}
                        </TableCell>
                        <TableCell>
                          <Badge variant="outline" className="text-emerald-700 border-emerald-200">
                            {type.capacity} {type.capacity === 1 ? 'Guest' : 'Guests'}
                          </Badge>
                        </TableCell>
                        <TableCell className="font-bold text-emerald-700">
                          {currency}{type.base_price.toLocaleString()}
                        </TableCell>
                        <TableCell className="text-right">
                          <div className="flex justify-end gap-2">
                            <Button 
                              variant="ghost" 
                              size="sm" 
                              onClick={() => handleEdit(type)}
                              className="text-emerald-600 hover:text-emerald-700 hover:bg-emerald-50"
                            >
                              <Edit2 className="h-3.5 w-3.5" />
                            </Button>
                            <Button 
                              variant="ghost" 
                              size="sm" 
                              onClick={() => deleteMutation.mutate(type.id)}
                              className="text-destructive hover:text-destructive/90 hover:bg-destructive/5"
                            >
                              <Trash2 className="h-3.5 w-3.5" />
                            </Button>
                          </div>
                        </TableCell>
                      </TableRow>
                    ))
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
