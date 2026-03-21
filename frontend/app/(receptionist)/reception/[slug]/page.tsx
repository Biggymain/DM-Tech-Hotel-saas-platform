'use client';

import * as React from 'react';
import { useQuery } from '@tanstack/react-query';
import { useParams } from 'next/navigation';
import { format, addDays, startOfDay, parseISO, eachDayOfInterval, subDays, isToday, subHours, setHours, setMinutes, differenceInCalendarDays, isFuture, isPast, isValid } from 'date-fns';
import { 
  ChevronLeft, 
  ChevronRight, 
  BedDouble, 
  Loader2,
  Filter,
  Info,
  AlertTriangle,
  List,
  CalendarDays,
  LogIn,
  LogOut,
  Clock,
  CheckCircle2,
  XCircle,
  User
} from 'lucide-react';
import { cn } from '@/lib/utils';
import api from '@/lib/api';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { 
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter 
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { toast } from 'sonner';
import { useMutation, useQueryClient } from '@tanstack/react-query';

export default function ReceptionDashboard() {
  const params = useParams();
  const branchSlug = params.slug as string;
  const queryClient = useQueryClient();
  const [activeTab, setActiveTab] = React.useState('calendar');
  const [statusFilter, setStatusFilter] = React.useState<string>('all');
  const [startDate, setStartDate] = React.useState(startOfDay(new Date()));
  const [selectedRoom, setSelectedRoom] = React.useState<any>(null);
  const [selectedRes, setSelectedRes] = React.useState<any>(null);
  const [checkInOpen, setCheckInOpen] = React.useState(false);
  const [summaryOpen, setSummaryOpen] = React.useState(false);
  const [folioOpen, setFolioOpen] = React.useState(false);
  
  const [guestForm, setGuestForm] = React.useState({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    days: '1',
    check_out_date: format(addDays(new Date(), 1), 'yyyy-MM-dd'),
  });

  const daysToShow = 7;
  
  const days = React.useMemo(() => {
    return eachDayOfInterval({
      start: startDate,
      end: addDays(startDate, daysToShow - 1),
    });
  }, [startDate]);

  const { data: rooms = [], isLoading: loadingRooms } = useQuery({
    queryKey: ['reception-rooms', branchSlug],
    queryFn: async () => {
      const { data } = await api.get('/api/v1/pms/rooms');
      return data.data || [];
    }
  });

  const { data: reservations = [], isLoading: loadingReservations } = useQuery({
    queryKey: ['reception-reservations', branchSlug],
    queryFn: async () => {
      const { data } = await api.get('/api/v1/pms/reservations');
      return data.data || [];
    },
    refetchInterval: 30_000, // auto-refresh every 30 seconds
    refetchIntervalInBackground: true,
  });

  const { data: folio } = useQuery({
    queryKey: ['folio', selectedRes?.id],
    queryFn: async () => {
      if (!selectedRes?.id) return null;
      const { data } = await api.get(`/api/v1/pms/reservations/${selectedRes.id}/folio`);
      return data.data;
    },
    enabled: !!selectedRes?.id && folioOpen
  });

  const checkInMutation = useMutation({
    mutationFn: (data: any) => api.post('/api/v1/pms/reservations', data),
    onSuccess: async (res: any) => {
      try {
        // After creating reservation, automatically check it in
        const resId = res.data.data.id;
        await api.post(`/api/v1/pms/reservations/${resId}/check-in`);
        
        toast.success('Guest checked in successfully!');
        queryClient.invalidateQueries({ queryKey: ['reception-reservations'] });
        setCheckInOpen(false);
      } catch (err: any) {
        toast.error(err?.response?.data?.message || 'Reservation created but check-in failed.');
        // Still invalidating to show the reservation at least
        queryClient.invalidateQueries({ queryKey: ['reception-reservations'] });
      }
    },
    onError: (err: any) => {
      toast.error(err?.response?.data?.message || 'Failed to check in guest.');
    }
  });

  const checkOutMutation = useMutation({
    mutationFn: (reservationId: number) => api.post(`/api/v1/pms/reservations/${reservationId}/check-out`),
    onSuccess: () => {
      toast.success('Guest checked out successfully!');
      queryClient.invalidateQueries({ queryKey: ['reception-reservations'] });
      setSummaryOpen(false);
    },
    onError: (err: any) => {
      toast.error(err?.response?.data?.message || 'Failed to check out guest.');
    }
  });

  const confirmCheckInMutation = useMutation({
    mutationFn: (reservationId: number) => api.post(`/api/v1/pms/reservations/${reservationId}/check-in`),
    onSuccess: () => {
      toast.success('Guest checked in successfully!');
      queryClient.invalidateQueries({ queryKey: ['reception-reservations'] });
    },
    onError: (err: any) => {
      toast.error(err?.response?.data?.message || 'Failed to check in guest.');
    }
  });

  const handleRoomClick = (room: any, status: string, data: any) => {
    setSelectedRoom(room);
    setSelectedRes(data);
    if (status === 'vacant') {
      setCheckInOpen(true);
    } else {
      setSummaryOpen(true);
    }
  };

  const getStatusForRoomOnDate = (roomId: number, date: Date) => {
    const dayStart = startOfDay(date);
    
    // Check for reservations overlapping this date
    const dayReservations = reservations.filter((res: any) => {
      // Check if this room is part of the reservation's rooms array
      if (!res.rooms || !res.rooms.some((r: any) => r.id === roomId)) return false;

      // EXCLUSION: If the guest has already checked out, the room is vacant for the grid
      if (res.status === 'checked_out') return false;

      // Extract only the YYYY-MM-DD part to avoid timezone shifts
      const checkInStr = res.check_in_date.split('T')[0];
      const checkOutStr = res.check_out_date.split('T')[0];
      
      const checkIn = startOfDay(parseISO(checkInStr));
      const checkOut = startOfDay(parseISO(checkOutStr));
      
      return dayStart >= checkIn && dayStart < checkOut;
    });

    if (dayReservations.length === 0) return { status: 'vacant', data: null };
    
    // Sort to prioritize 'checked_in' status
    const activeRes = dayReservations.find((res: any) => res.status === 'checked_in') || dayReservations[0];
    
    return {
      status: activeRes.status === 'checked_in' ? 'lodged' : 'reserved',
      data: activeRes
    };
  };

  const nextWeek = () => setStartDate(prev => addDays(prev, 7));
  const prevWeek = () => setStartDate(prev => subDays(prev, 7));
  const today = () => setStartDate(startOfDay(new Date()));

  if (loadingRooms || loadingReservations) {
    return (
      <div className="flex h-[600px] flex-col items-center justify-center gap-4">
        <Loader2 className="h-10 w-10 animate-spin text-primary" />
        <p className="text-muted-foreground animate-pulse font-medium">Syncing room availability...</p>
      </div>
    );
  }

  // ── Computed stats for the Reservations tab ────────────────────────────────
  const todayStr = format(new Date(), 'yyyy-MM-dd');
  const todayArrivals = reservations.filter((r: any) => r.check_in_date?.split('T')[0] === todayStr && r.status === 'confirmed').length;
  const inHouse = reservations.filter((r: any) => r.status === 'checked_in').length;
  const todayDepartures = reservations.filter((r: any) => r.check_out_date?.split('T')[0] === todayStr && r.status === 'checked_in').length;

  const filteredReservations = statusFilter === 'all'
    ? reservations
    : reservations.filter((r: any) => r.status === statusFilter);

  const statusConfig: Record<string, { label: string; color: string; icon: any }> = {
    confirmed:    { label: 'Confirmed',   color: 'bg-blue-500/10 text-blue-700 border-blue-500/20',   icon: Clock },
    checked_in:   { label: 'Checked In',  color: 'bg-emerald-500/10 text-emerald-700 border-emerald-500/20', icon: CheckCircle2 },
    checked_out:  { label: 'Checked Out', color: 'bg-slate-400/10 text-slate-600 border-slate-400/20', icon: XCircle },
    extended:     { label: 'Extended',    color: 'bg-purple-500/10 text-purple-700 border-purple-500/20', icon: Clock },
    cancelled:    { label: 'Cancelled',   color: 'bg-rose-500/10 text-rose-700 border-rose-500/20',   icon: XCircle },
  };

  const isEndingSoon = (checkOutDate: string) => {
    const checkOut = parseISO(checkOutDate);
    const tomorrow = addDays(startOfDay(new Date()), 1);
    return isToday(checkOut) || (startOfDay(checkOut).getTime() === tomorrow.getTime());
  };

  return (
    <div className="space-y-6 animate-in fade-in duration-500">
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h1 className="text-3xl font-bold tracking-tight flex items-center gap-3">
            Reception Desk
            <Badge variant="outline" className="text-[10px] font-bold uppercase tracking-widest bg-primary/5 text-primary border-primary/20">
              Live View
            </Badge>
          </h1>
          <p className="text-muted-foreground mt-1">
            Visual room planning and occupancy management.
          </p>
        </div>
        <div className="flex items-center gap-2">
          <Button variant="outline" size="sm" onClick={today}>Today</Button>
          <div className="flex items-center border rounded-lg bg-card shadow-sm p-1">
            <Button variant="ghost" size="icon" className="h-8 w-8" onClick={prevWeek}>
              <ChevronLeft className="h-4 w-4" />
            </Button>
            <div className="px-3 py-1 text-sm font-semibold min-w-[180px] text-center">
              {format(days[0], 'MMM d')} - {format(days[days.length - 1], 'MMM d, yyyy')}
            </div>
            <Button variant="ghost" size="icon" className="h-8 w-8" onClick={nextWeek}>
              <ChevronRight className="h-4 w-4" />
            </Button>
          </div>
          <Button variant="outline" size="icon" className="h-10 w-10"><Filter className="h-4 w-4" /></Button>
        </div>
      </div>

      {/* ── TABS WRAPPER ──────────────────────────────────────────────── */}
      <Tabs value={activeTab} onValueChange={setActiveTab}>
        <TabsList className="mb-4">
          <TabsTrigger value="calendar" className="gap-2">
            <CalendarDays className="h-4 w-4" /> Room Grid
          </TabsTrigger>
          <TabsTrigger value="reservations" className="gap-2">
            <List className="h-4 w-4" />
            Reservations
            {reservations.filter((r: any) => r.status === 'confirmed').length > 0 && (
              <span className="ml-1 h-5 min-w-5 rounded-full bg-blue-500 text-white text-[10px] font-black flex items-center justify-center px-1">
                {reservations.filter((r: any) => r.status === 'confirmed').length}
              </span>
            )}
          </TabsTrigger>
        </TabsList>

        {/* ── CALENDAR TAB (existing content) ──────────────────────────── */}
        <TabsContent value="calendar" className="mt-0">

      <div className="grid grid-cols-1 lg:grid-cols-4 gap-6">
        <Card className="lg:col-span-1 bg-gradient-to-b from-card to-muted/20 border-primary/10">
          <CardHeader>
            <CardTitle className="text-sm font-bold uppercase tracking-widest flex items-center gap-2">
              <Info className="h-4 w-4 text-primary" />
              Legend
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="flex items-center justify-between p-3 rounded-xl bg-emerald-500/10 border border-emerald-500/20">
              <div className="flex items-center gap-3">
                <div className="h-4 w-4 rounded-full bg-emerald-500 shadow-sm shadow-emerald-500/50" />
                <span className="text-sm font-semibold text-emerald-700">Vacant</span>
              </div>
              <span className="text-[10px] font-bold text-emerald-600/70 border border-emerald-600/20 px-1.5 py-0.5 rounded-full bg-emerald-50">FREE</span>
            </div>
            <div className="flex items-center justify-between p-3 rounded-xl bg-blue-500/10 border border-blue-500/20">
              <div className="flex items-center gap-3">
                <div className="h-4 w-4 rounded-full bg-blue-500 shadow-sm shadow-blue-500/50" />
                <span className="text-sm font-semibold text-blue-700">Reserved</span>
              </div>
              <span className="text-[10px] font-bold text-blue-600/70 border border-blue-600/20 px-1.5 py-0.5 rounded-full bg-blue-50">BOOKED</span>
            </div>
            <div className="flex items-center justify-between p-3 rounded-xl bg-rose-500/10 border border-rose-500/20">
              <div className="flex items-center gap-3">
                <div className="h-4 w-4 rounded-full bg-rose-500 shadow-sm shadow-rose-500/50" />
                <span className="text-sm font-semibold text-rose-700">Lodged In</span>
              </div>
              <span className="text-[10px] font-bold text-rose-600/70 border border-rose-600/20 px-1.5 py-0.5 rounded-full bg-rose-50">OCCUPIED</span>
            </div>
            <div className="flex items-center justify-between p-3 rounded-xl bg-amber-500/10 border border-amber-500/20">
              <div className="flex items-center gap-3">
                <div className="h-4 w-4 rounded-full bg-amber-500 shadow-sm shadow-amber-500/50" />
                <span className="text-sm font-semibold text-amber-700">Due Soon</span>
              </div>
              <span className="text-[10px] font-bold text-amber-600/70 border border-amber-600/20 px-1.5 py-0.5 rounded-full bg-amber-50">EXITING</span>
            </div>
            
            <div className="mt-6 pt-6 border-t border-border/50">
              <h4 className="text-[10px] font-black uppercase tracking-[0.2em] text-muted-foreground mb-4">Quick Stats</h4>
              <div className="grid grid-cols-2 gap-4">
                <div className="bg-card p-3 rounded-xl border border-border/40 shadow-sm">
                  <p className="text-[10px] font-bold text-muted-foreground uppercase text-center mb-1">Arrivals</p>
                  <p className="text-2xl font-black text-center text-primary">0</p>
                </div>
                <div className="bg-card p-3 rounded-xl border border-border/40 shadow-sm">
                  <p className="text-[10px] font-bold text-muted-foreground uppercase text-center mb-1">Stay Overs</p>
                  <p className="text-2xl font-black text-center text-primary">0</p>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>

        <Card className="lg:col-span-3 overflow-hidden shadow-2xl border-primary/5">
          <CardContent className="p-0">
            <div className="overflow-x-auto">
              <div className="min-w-[800px]">
                {/* Header row with days */}
                <div className="flex border-b bg-muted/30">
                  <div className="w-48 p-4 border-r shrink-0 flex items-center justify-center font-bold text-xs uppercase tracking-widest text-muted-foreground">
                    Rooms
                  </div>
                  {days.map((day) => (
                    <div key={day.toISOString()} className={cn(
                      "flex-1 p-3 text-center border-r last:border-r-0",
                      format(day, 'yyyy-MM-dd') === format(new Date(), 'yyyy-MM-dd') && "bg-primary/5"
                    )}>
                      <div className="text-[10px] font-black text-muted-foreground uppercase tracking-tighter">
                        {format(day, 'EEEE')}
                      </div>
                      <div className="text-lg font-black tracking-tighter text-foreground">
                        {format(day, 'MMM d')}
                      </div>
                    </div>
                  ))}
                </div>

                {/* Room rows */}
                <div className="divide-y">
                  {rooms.length === 0 ? (
                    <div className="p-20 text-center flex flex-col items-center gap-3">
                      <BedDouble className="h-12 w-12 text-muted-foreground/30" />
                      <p className="text-sm font-medium text-muted-foreground italic">No rooms configured for this branch.</p>
                    </div>
                  ) : rooms.map((room: any) => (
                    <div key={room.id} className="flex hover:bg-muted/10 transition-colors">
                      <div className="w-48 p-3 border-r shrink-0 flex items-center bg-muted/5">
                        <div className="flex flex-col justify-center w-full bg-card/60 border border-primary/10 rounded-xl p-3 shadow-sm hover:border-primary/30 transition-colors">
                          <div className="font-black tracking-tight text-xl leading-none text-primary mb-1">{room.room_number}</div>
                          <div className="text-[10px] font-bold text-muted-foreground uppercase truncate opacity-80">
                            {room.room_type?.name || 'Standard'}
                          </div>
                        </div>
                      </div>
                      
                      {days.map((day) => {
                        const { status, data } = getStatusForRoomOnDate(room.id, day);
                        return (
                          <div key={day.toISOString()} className="flex-1 border-r last:border-r-0 p-1.5 min-h-[70px]">
                            <div 
                              className={cn(
                                "w-full h-full rounded-lg transition-all duration-300 flex items-center justify-center p-1 cursor-pointer hover:scale-[1.02] active:scale-95 group relative overflow-hidden",
                                status === 'vacant' && "bg-emerald-500/10 hover:bg-emerald-500/20 border border-emerald-500/20",
                                status === 'reserved' && "bg-blue-500 text-white shadow-lg shadow-blue-500/30",
                                status === 'lodged' && "bg-rose-500 text-white shadow-lg shadow-rose-500/30"
                              )}
                              title={status === 'vacant' ? 'Vacant' : `${data.guest?.first_name} ${data.guest?.last_name} (${data.status.replace('_', ' ').toUpperCase()})`}
                              onClick={() => handleRoomClick(room, status, data)}
                            >
                              {status === 'vacant' && (
                                 <div className="flex flex-col items-center">
                                    <div className="h-1.5 w-1.5 rounded-full bg-emerald-500 mb-1" />
                                    <span className="text-[10px] font-black text-emerald-600/40 uppercase">Avail</span>
                                 </div>
                              )}
                              {status === 'reserved' && (
                                <div className="text-center">
                                   <div className="text-[9px] font-bold uppercase truncate px-1">{data.guest?.last_name || 'Reserved'}</div>
                                   <div className="text-[8px] opacity-70 font-black tracking-widest">{data.confirmation_number}</div>
                                </div>
                              )}
                              {status === 'lodged' && (
                                <div className="text-center relative">
                                   <div className="text-[9px] font-bold uppercase truncate px-1">{data.guest?.last_name || 'In-House'}</div>
                                   <div className="text-[8px] opacity-70 font-black tracking-widest">OCCUPIED</div>
                                   
                                   {/* Recovery Alert Indicator */}
                                   {(() => {
                                      const checkOut = parseISO(data.check_out_date);
                                      const limit = subHours(setHours(setMinutes(checkOut, 0), 12), 5); // 7 AM
                                      if (isToday(checkOut) && new Date() > limit && parseFloat(data.folios?.[0]?.balance || 0) > 0) {
                                          return (
                                              <div className="absolute -top-1 -right-1 h-2 w-2 rounded-full bg-white animate-ping shadow-sm" title="Payment Recovery Required" />
                                          );
                                      }
                                      return null;
                                   })()}
                                </div>
                              )}
                            </div>
                          </div>
                        );
                      })}
                    </div>
                  ))}
                </div>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
        </TabsContent>

        {/* ── RESERVATIONS TAB ─────────────────────────────────────────── */}
        <TabsContent value="reservations" className="mt-0">

          {/* Stats Row */}
          <div className="grid grid-cols-3 gap-4 mb-6">
            <Card className="bg-gradient-to-br from-blue-500/10 to-blue-500/5 border-blue-500/20">
              <CardContent className="p-4 flex items-center gap-3">
                <div className="h-10 w-10 rounded-xl bg-blue-500/10 flex items-center justify-center">
                  <LogIn className="h-5 w-5 text-blue-600" />
                </div>
                <div>
                  <p className="text-[10px] font-black uppercase tracking-widest text-blue-600/70">Today's Arrivals</p>
                  <p className="text-3xl font-black text-blue-700">{todayArrivals}</p>
                </div>
              </CardContent>
            </Card>
            <Card className="bg-gradient-to-br from-emerald-500/10 to-emerald-500/5 border-emerald-500/20">
              <CardContent className="p-4 flex items-center gap-3">
                <div className="h-10 w-10 rounded-xl bg-emerald-500/10 flex items-center justify-center">
                  <User className="h-5 w-5 text-emerald-600" />
                </div>
                <div>
                  <p className="text-[10px] font-black uppercase tracking-widest text-emerald-600/70">In-House Guests</p>
                  <p className="text-3xl font-black text-emerald-700">{inHouse}</p>
                </div>
              </CardContent>
            </Card>
            <Card className="bg-gradient-to-br from-amber-500/10 to-amber-500/5 border-amber-500/20">
              <CardContent className="p-4 flex items-center gap-3">
                <div className="h-10 w-10 rounded-xl bg-amber-500/10 flex items-center justify-center">
                  <LogOut className="h-5 w-5 text-amber-600" />
                </div>
                <div>
                  <p className="text-[10px] font-black uppercase tracking-widest text-amber-600/70">Today's Departures</p>
                  <p className="text-3xl font-black text-amber-700">{todayDepartures}</p>
                </div>
              </CardContent>
            </Card>
          </div>

          {/* Filter Row */}
          <div className="flex items-center gap-2 mb-4 flex-wrap">
            {(['all', 'confirmed', 'checked_in', 'checked_out'] as const).map(s => (
              <Button
                key={s}
                size="sm"
                variant={statusFilter === s ? 'default' : 'outline'}
                onClick={() => setStatusFilter(s)}
                className="rounded-full capitalize text-xs h-8"
              >
                {s === 'all' ? 'All' : s.replace('_', ' ')}
                {s !== 'all' && (
                  <span className="ml-1.5 text-[9px] font-black bg-white/20 px-1.5 py-0.5 rounded-full">
                    {reservations.filter((r: any) => r.status === s).length}
                  </span>
                )}
              </Button>
            ))}
          </div>

          {/* Reservations List */}
          <Card className="shadow-lg border-primary/5 overflow-hidden">
            <CardContent className="p-0">
              {loadingReservations ? (
                <div className="flex items-center justify-center py-20">
                  <Loader2 className="h-8 w-8 animate-spin text-primary" />
                </div>
              ) : filteredReservations.length === 0 ? (
                <div className="flex flex-col items-center justify-center py-20 gap-3">
                  <BedDouble className="h-12 w-12 text-muted-foreground/20" />
                  <p className="text-muted-foreground italic text-sm">No reservations found.</p>
                </div>
              ) : (
                <div className="divide-y">
                  {/* Table Header */}
                  <div className="grid grid-cols-12 px-4 py-3 bg-muted/40 text-[10px] font-black uppercase tracking-widest text-muted-foreground">
                    <div className="col-span-1">#</div>
                    <div className="col-span-3">Guest</div>
                    <div className="col-span-2">Rooms</div>
                    <div className="col-span-2">Check-in</div>
                    <div className="col-span-2">Check-out</div>
                    <div className="col-span-1">Status</div>
                    <div className="col-span-1 text-right">Actions</div>
                  </div>

                  {filteredReservations.map((res: any) => {
                    const conf = statusConfig[res.status] || statusConfig.confirmed;
                    const StatusIcon = conf.icon;
                    const guestName = res.guest
                      ? `${res.guest.first_name} ${res.guest.last_name}`
                      : 'Guest';
                    const roomNumbers = res.rooms?.map((r: any) => r.room_number).join(', ') || '-';

                    return (
                      <div
                        key={res.id}
                        className={cn(
                          'grid grid-cols-12 px-4 py-4 items-center hover:bg-muted/20 transition-colors group',
                          res.status === 'checked_out' && 'opacity-60'
                        )}
                      >
                        {/* Reservation # */}
                        <div className="col-span-1">
                          <span className="text-[10px] font-black text-muted-foreground font-mono">{res.reservation_number?.slice(-6) || res.id}</span>
                        </div>

                        {/* Guest */}
                        <div className="col-span-3 flex items-center gap-2">
                          <div className="h-8 w-8 rounded-full bg-primary/10 flex items-center justify-center text-primary font-black text-xs shrink-0">
                            {guestName.charAt(0)}
                          </div>
                          <div>
                            <p className="text-sm font-bold leading-none">{guestName}</p>
                            <p className="text-[10px] text-muted-foreground mt-0.5">{res.guest?.email || res.source || 'website'}</p>
                          </div>
                        </div>

                        {/* Rooms */}
                        <div className="col-span-2">
                          <span className="text-sm font-mono font-bold">{roomNumbers}</span>
                        </div>

                        {/* Check-in */}
                        <div className="col-span-2">
                          <p className="text-sm font-bold">{res.check_in_date?.split('T')[0]}</p>
                          {res.check_in_date?.split('T')[0] === todayStr && res.status === 'confirmed' && (
                            <span className="text-[9px] font-black uppercase text-blue-600 tracking-wider">TODAY</span>
                          )}
                        </div>

                        {/* Check-out */}
                        <div className="col-span-2">
                          <p className="text-sm font-bold">{res.check_out_date?.split('T')[0]}</p>
                          {isEndingSoon(res.check_out_date) && res.status === 'checked_in' && (
                            <span className="text-[9px] font-black uppercase text-amber-600 tracking-wider flex items-center gap-1">
                              <AlertTriangle className="h-2.5 w-2.5" /> DUE SOON
                            </span>
                          )}
                        </div>

                        {/* Status Badge */}
                        <div className="col-span-1">
                          <div className={cn('inline-flex items-center gap-1 px-2 py-1 rounded-full text-[9px] font-black uppercase tracking-widest border', conf.color)}>
                            <StatusIcon className="h-3 w-3" />
                            {conf.label}
                          </div>
                        </div>

                        {/* Actions */}
                        <div className="col-span-1 flex items-center justify-end gap-1">
                          {res.status === 'confirmed' && (
                            <Button
                              size="sm"
                              className="h-7 text-[10px] font-black bg-blue-600 hover:bg-blue-700 px-2"
                              disabled={confirmCheckInMutation.isPending}
                              onClick={() => confirmCheckInMutation.mutate(res.id)}
                            >
                              {confirmCheckInMutation.isPending ? <Loader2 className="h-3 w-3 animate-spin" /> : <LogIn className="h-3 w-3" />}
                            </Button>
                          )}
                          {res.status === 'checked_in' && (
                            <Button
                              size="sm"
                              variant="outline"
                              className="h-7 text-[10px] font-black text-amber-700 border-amber-400/40 hover:bg-amber-50 px-2"
                              disabled={checkOutMutation.isPending}
                              onClick={() => {
                                setSelectedRes(res);
                                checkOutMutation.mutate(res.id);
                              }}
                            >
                              {checkOutMutation.isPending ? <Loader2 className="h-3 w-3 animate-spin" /> : <LogOut className="h-3 w-3" />}
                            </Button>
                          )}
                          <Button
                            size="sm"
                            variant="ghost"
                            className="h-7 px-2"
                            onClick={() => { setSelectedRes(res); setSummaryOpen(true); }}
                          >
                            <Info className="h-3.5 w-3.5" />
                          </Button>
                        </div>
                      </div>
                    );
                  })}
                </div>
              )}
            </CardContent>
          </Card>
        </TabsContent>

      </Tabs>

      {/* Quick Check-in Dialog */}
      <Dialog open={checkInOpen} onOpenChange={setCheckInOpen}>
        <DialogContent className="sm:max-w-[425px]">
          <DialogHeader>
            <DialogTitle className="flex items-center gap-2">
              <span className="text-emerald-600">Quick Check-in</span>
              <Badge variant="outline">Room {selectedRoom?.room_number}</Badge>
            </DialogTitle>
          </DialogHeader>
          <div className="grid gap-4 py-4">
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label>First Name</Label>
                <Input value={guestForm.first_name} onChange={e => setGuestForm({...guestForm, first_name: e.target.value})} />
              </div>
              <div className="space-y-2">
                <Label>Last Name</Label>
                <Input value={guestForm.last_name} onChange={e => setGuestForm({...guestForm, last_name: e.target.value})} />
              </div>
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label>Email <span className="text-[10px] text-muted-foreground">(Optional)</span></Label>
                <Input placeholder="guest@example.com" value={guestForm.email} onChange={e => setGuestForm({...guestForm, email: e.target.value})} />
              </div>
              <div className="space-y-2">
                <Label>Phone Number</Label>
                <Input placeholder="+234..." value={guestForm.phone} onChange={e => setGuestForm({...guestForm, phone: e.target.value})} />
              </div>
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label>Stay Duration (Days)</Label>
                <Input type="number" min="1" value={guestForm.days} onChange={e => {
                  const days = parseInt(e.target.value || '0');
                  setGuestForm(prev => {
                    const out = addDays(startOfDay(new Date()), days > 0 ? days : 1);
                    return { ...prev, days: e.target.value, check_out_date: format(out, 'yyyy-MM-dd') };
                  });
                }} />
              </div>
              <div className="space-y-2">
                <Label>Check-out Date</Label>
                <Input type="date" value={guestForm.check_out_date} onChange={e => {
                  const newOut = e.target.value;
                  setGuestForm(prev => {
                    if (newOut) {
                      const dIn = startOfDay(new Date());
                      const dOut = startOfDay(parseISO(newOut));
                      const diffDays = differenceInCalendarDays(dOut, dIn);
                      if (diffDays > 0) {
                        return { ...prev, check_out_date: newOut, days: diffDays.toString() };
                      }
                    }
                    return { ...prev, check_out_date: newOut };
                  });
                }} />
              </div>
            </div>
            <div className="mt-4 p-4 rounded-xl bg-emerald-50 border border-emerald-100 space-y-2">
              <div className="flex justify-between items-center text-sm">
                <span className="text-emerald-700 font-medium">Daily Rate:</span>
                <span className="font-bold text-emerald-900 font-mono">₦{selectedRoom?.room_type?.base_price?.toLocaleString() || '0'}</span>
              </div>
              <div className="flex justify-between items-center text-lg font-black border-t border-emerald-200/50 pt-2">
                <span className="text-emerald-800">Total Payable:</span>
                <span className="text-emerald-600 font-mono">₦{(parseInt(guestForm.days) * (selectedRoom?.room_type?.base_price || 0)).toLocaleString()}</span>
              </div>
              <p className="text-[10px] text-emerald-600/70 italic mt-2">
                * Nigerian Rules: Lodging starts 8:00 AM, Check-out 12:00 PM next day.
              </p>
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setCheckInOpen(false)}>Cancel</Button>
            <Button 
              className="bg-emerald-600 hover:bg-emerald-700"
              onClick={() => checkInMutation.mutate({
                guest_first_name: guestForm.first_name,
                guest_last_name: guestForm.last_name,
                guest_email: guestForm.email,
                guest_phone: guestForm.phone,
                room_type_id: selectedRoom?.room_type?.id,
                hotel_slug: branchSlug,
                rooms: [{ id: selectedRoom?.id }],
                check_in_date: format(new Date(), 'yyyy-MM-dd'),
                check_out_date: guestForm.check_out_date,
                number_of_days: parseInt(guestForm.days)
              })}
              disabled={checkInMutation.isPending || !guestForm.first_name}
            >
              {checkInMutation.isPending && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
              Confirm & Pay
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Reservation Summary & Billing */}
      <Dialog open={summaryOpen} onOpenChange={setSummaryOpen}>
        <DialogContent className="sm:max-w-[425px]">
          <DialogHeader>
            <DialogTitle className="flex items-center justify-between">
              <span>Reservation Details</span>
              <Badge variant={selectedRes?.status === 'checked_in' ? 'default' : 'outline'}>
                {selectedRes?.status?.replace('_', ' ').toUpperCase()}
              </Badge>
            </DialogTitle>
          </DialogHeader>
          <div className="space-y-6 py-4">
            <div className="flex items-center gap-4">
              <div className="h-12 w-12 rounded-full bg-primary/10 flex items-center justify-center font-bold text-primary">
                {selectedRes?.guest?.first_name?.[0]}{selectedRes?.guest?.last_name?.[0]}
              </div>
              <div>
                <h4 className="font-bold text-lg">{selectedRes?.guest?.first_name} {selectedRes?.guest?.last_name}</h4>
                <p className="text-xs text-muted-foreground">{selectedRes?.guest?.email || 'No email provided'}</p>
              </div>
            </div>
            
            {/* Billing Alert / Payment Recovery */}
            {selectedRes?.status === 'checked_in' && (parseFloat(selectedRes?.folios?.[0]?.balance || 0) > 0) && (
              (() => {
                const checkOut = parseISO(selectedRes.check_out_date);
                const limit = subHours(setHours(setMinutes(checkOut, 0), 12), 5); // 7 AM
                if (isToday(checkOut) && new Date() > limit) {
                  return (
                    <div className="p-4 rounded-xl bg-destructive/10 border border-destructive/20 animate-pulse">
                      <div className="flex items-center gap-2 text-destructive font-black text-sm mb-1">
                        <AlertTriangle className="h-4 w-4" />
                        PAYMENT RECOVERY REQUIRED
                      </div>
                      <p className="text-[10px] text-destructive/80 font-medium">
                        This guest is within 5 hours of 12 PM check-out and has an outstanding balance of ₦{parseFloat(selectedRes.folios[0].balance).toLocaleString()}. 
                        Charge to room is now disabled.
                      </p>
                    </div>
                  );
                }
                return null;
              })()
            )}

            <div className="grid grid-cols-2 gap-4 text-sm">
              <div className="space-y-1">
                <span className="text-muted-foreground font-medium">Check-in</span>
                <p className="font-bold">{selectedRes?.check_in_date}</p>
              </div>
              <div className="space-y-1">
                <span className="text-muted-foreground font-medium">Check-out</span>
                <p className="font-bold">{selectedRes?.check_out_date}</p>
              </div>
            </div>

            <Button 
              variant="outline" 
              className="w-full justify-between h-12 border-primary/20 hover:border-primary/40 hover:bg-primary/5 transition-colors"
              onClick={() => {
                setSummaryOpen(false);
                setFolioOpen(true);
              }}
            >
              <div className="flex items-center gap-2">
                <Info className="h-4 w-4 text-primary" />
                <span className="font-bold">View Detailed Bills</span>
              </div>
              <ChevronRight className="h-4 w-4" />
            </Button>
          </div>
          <DialogFooter>
            <Button variant="ghost" className="text-xs" onClick={() => setSummaryOpen(false)}>Close</Button>
            {selectedRes?.status === 'confirmed' && (
               <Button 
                className="bg-emerald-600"
                onClick={() => {
                  if (selectedRes?.id) {
                    api.post(`/api/v1/pms/reservations/${selectedRes.id}/check-in`).then(() => {
                      toast.success('Guest checked in successfully!');
                      queryClient.invalidateQueries({ queryKey: ['reception-reservations'] });
                      setSummaryOpen(false);
                    }).catch((err) => {
                      toast.error(err?.response?.data?.message || 'Failed to check in guest.');
                    });
                  }
                }}
              >
                Confirm Check-in
              </Button>
            )}
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Folio / Billing Display */}
      <Dialog open={folioOpen} onOpenChange={setFolioOpen}>
        <DialogContent className="sm:max-w-md max-h-[80vh] overflow-hidden flex flex-col">
          <DialogHeader>
            <DialogTitle>Guest Folio & Bills</DialogTitle>
          </DialogHeader>
          <div className="flex-1 overflow-y-auto py-4 space-y-4">
            {folio?.items?.length === 0 ? (
              <p className="text-center text-muted-foreground py-8 italic">No charges found.</p>
            ) : (
              <div className="divide-y border rounded-xl overflow-hidden shadow-sm">
                {folio?.items?.map((item: any) => (
                  <div key={item.id} className="flex justify-between p-3 bg-card hover:bg-muted/30 transition-colors">
                    <div className="space-y-0.5">
                      <p className="text-sm font-bold">{item.description}</p>
                      <p className="text-[10px] text-muted-foreground uppercase font-medium tracking-widest">{item.source || 'General'}</p>
                    </div>
                    <div className="text-sm font-black font-mono">₦{parseFloat(item.amount).toLocaleString()}</div>
                  </div>
                ))}
              </div>
            )}
          </div>
          <div className="p-4 bg-muted/30 rounded-xl space-y-2 mb-4">
             <div className="flex justify-between text-sm">
                <span className="text-muted-foreground font-medium">Total Charges:</span>
                <span className="font-bold">₦{parseFloat(folio?.total_charges || 0).toLocaleString()}</span>
             </div>
             <div className="flex justify-between text-lg font-black border-t border-border pt-2 text-primary">
                <span>Account Balance:</span>
                <span className="font-mono">₦{parseFloat(folio?.balance || 0).toLocaleString()}</span>
             </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setFolioOpen(false)}>Back</Button>
            <Button className="font-bold">Settle Account</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
