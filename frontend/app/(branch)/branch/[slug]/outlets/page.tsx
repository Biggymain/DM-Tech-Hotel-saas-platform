'use client';

import * as React from 'react';
import Link from 'next/link';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { PlusIcon, Loader2, Wine, ChefHat, Store, Trash2, ChevronRight, PrinterIcon } from 'lucide-react';
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { toast } from 'sonner';
import api from '@/lib/api';
import { useParams } from 'next/navigation';

export default function OutletsPage() {
  const params = useParams();
  const queryClient = useQueryClient();
  const [open, setOpen] = React.useState(false);
  const [form, setForm] = React.useState({ name: '', type: 'restaurant', is_active: true });

  const { data: outlets, isLoading } = useQuery({
    queryKey: ['outlets'],
    queryFn: async () => {
      const { data } = await api.get('/api/v1/outlets');
      return data ?? [];
    },
  });

  const { data: overview } = useQuery({
    queryKey: ['org-overview'],
    queryFn: async () => {
      const { data } = await api.get('/api/v1/organization/overview');
      return data;
    },
  });

  const printOutletQrs = (outlet: any) => {
    if (!outlet.tables_count || outlet.tables_count === 0) {
        toast.error('This outlet has no tables configured. Please update tables count in POS Settings.');
        return;
    }
    const tenantId = overview?.group?.id || '';
    const branchId = overview?.hotel?.id || '';
    
    const win = window.open('', '_blank');
    if (win) {
      win.document.write(`<html><head><title>Bulk QRs - ${outlet.name}</title><style>
          body { font-family: sans-serif; margin: 0; padding: 20px; }
          .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #eee; padding-bottom: 10px; }
          .grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
          .qr-card { border: 1px solid #ddd; border-radius: 8px; padding: 15px; text-align: center; page-break-inside: avoid; }
          .label { font-weight: bold; font-size: 14px; margin-top: 5px; }
          .sub-label { font-size: 10px; color: #666; margin-top: 2px; }
          @media print { .no-print { display: none; } }
      </style></head><body>`);
      win.document.write(`<div class="header"><h1>${outlet.name} - Table QR Codes</h1><button onclick="window.print()" class="no-print">Print Now</button></div>`);
      win.document.write('<div class="grid">');
      
      for(let i=1; i<=outlet.tables_count; i++) {
          const url = `http://localhost:3002/?tenant=${tenantId}&branch=${branchId}&outlet_id=${outlet.id}&table=${i}`;
          const qrImgUrl = `https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${encodeURIComponent(url)}`;
          win.document.write(`<div class="qr-card">
              <img src="${qrImgUrl}" width="150" height="150" />
              <div class="label">TABLE ${i}</div>
              <div class="sub-label">${outlet.name}</div>
          </div>`);
      }
      win.document.write('</div></body></html>');
      win.document.close();
    }
  };

  const createMutation = useMutation({
    mutationFn: (data: any) => api.post('/api/v1/outlets', data),
    onSuccess: () => {
      toast.success('Outlet created successfully!');
      queryClient.invalidateQueries({ queryKey: ['outlets'] });
      setOpen(false);
      setForm({ name: '', type: 'restaurant', is_active: true });
    },
    onError: (err: any) => {
      toast.error(err?.response?.data?.message ?? 'Failed to create outlet.');
    },
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => api.delete(`/api/v1/outlets/${id}`),
    onSuccess: () => {
      toast.success('Outlet deleted.');
      queryClient.invalidateQueries({ queryKey: ['outlets'] });
    },
  });

  const getIcon = (type: string) => {
    switch (type) {
      case 'bar': return <Wine className="h-4 w-4" />;
      case 'store': return <Store className="h-4 w-4" />;
      default: return <ChefHat className="h-4 w-4" />;
    }
  };

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h2 className="text-3xl font-bold tracking-tight">Outlets & Stores</h2>
          <p className="text-muted-foreground">Manage your property's restaurants, bars, and the central store.</p>
        </div>
        <Dialog open={open} onOpenChange={setOpen}>
          <DialogTrigger render={<Button><PlusIcon className="mr-2 h-4 w-4" />New Outlet</Button>} />
          <DialogContent className="sm:max-w-md">
            <DialogHeader>
              <DialogTitle>Create New Outlet</DialogTitle>
            </DialogHeader>
            <div className="space-y-4 py-4">
              <div className="space-y-1.5">
                <label className="text-sm font-medium">Outlet Name</label>
                <Input
                  placeholder="e.g. Roof Top Bar, Main Kitchen"
                  value={form.name}
                  onChange={(e) => setForm(f => ({ ...f, name: e.target.value }))}
                />
              </div>
              <div className="space-y-1.5">
                <label className="text-sm font-medium">Type</label>
                <Select
                  onValueChange={(val: string | null) => setForm(f => ({ ...f, type: val ?? 'restaurant' }))}
                  value={form.type}
                >
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="restaurant">Restaurant</SelectItem>
                    <SelectItem value="bar">Bar</SelectItem>
                    <SelectItem value="cafe">Cafe</SelectItem>
                    <SelectItem value="store">Store (Supply Hub)</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </div>
            <DialogFooter>
              <Button variant="outline" onClick={() => setOpen(false)}>Cancel</Button>
              <Button
                disabled={!form.name || createMutation.isPending}
                onClick={() => createMutation.mutate(form)}
              >
                {createMutation.isPending ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : null}
                Create Outlet
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Configured Outlets</CardTitle>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="flex justify-center p-8"><Loader2 className="animate-spin text-muted-foreground" /></div>
          ) : (
            <div className="rounded-md border">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Name</TableHead>
                    <TableHead>Type</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead className="text-right">Actions</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {outlets?.map((outlet: any) => (
                    <TableRow key={outlet.id}>
                      <TableCell className="font-bold">
                        <div className="flex items-center gap-2">
                          {getIcon(outlet.type)}
                          {outlet.name}
                        </div>
                      </TableCell>
                      <TableCell className="capitalize">{outlet.type}</TableCell>
                      <TableCell>
                        <Badge variant={outlet.is_active ? 'default' : 'secondary'}>
                          {outlet.is_active ? 'Active' : 'Inactive'}
                        </Badge>
                      </TableCell>
                      <TableCell className="text-right flex items-center justify-end gap-2">
                        {outlet.tables_count > 0 && (
                          <Button variant="outline" size="sm" className="h-8 gap-1" onClick={() => printOutletQrs(outlet)}>
                            <PrinterIcon className="h-3 w-3" />
                            Print Tables
                          </Button>
                        )}
                        <Button variant="outline" size="sm" className="h-8 gap-1" asChild>
                          <Link href={`/branch/${params.slug}/pos?outlet_id=${outlet.id}`}>
                            Manage
                            <ChevronRight className="h-3 w-3" />
                          </Link>
                        </Button>
                        <Button
                          variant="ghost"
                          size="icon"
                          className="text-destructive h-8 w-8"
                          onClick={() => deleteMutation.mutate(outlet.id)}
                        >
                          <Trash2 className="h-4 w-4" />
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))}
                  {outlets?.length === 0 && (
                    <TableRow>
                      <TableCell colSpan={4} className="text-center py-6 text-muted-foreground italic">
                        No outlets found.
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
