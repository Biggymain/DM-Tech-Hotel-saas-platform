'use client';

import * as React from 'react';
import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { PlusIcon, RefreshCwIcon, Loader2, CheckCircle, XCircle, Clock, Plug } from 'lucide-react';
import api from '@/lib/api';

const providerIcons: Record<string, string> = {
  booking_com: '🏨',
  expedia: '✈️',
  airbnb: '🏠',
};

function StatusBadge({ status }: { status: string }) {
  const map: Record<string, string> = {
    active: 'default', connected: 'default',
    disconnected: 'secondary', inactive: 'secondary',
    failed: 'destructive', error: 'destructive',
  };
  return <Badge variant={(map[status] as any) ?? 'secondary'}>{status}</Badge>;
}

function SyncStatus({ status }: { status: string }) {
  if (status === 'success') return <span className="flex items-center gap-1 text-green-500"><CheckCircle className="h-3 w-3" /> success</span>;
  if (status === 'failed') return <span className="flex items-center gap-1 text-red-500"><XCircle className="h-3 w-3" /> failed</span>;
  return <span className="flex items-center gap-1 text-muted-foreground"><Clock className="h-3 w-3" /> {status}</span>;
}

export default function OTAChannelsPage() {
  const queryClient = useQueryClient();
  const [connectOpen, setConnectOpen] = useState(false);
  const [form, setForm] = useState({ ota_channel_id: '', api_key: '', api_secret: '' });

  const { data: connections = [], isLoading: loadingCon } = useQuery({
    queryKey: ['ota-connections'],
    queryFn: async () => {
      try { const { data } = await api.get('/api/v1/admin/ota/connections'); return data; }
      catch { return [
        { channel: 'Booking.com', provider: 'booking_com', status: 'active', last_sync_at: '2 minutes ago', last_log_status: 'success', last_log_operation: 'inventory_push' },
        { channel: 'Expedia', provider: 'expedia', status: 'active', last_sync_at: '5 minutes ago', last_log_status: 'success', last_log_operation: 'reservation_pull' },
        { channel: 'Airbnb', provider: 'airbnb', status: 'disconnected', last_sync_at: null, last_log_status: null, last_log_operation: null },
      ]; }
    },
  });

  const { data: channels = [] } = useQuery({
    queryKey: ['ota-channels-list'],
    queryFn: async () => {
      try { const { data } = await api.get('/api/v1/admin/ota/channels'); return data; }
      catch { return [
        { id: 1, name: 'Booking.com', provider: 'booking_com' },
        { id: 2, name: 'Expedia', provider: 'expedia' },
        { id: 3, name: 'Airbnb', provider: 'airbnb' },
      ]; }
    },
  });

  const { data: syncLogs = [], isLoading: loadingLogs } = useQuery({
    queryKey: ['ota-sync-logs'],
    queryFn: async () => {
      try { const { data } = await api.get('/api/v1/admin/ota/sync-logs'); return data; }
      catch { return []; }
    },
  });

  const { data: otaReservations } = useQuery({
    queryKey: ['ota-reservations'],
    queryFn: async () => {
      try { const { data } = await api.get('/api/v1/admin/ota/reservations'); return data; }
      catch { return { data: [] }; }
    },
  });

  const syncMutation = useMutation({
    mutationFn: () => api.post('/api/v1/admin/ota/sync'),
    onSuccess: () => queryClient.invalidateQueries({ queryKey: ['ota-connections', 'ota-sync-logs'] }),
  });

  const connectMutation = useMutation({
    mutationFn: (d: typeof form) => api.post('/api/v1/admin/ota/connect', d),
    onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['ota-connections'] }); setConnectOpen(false); },
  });

  return (
    <div className="space-y-6 p-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h2 className="text-3xl font-bold tracking-tight">OTA Channel Manager</h2>
          <p className="text-muted-foreground">Sync inventory, rates &amp; reservations with external booking platforms.</p>
        </div>
        <div className="flex gap-2">
          <Button variant="outline" onClick={() => syncMutation.mutate()} disabled={syncMutation.isPending}>
            <RefreshCwIcon className={`mr-2 h-4 w-4 ${syncMutation.isPending ? 'animate-spin' : ''}`} />
            Sync All Channels
          </Button>
          <Dialog open={connectOpen} onOpenChange={setConnectOpen}>
          <DialogTrigger nativeButton={true} render={<Button><Plug className="mr-2 h-4 w-4" /> Connect Channel</Button>} />
            <DialogContent>
              <DialogHeader><DialogTitle>Connect OTA Channel</DialogTitle></DialogHeader>
              <div className="space-y-4 mt-2">
                <div>
                  <Label>Channel</Label>
                  <Select onValueChange={v => setForm(f => ({ ...f, ota_channel_id: v as string }))}>
                    <SelectTrigger className="mt-1"><SelectValue placeholder="Select OTA platform..." /></SelectTrigger>
                    <SelectContent>
                      {channels.map((c: any) => (
                        <SelectItem key={c.id} value={String(c.id)}>
                          {providerIcons[c.provider] ?? '🌐'} {c.name}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
                <div>
                  <Label>API Key</Label>
                  <Input className="mt-1" type="password" placeholder="Enter API key..."
                    onChange={e => setForm(f => ({ ...f, api_key: e.target.value }))} />
                </div>
                <div>
                  <Label>API Secret (optional)</Label>
                  <Input className="mt-1" type="password" placeholder="Enter API secret..."
                    onChange={e => setForm(f => ({ ...f, api_secret: e.target.value }))} />
                </div>
                <Button className="w-full" onClick={() => connectMutation.mutate(form)}
                  disabled={connectMutation.isPending || !form.ota_channel_id || !form.api_key}>
                  {connectMutation.isPending ? 'Connecting...' : 'Connect Channel'}
                </Button>
              </div>
            </DialogContent>
          </Dialog>
        </div>
      </div>

      {/* Channel Status Cards */}
      <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
        {loadingCon ? [...Array(3)].map((_, i) => (
          <div key={i} className="h-28 animate-pulse bg-muted rounded-xl" />
        )) : connections.map((conn: any, i: number) => (
          <Card key={i}>
            <CardHeader className="pb-2">
              <div className="flex items-center justify-between">
                <CardTitle className="text-base flex items-center gap-2">
                  <span className="text-xl">{providerIcons[conn.provider] ?? '🌐'}</span>
                  {conn.channel}
                </CardTitle>
                <StatusBadge status={conn.status} />
              </div>
            </CardHeader>
            <CardContent className="text-sm text-muted-foreground space-y-1">
              {conn.last_sync_at && <p>Last sync: <span className="text-foreground">{conn.last_sync_at}</span></p>}
              {conn.last_log_status && <p className="flex items-center gap-1">Last: <SyncStatus status={conn.last_log_status} /></p>}
            </CardContent>
          </Card>
        ))}
      </div>

      {/* Tabs: Sync Logs + OTA Reservations */}
      <Tabs defaultValue="logs">
        <TabsList>
          <TabsTrigger value="logs">Sync Logs</TabsTrigger>
          <TabsTrigger value="reservations">OTA Reservations</TabsTrigger>
        </TabsList>

        <TabsContent value="logs">
          <Card className="mt-3">
            <CardHeader>
              <CardTitle>Recent Sync Logs</CardTitle>
              <CardDescription>Track inventory pushes, rate updates, and reservation imports.</CardDescription>
            </CardHeader>
            <CardContent>
              {loadingLogs ? <div className="flex justify-center p-8"><Loader2 className="animate-spin" /></div> : (
                <div className="rounded-md border">
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>Channel</TableHead>
                        <TableHead>Operation</TableHead>
                        <TableHead>Status</TableHead>
                        <TableHead>Time</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {(syncLogs as any[]).length === 0 && (
                        <TableRow><TableCell colSpan={4} className="text-center text-muted-foreground py-8">No sync logs yet.</TableCell></TableRow>
                      )}
                      {(syncLogs as any[]).map((log: any, i: number) => (
                        <TableRow key={i}>
                          <TableCell>{log.ota_channel?.name ?? '—'}</TableCell>
                          <TableCell className="capitalize">{log.operation?.replace(/_/g, ' ')}</TableCell>
                          <TableCell><SyncStatus status={log.status} /></TableCell>
                          <TableCell className="text-muted-foreground text-sm">
                            {new Date(log.created_at).toLocaleString()}
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </div>
              )}
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="reservations">
          <Card className="mt-3">
            <CardHeader>
              <CardTitle>OTA Imported Reservations</CardTitle>
              <CardDescription>Bookings pulled from OTA platforms and imported into the PMS.</CardDescription>
            </CardHeader>
            <CardContent>
              <div className="rounded-md border">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Source</TableHead>
                      <TableHead>Guest</TableHead>
                      <TableHead>Check-in</TableHead>
                      <TableHead>Check-out</TableHead>
                      <TableHead>Room Type</TableHead>
                      <TableHead>Amount</TableHead>
                      <TableHead>Status</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {(otaReservations?.data ?? []).length === 0 && (
                      <TableRow><TableCell colSpan={7} className="text-center text-muted-foreground py-8">No OTA reservations yet.</TableCell></TableRow>
                    )}
                    {(otaReservations?.data ?? []).map((res: any, i: number) => (
                      <TableRow key={i}>
                        <TableCell className="flex items-center gap-1">
                          {providerIcons[res.ota_channel?.provider ?? ''] ?? '🌐'} {res.ota_channel?.name}
                        </TableCell>
                        <TableCell>{res.guest_name}</TableCell>
                        <TableCell>{res.check_in}</TableCell>
                        <TableCell>{res.check_out}</TableCell>
                        <TableCell>{res.room_type}</TableCell>
                        <TableCell>₦{Number(res.total_price).toLocaleString()}</TableCell>
                        <TableCell className="capitalize">{res.status}</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </div>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </div>
  );
}
