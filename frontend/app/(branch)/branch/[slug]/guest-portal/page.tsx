'use client';

import * as React from 'react';
import { useQuery } from '@tanstack/react-query';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { UserCircleIcon, QrCodeIcon, Loader2, WrenchIcon, CheckIcon, PrinterIcon } from 'lucide-react';
import api from '@/lib/api';
import { QRCodeSVG } from 'qrcode.react';
import { useSearchParams } from 'next/navigation';

import { useMutation, useQueryClient } from '@tanstack/react-query';
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger,
} from '@/components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { toast } from 'sonner';

function GuestPortalContent() {
  const queryClient = useQueryClient();
  const [sessionsOpen, setSessionsOpen] = React.useState(false);
  const searchParams = useSearchParams();
  const queryOutletId = searchParams.get('outlet_id');
  const autoOpenQr = searchParams.get('generate_qr') === 'true';

  const [qrOpen, setQrOpen] = React.useState(autoOpenQr);
  const [qrConfig, setQrConfig] = React.useState({ 
    type: 'room', 
    id: '', 
    table_number: '',
    start_range: '',
    end_range: ''
  });

  React.useEffect(() => {
    if (queryOutletId) {
        setQrConfig(prev => ({ ...prev, type: 'outlet', id: queryOutletId }));
    }
  }, [queryOutletId]);

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

  const { data: hotels = [] } = useQuery({
    queryKey: ['hotels'],
    queryFn: async () => {
      const { data } = await api.get('/api/v1/hotels');
      return data.data || [];
    }
  });

  const { data: rooms = [] } = useQuery({
    queryKey: ['rooms-list'],
    queryFn: async () => {
      const { data } = await api.get('/api/v1/pms/rooms');
      return data.data || [];
    }
  });

  const { data: outlets = [] } = useQuery({
    queryKey: ['available-outlets'],
    queryFn: () => api.get('/api/v1/outlets').then(res => res.data),
  });

  const currentHotel = hotels[0]; // Assuming single hotel for now
  const baseUrl = typeof window !== 'undefined' ? `${window.location.protocol}//${window.location.host}` : '';
  
  const generateQrUrl = (type?: string, id?: string, tableNumber?: string) => {
    if (!currentHotel) return '';
    const targetType = type || qrConfig.type;
    const targetId = id || qrConfig.id;
    const targetTable = tableNumber || qrConfig.table_number;

    const params = new URLSearchParams({
        hotel_id: currentHotel.id.toString(),
        context_type: targetType,
        context_id: targetId,
    });
    if (targetType === 'outlet' && targetTable) {
        params.append('table_number', targetTable);
    }
    return `${baseUrl}/guest-portal/start?${params.toString()}`;
  };

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
          <Dialog open={qrOpen} onOpenChange={setQrOpen}>
            <DialogTrigger 
              render={
                <Button variant="outline">
                    <QrCodeIcon className="mr-2 h-4 w-4" />
                    Generate QRs
                </Button>
            } />
            <DialogContent className="sm:max-w-md max-h-[90vh] overflow-y-auto">
              <DialogHeader>
                <DialogTitle>Generate Contextual QR Codes</DialogTitle>
              </DialogHeader>
              <div className="space-y-4 py-4">
                <div className="space-y-2">
                  <Label>QR Category</Label>
                  <Select 
                    value={qrConfig.type} 
                    onValueChange={(val: any) => setQrConfig({ ...qrConfig, type: val as string, id: '' })}
                  >
                    <SelectTrigger>
                        <SelectValue placeholder="Select type" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="room">Room</SelectItem>
                      <SelectItem value="outlet">Outlet (Restaurant/Bar)</SelectItem>
                    </SelectContent>
                  </Select>
                </div>

                <div className="space-y-2">
                  <Label>{qrConfig.type === 'room' ? 'Select Single Room (Optional)' : 'Select Outlet'}</Label>
                  <Select 
                    value={qrConfig.id} 
                    onValueChange={(val: any) => setQrConfig({ ...qrConfig, id: val as string })}
                  >
                    <SelectTrigger>
                        <SelectValue placeholder={qrConfig.type === 'room' ? "Select room or use range below" : `Select ${qrConfig.type}`} />
                    </SelectTrigger>
                    <SelectContent>
                      {qrConfig.type === 'room' ? (
                        rooms.map((room: any) => (
                          <SelectItem key={room.id} value={String(room.id)}>{room.room_number} - {room.roomType?.name}</SelectItem>
                        ))
                      ) : (
                        outlets.map((outlet: any) => (
                          <SelectItem key={outlet.id} value={String(outlet.id)}>{outlet.name}</SelectItem>
                        ))
                      )}
                    </SelectContent>
                  </Select>
                </div>

                {qrConfig.type === 'room' && (
                  <div className="pt-2 border-t space-y-3">
                    <Label className="text-[10px] uppercase font-bold text-muted-foreground tracking-widest">Or Generate Range</Label>
                    <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-1">
                            <Label className="text-xs">From Room #</Label>
                            <Input 
                                placeholder="e.g. 101" 
                                value={qrConfig.start_range}
                                onChange={(e) => setQrConfig({...qrConfig, start_range: e.target.value})}
                            />
                        </div>
                        <div className="space-y-1">
                            <Label className="text-xs">To Room #</Label>
                            <Input 
                                placeholder="e.g. 120" 
                                value={qrConfig.end_range}
                                onChange={(e) => setQrConfig({...qrConfig, end_range: e.target.value})}
                            />
                        </div>
                    </div>
                    {(qrConfig.start_range || qrConfig.end_range) && (
                        <Button 
                            variant="outline" 
                            className="w-full h-8 text-xs gap-2"
                            onClick={() => {
                                // Logic for bulk room print will be handled in the print button below
                                // We just set the config to "batch" mode effectively
                                setQrConfig({...qrConfig, id: ''});
                            }}
                        >
                            <PrinterIcon className="h-3 w-3" />
                            Print Range {qrConfig.start_range} - {qrConfig.end_range}
                        </Button>
                    )}
                  </div>
                )}

                {qrConfig.type === 'outlet' && (
                  <div className="space-y-2">
                    <Label className="flex justify-between">
                        <span>Table Number (Optional)</span>
                        {qrConfig.id && outlets.find((o: any) => String(o.id) === qrConfig.id)?.tables_count > 0 && (
                            <span className="text-[10px] text-emerald-600 font-bold uppercase">
                                Range: 1 - {outlets.find((o: any) => String(o.id) === qrConfig.id).tables_count}
                            </span>
                        )}
                    </Label>
                    <Input 
                      placeholder="e.g. 5" 
                      value={qrConfig.table_number}
                      onChange={(e) => setQrConfig({ ...qrConfig, table_number: e.target.value })}
                    />
                  </div>
                )}

                {(qrConfig.id || (qrConfig.type === 'room' && qrConfig.start_range && qrConfig.end_range)) && (
                  <div className="flex flex-col items-center justify-center p-6 bg-white rounded-lg border space-y-4">
                    {qrConfig.id && (
                        <div id="qr-printable-area" className="flex flex-col items-center p-4 bg-white">
                            <QRCodeSVG 
                            value={generateQrUrl()} 
                            size={200}
                            level="H"
                            includeMargin={true}
                            />
                            <div className="mt-2 text-[12px] font-bold text-black text-center">
                                {qrConfig.type === 'room' ? `ROOM ${rooms.find((r: any) => String(r.id) === qrConfig.id)?.room_number}` : `${outlets.find((o: any) => String(o.id) === qrConfig.id)?.name}`}
                                {qrConfig.table_number && ` - TABLE ${qrConfig.table_number}`}
                            </div>
                        </div>
                    )}
                    
                    <div className="flex flex-col w-full gap-2">
                        {qrConfig.id && (
                            <Button 
                            className="w-full" 
                            variant="secondary"
                            onClick={() => {
                                const printContent = document.getElementById('qr-printable-area');
                                if (printContent) {
                                    const win = window.open('', '', 'width=600,height=600');
                                    if (win) {
                                        win.document.write('<html><head><title>Print QR</title><style>body{display:flex;flex-direction:column;align-items:center;justify-content:center;height:100vh;margin:0;} #print-me{text-align:center;}</style></head><body>');
                                        win.document.write('<div id="print-me">' + printContent.innerHTML + '</div>');
                                        win.document.write('</body></html>');
                                        win.document.close();
                                        win.focus();
                                        setTimeout(() => { win.print(); win.close(); }, 500);
                                    }
                                }
                            }}
                            >
                                <PrinterIcon className="mr-2 h-4 w-4" />
                                Print Current QR
                            </Button>
                        )}
                        
                        {qrConfig.type === 'outlet' && qrConfig.id && outlets.find((o: any) => String(o.id) === qrConfig.id)?.tables_count > 0 && (
                            <Button 
                                className="w-full"
                                variant="outline"
                                onClick={() => {
                                    const outlet = outlets.find((o: any) => String(o.id) === qrConfig.id);
                                    if (outlet) {
                                        const win = window.open('', '_blank');
                                        if (win) {
                                            win.document.write(`<html><head><title>Bulk QRs - ${outlet.name}</title><style>
                                                body { font-family: sans-serif; margin: 0; padding: 20px; }
                                                .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #eee; padding-bottom: 10px; }
                                                .grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
                                                .qr-card { border: 1px solid #ddd; border-radius: 8px; padding: 15px; text-align: center; page-break-inside: avoid; }
                                                .qr-placeholder { background: #f9f9f9; border: 1px dashed #ccc; width: 150px; height: 150px; margin: 0 auto 10px; display: flex; align-items: center; justify-content: center; font-size: 10px; color: #999; }
                                                .label { font-weight: bold; font-size: 14px; margin-top: 5px; }
                                                .sub-label { font-size: 10px; color: #666; margin-top: 2px; }
                                                @media print { .no-print { display: none; } }
                                            </style></head><body>`);
                                            win.document.write(`<div class="header"><h1>${outlet.name} - Table QR Codes</h1><button onclick="window.print()" class="no-print">Print Now</button></div>`);
                                            win.document.write('<div class="grid">');
                                            
                                            for(let i=1; i<=outlet.tables_count; i++) {
                                                const url = generateQrUrl('outlet', String(outlet.id), String(i));
                                                // We'll use a public API to generate the image for the print window
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
                                    }
                                }}
                            >
                                <PrinterIcon className="mr-2 h-4 w-4" />
                                Print All {outlets.find((o: any) => String(o.id) === qrConfig.id).tables_count} Tables
                            </Button>
                        )}

                        {qrConfig.type === 'room' && qrConfig.start_range && qrConfig.end_range && (
                            <Button 
                                className="w-full"
                                variant="outline"
                                onClick={() => {
                                    const start = parseInt(qrConfig.start_range);
                                    const end = parseInt(qrConfig.end_range);
                                    if (isNaN(start) || isNaN(end) || start > end) {
                                        toast.error("Invalid range");
                                        return;
                                    }

                                    const win = window.open('', '_blank');
                                    if (win) {
                                        win.document.write(`<html><head><title>Room QRs - Range ${start}-${end}</title><style>
                                            body { font-family: sans-serif; margin: 0; padding: 20px; }
                                            .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #eee; padding-bottom: 10px; }
                                            .grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
                                            .qr-card { border: 1px solid #ddd; border-radius: 8px; padding: 15px; text-align: center; page-break-inside: avoid; }
                                            .label { font-weight: bold; font-size: 14px; margin-top: 5px; }
                                            .sub-label { font-size: 10px; color: #666; margin-top: 2px; }
                                            @media print { .no-print { display: none; } }
                                        </style></head><body>`);
                                        win.document.write(`<div class="header"><h1>Room QR Codes</h1><p>Range: ${start} - ${end}</p><button onclick="window.print()" class="no-print">Print Now</button></div>`);
                                        win.document.write('<div class="grid">');
                                        
                                        // We need to map room numbers to IDs if we want context-aware QRs
                                        // But if they just want specific range, we can try to find rooms that match or just generate placeholder rooms?
                                        // Actually, context_id for rooms is the room ID.
                                        // Let's filter existing rooms that fall within the range.
                                        const roomsInRange = rooms.filter((r: any) => {
                                            const num = parseInt(r.room_number);
                                            return !isNaN(num) && num >= start && num <= end;
                                        });

                                        if (roomsInRange.length === 0) {
                                            win.document.write('<p>No existing rooms found in this numeric range.</p>');
                                        }

                                        roomsInRange.forEach((room: any) => {
                                            const url = generateQrUrl('room', String(room.id));
                                            const qrImgUrl = `https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=${encodeURIComponent(url)}`;
                                            win.document.write(`<div class="qr-card">
                                                <img src="${qrImgUrl}" width="150" height="150" />
                                                <div class="label">ROOM ${room.room_number}</div>
                                                <div class="sub-label">${room.roomType?.name || ''}</div>
                                            </div>`);
                                        });
                                        win.document.write('</div></body></html>');
                                        win.document.close();
                                    }
                                }}
                            >
                                <PrinterIcon className="mr-2 h-4 w-4" />
                                Print Range Batch
                            </Button>
                        )}
                    </div>
                  </div>
                )}
              </div>
            </DialogContent>
          </Dialog>
          
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
                          <Select onValueChange={(userId: string | null) => userId && assignRequest.mutate({ requestId: req.id, userId })}>
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

export default function GuestPortalPage() {
  return (
    <React.Suspense fallback={<div className="flex justify-center p-8"><Loader2 className="animate-spin text-muted-foreground" /></div>}>
      <GuestPortalContent />
    </React.Suspense>
  );
}
