'use client';

import * as React from 'react';
import { useQuery } from '@tanstack/react-query';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { UserCircleIcon, QrCodeIcon, Loader2, WrenchIcon, CheckIcon } from 'lucide-react';
import api from '@/lib/api';

import { useMutation, useQueryClient } from '@tanstack/react-query';
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger,
} from '@/components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { toast } from 'sonner';

export default function GuestPortalPage() {
  const queryClient = useQueryClient();
  const [sessionsOpen, setSessionsOpen] = React.useState(false);

  const { data: requests, isLoading } = useQuery({
    queryKey: ['guest-requests'],
    queryFn: async () => {
      try {
        const { data } = await api.get('/api/v1/guest-requests');
        return data; // api route returned raw array from GuestRequestController
      } catch (error) {
        return [];
      }
    },
  });

  const { data: activeSessions = [] } = useQuery({
    queryKey: ['active-sessions'],
    queryFn: async () => {
      try {
        const { data } = await api.get('/api/v1/guest-requests/active-sessions');
        return data.data ?? [];
      } catch {
        return [];
      }
    },
    enabled: sessionsOpen,
  });

  const { data: staff = [] } = useQuery({
    queryKey: ['staff'],
    queryFn: async () => {
      try {
        const { data } = await api.get('/api/v1/users');
        return data.data ?? [];
      } catch {
        return [];
      }
    },
  });

  const assignRequest = useMutation({
    mutationFn: ({ requestId, userId }: { requestId: number, userId: string }) => 
      api.post(`/api/v1/guest-requests/${requestId}/assign`, { assigned_user_id: userId }),
    onSuccess: () => {
      toast.success('Request assigned successfully');
      queryClient.invalidateQueries({ queryKey: ['guest-requests'] });
    },
  });

  const completeRequest = useMutation({
    mutationFn: (requestId: number) => api.post(`/api/v1/guest-requests/${requestId}/complete`),
    onSuccess: () => {
      toast.success('Request completed');
      queryClient.invalidateQueries({ queryKey: ['guest-requests'] });
    },
  });

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h2 className="text-3xl font-bold tracking-tight">Guest Portal Actions</h2>
          <p className="text-muted-foreground">Monitor and fulfill live guest requests generated from the mobile WebApp.</p>
        </div>
        <div className="flex gap-2">
          <Button variant="outline">
            <QrCodeIcon className="mr-2 h-4 w-4" />
            Generate Room QRs
          </Button>
          
          <Dialog open={sessionsOpen} onOpenChange={setSessionsOpen}>
            <DialogTrigger
              nativeButton={true}
              render={
                <Button>
                  <UserCircleIcon className="mr-2 h-4 w-4" />
                  Active Sessions
                </Button>
              }
            />
            <DialogContent className="sm:max-w-2xl max-h-[80vh] overflow-y-auto">
              <DialogHeader>
                <DialogTitle>Active Guest Sessions</DialogTitle>
              </DialogHeader>
              <div className="py-4">
                {activeSessions.length === 0 ? (
                  <p className="text-center text-muted-foreground py-8">No active guest sessions found.</p>
                ) : (
                  <div className="space-y-4">
                    {activeSessions.map((session: any) => (
                      <div key={session.id} className="flex items-center justify-between p-3 border rounded-lg bg-muted/30">
                        <div className="flex items-center gap-3">
                          <div className="h-10 w-10 rounded-full bg-primary/10 flex items-center justify-center">
                            <UserCircleIcon className="h-6 w-6 text-primary" />
                          </div>
                          <div>
                            <div className="font-semibold">{session.guest?.name || 'Anonymous Guest'}</div>
                            <div className="text-xs text-muted-foreground">
                              Room {session.room?.room_number || session.room_id} · {session.context_type.toUpperCase()}
                            </div>
                          </div>
                        </div>
                        <div className="text-right">
                          <div className="text-xs font-mono">{session.session_token.substring(0, 8)}...</div>
                          <div className="text-[10px] text-muted-foreground">Expires {new Date(session.expires_at).toLocaleTimeString()}</div>
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            </DialogContent>
          </Dialog>
        </div>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Service Request Queue</CardTitle>
          <CardDescription>Direct requests from guests checking in via their mobile portal.</CardDescription>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="flex justify-center p-8"><Loader2 className="animate-spin text-muted-foreground" /></div>
          ) : (
            <div className="rounded-md border">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Room ID/Number</TableHead>
                    <TableHead>Type</TableHead>
                    <TableHead>Description</TableHead>
                    <TableHead>Requested</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead className="text-right">Actions</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {requests?.map((req: any) => (
                    <TableRow key={req.id}>
                      <TableCell className="font-bold">Room {req.room?.room_number || req.room_id}</TableCell>
                      <TableCell className="capitalize">{req.request_type}</TableCell>
                      <TableCell className="max-w-[250px] truncate" title={req.description}>
                        {req.description || 'No additional details'}
                      </TableCell>
                      <TableCell>{new Date(req.created_at).toLocaleTimeString()}</TableCell>
                      <TableCell>
                        <Badge variant={
                          req.status === 'pending' ? 'destructive' : 
                          req.status === 'assigned' ? 'outline' : 'secondary'
                        }>
                          {req.status.toUpperCase()}
                        </Badge>
                      </TableCell>
                      <TableCell className="text-right space-x-2">
                        {req.status === 'pending' && (
                          <Select onValueChange={(userId) => assignRequest.mutate({ requestId: req.id, userId })}>
                            <SelectTrigger className="h-8 w-[120px] inline-flex">
                              <SelectValue placeholder="Assign Staff" />
                            </SelectTrigger>
                            <SelectContent>
                              {staff.map((s: any) => (
                                <SelectItem key={s.id} value={s.id.toString()}>{s.name}</SelectItem>
                              ))}
                            </SelectContent>
                          </Select>
                        )}
                        {req.status !== 'completed' && (
                          <Button 
                            size="sm" 
                            variant="default"
                            onClick={() => completeRequest.mutate(req.id)}
                            disabled={completeRequest.isPending}
                          >
                            <CheckIcon className="mr-2 h-3 w-3" /> 
                            {completeRequest.isPending ? '...' : 'Complete'}
                          </Button>
                        )}
                      </TableCell>
                    </TableRow>
                  ))}
                  {(!requests || requests.length === 0) && (
                    <TableRow>
                      <TableCell colSpan={6} className="text-center py-6 text-muted-foreground">
                        No active service requests.
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
