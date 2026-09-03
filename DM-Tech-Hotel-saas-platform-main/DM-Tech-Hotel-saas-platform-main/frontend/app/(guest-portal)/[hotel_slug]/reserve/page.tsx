'use client';

import React, { useState, useEffect, useMemo } from 'react';
import { useParams, useRouter } from 'next/navigation';
import { 
  Calendar, Users, BedDouble, Star, ArrowRight, Loader2, 
  CheckCircle, MapPin, Phone, Mail, Coffee, Wifi, 
  Wind, Tv, Shield, Info, CreditCard 
} from 'lucide-react';
import { format, differenceInCalendarDays, parseISO } from 'date-fns';
import api from '@/lib/api';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { 
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter 
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

// ─── Constants ────────────────────────────────────────────────────────────────

const AMENITY_MAP: Record<string, any> = {
  'wifi': Wifi,
  'ac': Wind,
  'air conditioning': Wind,
  'breakfast': Coffee,
  'tv': Tv,
  'security': Shield,
  'netflix': Tv,
  'room service': BedDouble,
};

// ─── Types ────────────────────────────────────────────────────────────────────

interface Theme {
  primary_color: string;
  accent_color: string;
  hotel_name: string;
  currency: string;
  logo_url?: string;
  design_settings?: any;
}

interface RoomType {
  id: number;
  name: string;
  description: string;
  base_price: number;
  capacity: number;
  amenities?: string[];
  available_rooms?: number;
  total_price?: number;
  nights?: number;
}

interface BookingData {
  hotel: { id: number; name: string; email: string; address: string; group_slug?: string };
  theme: Theme;
  room_types: RoomType[];
  payment: { gateway: string; public_key: string };
}

// ─── Reserve Page ─────────────────────────────────────────────────────────────

export default function ReservePage() {
  const params = useParams<{ hotel_slug: string }>();
  const slug = params.hotel_slug;
  const router = useRouter();

  const [loading, setLoading] = useState(true);
  const [bookingData, setBookingData] = useState<BookingData | null>(null);
  const [step, setStep] = useState<'search' | 'select' | 'details' | 'confirmed'>('search');
  
  const [selectedRoom, setSelectedRoom] = useState<RoomType | null>(null);
  const [availability, setAvailability] = useState<RoomType[]>([]);
  const [checkingAvail, setCheckingAvail] = useState(false);
  const [reserving, setReserving] = useState(false);
  const [error, setError] = useState('');
  
  const [summaryOpen, setSummaryOpen] = useState(false);
  const [reservationId, setReservationId] = useState<number | null>(null);

  const [dates, setDates] = useState({ 
    check_in: format(new Date(), 'yyyy-MM-dd'), 
    check_out: format(addDays(new Date(), 1), 'yyyy-MM-dd') 
  });

  const [quantity, setQuantity] = useState(1);

  const [guestInfo, setGuestInfo] = useState({
    guest_name: '', guest_email: '', guest_phone: '', adults: '1', special_requests: '',
  });

  // Helper inside component since it uses state
  function addDays(date: Date, days: number) {
    const result = new Date(date);
    result.setDate(result.getDate() + days);
    return result;
  }

  const nights = useMemo(() => {
    if (!dates.check_in || !dates.check_out) return 0;
    try {
      return differenceInCalendarDays(parseISO(dates.check_out), parseISO(dates.check_in));
    } catch { return 0; }
  }, [dates]);

  // Fetch hotel info on mount
  useEffect(() => {
    api.get(`/api/v1/booking/${slug}`)
      .then((res) => {
        setBookingData(res.data);
      })
      .catch(() => setError('Hotel not found or unavailable.'))
      .finally(() => setLoading(false));
  }, [slug]);

  // Check availability
  const checkAvailability = async () => {
    if (!dates.check_in || !dates.check_out) return;
    setCheckingAvail(true);
    setError('');
    try {
      const res = await api.get(`/api/v1/booking/${slug}/availability`, {
        params: { check_in: dates.check_in, check_out: dates.check_out }
      });
      setAvailability(res.data.room_types ?? []);
      setStep('select');
    } catch {
      setError('Failed to fetch real-time availability. Please try different dates.');
    } finally {
      setCheckingAvail(false);
    }
  };

  // Make reservation
  const makeReservation = async () => {
    if (!selectedRoom) return;
    setReserving(true);
    setError('');
    try {
      const r = await api.post(`/api/v1/booking/${slug}/reserve`, {
        room_type_id: selectedRoom.id,
        quantity,
        ...dates,
        ...guestInfo,
      });
      setReservationId(r.data.reservation_id);
      setSummaryOpen(false);
      setStep('confirmed');
    } catch (e: any) {
      setError(e.response?.data?.message || 'An error occurred while processing your booking.');
    } finally {
      setReserving(false);
    }
  };

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-[#050810]">
        <Loader2 className="animate-spin h-10 w-10 text-indigo-500" />
      </div>
    );
  }

  if (error && !bookingData) {
    return (
      <div className="min-h-screen flex flex-col items-center justify-center text-white bg-[#050810] p-6 text-center">
        <Info className="h-16 w-16 text-red-500/50 mb-4" />
        <h1 className="text-2xl font-bold mb-2">Something went wrong</h1>
        <p className="text-white/50 mb-6">{error}</p>
        <Button onClick={() => window.location.reload()} variant="outline">Try Again</Button>
      </div>
    );
  }

  const { hotel, theme } = bookingData!;
  const primary = theme.primary_color;
  const accent  = theme.accent_color;

  return (
    <div className="min-h-screen text-white selection:bg-indigo-500/30 font-sans" style={{ background: '#02040a' }}>
      
      {/* ── Navigation ─── */}
      <nav className="sticky top-0 z-50 border-b border-white/5 backdrop-blur-2xl bg-black/40">
        <div className="max-w-7xl mx-auto px-6 h-20 flex items-center justify-between">
          <div className="flex items-center gap-4">
             {theme.logo_url ? (
               <img src={theme.logo_url} alt={theme.hotel_name} className="h-10 w-auto object-contain" />
             ) : (
               <div className="h-11 w-11 rounded-2xl flex items-center justify-center text-white font-black text-xl shadow-xl shadow-indigo-500/20"
                 style={{ background: `linear-gradient(135deg, ${primary}, ${accent})` }}>
                 {theme.hotel_name.charAt(0)}
               </div>
             )}
             <div className="hidden sm:block">
               <h3 className="font-bold text-lg leading-none">{theme.hotel_name}</h3>
               <p className="text-[10px] text-white/40 uppercase tracking-widest mt-1">Direct Reservation Portal</p>
             </div>
          </div>
          
          <div className="flex items-center gap-6">
            <div className="hidden lg:flex items-center gap-4 text-xs text-white/40">
              <span className="flex items-center gap-1.5"><MapPin className="h-3.5 w-3.5" />{hotel.address}</span>
              <span className="h-4 w-px bg-white/10" />
              <span className="flex items-center gap-1.5"><Phone className="h-3.5 w-3.5" />Reservations Open 24/7</span>
            </div>
            {step !== 'confirmed' && (
              <Button 
                variant="ghost" 
                className="text-white/60 hover:text-white"
                onClick={() => {
                  if (step === 'select') setStep('search');
                  if (step === 'details') setStep('select');
                }}
                disabled={step === 'search'}
              >
                Back
              </Button>
            )}
          </div>
        </div>
      </nav>

      <main className="max-w-7xl mx-auto px-6 py-12">
        <div className="grid lg:grid-cols-12 gap-12">
          
          {/* ── Left Column: Content ─── */}
          <div className="lg:col-span-8 space-y-12">
            
            {/* Header */}
            <div>
              <h1 className="text-4xl md:text-6xl font-black tracking-tighter mb-4 animate-in fade-in slide-in-from-bottom-4 duration-700">
                Experience{' '}
                <span className="bg-clip-text text-transparent" style={{ backgroundImage: `linear-gradient(to right, ${primary}, ${accent})` }}>
                  Unmatched Luxury
                </span>
              </h1>
              <p className="text-white/50 text-lg max-w-xl">
                Discover the perfect blend of comfort and elegance at {hotel.name}. Secure your getaway today.
              </p>
            </div>

            {/* Steps Rendering */}
            {step === 'search' && (
              <section className="p-8 rounded-[2rem] bg-white/[0.03] border border-white/10 backdrop-blur-sm animate-in fade-in zoom-in-95 duration-500">
                <div className="flex items-center gap-3 mb-8">
                  <div className="h-10 w-10 rounded-full bg-indigo-500/10 flex items-center justify-center">
                    <Calendar className="h-5 w-5 text-indigo-400" />
                  </div>
                  <div>
                    <h2 className="text-xl font-bold">Pick Your Dates</h2>
                    <p className="text-xs text-white/40">Real-time availability for groups and individuals</p>
                  </div>
                </div>

                <div className="grid sm:grid-cols-2 gap-6 mb-8">
                  <div className="space-y-2">
                    <Label className="text-white/60 text-[10px] uppercase font-bold tracking-widest pl-1">Check-In</Label>
                    <div className="relative group">
                      <Calendar className="absolute left-4 top-1/2 -translate-y-1/2 h-4 w-4 text-white/20 group-focus-within:text-indigo-400 transition-colors" />
                      <Input 
                        type="date" 
                        value={dates.check_in}
                        min={format(new Date(), 'yyyy-MM-dd')}
                        onChange={e => setDates(d => ({ ...d, check_in: e.target.value }))}
                        className="h-14 pl-11 bg-white/[0.05] border-white/10 focus:ring-2 focus:ring-indigo-500/20"
                      />
                    </div>
                  </div>
                  <div className="space-y-2">
                    <Label className="text-white/60 text-[10px] uppercase font-bold tracking-widest pl-1">Check-Out</Label>
                    <div className="relative group">
                      <Calendar className="absolute left-4 top-1/2 -translate-y-1/2 h-4 w-4 text-white/20 group-focus-within:text-indigo-400 transition-colors" />
                      <Input 
                        type="date" 
                        value={dates.check_out}
                        min={dates.check_in || format(new Date(), 'yyyy-MM-dd')}
                        onChange={e => setDates(d => ({ ...d, check_out: e.target.value }))}
                        className="h-14 pl-11 bg-white/[0.05] border-white/10 focus:ring-2 focus:ring-indigo-500/20"
                      />
                    </div>
                  </div>
                </div>

                <Button 
                  onClick={checkAvailability}
                  disabled={checkingAvail || !dates.check_in || !dates.check_out}
                  className="w-full h-16 rounded-2xl text-lg font-bold shadow-2xl shadow-indigo-500/20 transition-all hover:scale-[1.01] active:scale-[0.99] disabled:opacity-50"
                  style={{ background: `linear-gradient(to right, ${primary}, ${accent})` }}
                >
                  {checkingAvail ? <Loader2 className="animate-spin h-6 w-6" /> : 'Find Available Rooms'}
                </Button>
              </section>
            )}

            {step === 'select' && (
              <div className="space-y-6 animate-in fade-in slide-in-from-right-4 duration-500">
                <div className="flex items-center justify-between">
                  <h2 className="text-2xl font-bold flex items-center gap-3">
                    <BedDouble className="h-6 w-6 text-indigo-400" />
                    Available Room Categories
                  </h2>
                  <Badge variant="outline" className="text-white/40 border-white/10 px-3 py-1">
                    {availability.length} Options
                  </Badge>
                </div>

                {availability.length === 0 ? (
                  <div className="text-center py-20 bg-white/[0.02] rounded-3xl border border-dashed border-white/10">
                    <div className="h-20 w-20 rounded-full bg-white/5 flex items-center justify-center mx-auto mb-6">
                       <Calendar className="h-10 w-10 text-white/20" />
                    </div>
                    <h3 className="text-xl font-bold mb-2">No Rooms Found</h3>
                    <p className="text-white/40 mb-8 max-w-xs mx-auto">Try selecting different dates or reaching out to support.</p>
                    <Button variant="outline" onClick={() => setStep('search')}>Change Dates</Button>
                  </div>
                ) : (
                  <div className="grid md:grid-cols-2 gap-6">
                    {availability.map((rt) => (
                      <div 
                        key={rt.id}
                        className="group relative bg-white/[0.03] border border-white/10 rounded-[2.5rem] p-2 transition-all hover:bg-white/[0.05] hover:border-white/20 hover:shadow-2xl hover:shadow-indigo-500/5"
                      >
                        <div className="p-6 space-y-6">
                          <div className="flex justify-between items-start">
                            <div className="space-y-1">
                              <h3 className="text-2xl font-black">{rt.name}</h3>
                              <p className="text-sm text-white/40 line-clamp-2 leading-relaxed">{rt.description}</p>
                            </div>
                            <Badge className="bg-white/10 text-white font-bold px-3 py-1 rounded-full uppercase text-[10px] tracking-widest">
                               {rt.available_rooms} Left
                            </Badge>
                          </div>

                          {/* Amenities */}
                          {rt.amenities && rt.amenities.length > 0 && (
                            <div className="flex flex-wrap gap-2">
                              {rt.amenities.map(amenity => {
                                const Icon = AMENITY_MAP[amenity.toLowerCase()] || Info;
                                return (
                                  <div key={amenity} className="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white/5 border border-white/5 text-[10px] uppercase font-bold tracking-tight text-white/60">
                                    <Icon className="h-3 w-3" />
                                    {amenity}
                                  </div>
                                );
                              })}
                            </div>
                          )}

                          <div className="pt-6 border-t border-white/5 flex items-center justify-between">
                            <div>
                               <p className="text-[10px] text-white/40 uppercase tracking-widest mb-1">{nights} Nights Stay</p>
                               <div className="flex items-baseline gap-1">
                                  <span className="text-2xl font-black leading-none">{theme.currency} {rt.total_price?.toLocaleString()}</span>
                                  <span className="text-xs text-white/30 font-medium"> total</span>
                               </div>
                            </div>
                            <Button 
                              onClick={() => { setSelectedRoom(rt); setStep('details'); }}
                              className="rounded-2xl h-12 px-6 font-bold bg-white text-black hover:bg-white/90 transition-all group-hover:px-8"
                            >
                               Select <ArrowRight className="h-4 w-4 ml-2" />
                            </Button>
                          </div>
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            )}

            {step === 'details' && (
              <section className="animate-in fade-in slide-in-from-right-4 duration-500 space-y-8">
                <div className="flex items-center justify-between">
                   <h2 className="text-2xl font-bold flex items-center gap-3">
                     <Users className="h-6 w-6 text-indigo-400" />
                     Guest Details
                   </h2>
                </div>

                <div className="grid grid-cols-1 sm:grid-cols-2 gap-6">
                  <div className="space-y-2">
                    <Label className="text-white/40 text-[10px] uppercase font-black tracking-[0.2em] pl-1">Full Name</Label>
                    <Input 
                      placeholder="John Olawale" 
                      value={guestInfo.guest_name}
                      onChange={e => setGuestInfo(i => ({...i, guest_name: e.target.value}))}
                      className="h-14 bg-white/[0.05] border-white/10"
                    />
                  </div>
                  <div className="space-y-2">
                    <Label className="text-white/40 text-[10px] uppercase font-black tracking-[0.2em] pl-1">Email Address</Label>
                    <Input 
                      placeholder="john@example.com"
                      value={guestInfo.guest_email}
                      onChange={e => setGuestInfo(i => ({...i, guest_email: e.target.value}))}
                      className="h-14 bg-white/[0.05] border-white/10"
                    />
                  </div>
                  <div className="space-y-2">
                    <Label className="text-white/40 text-[10px] uppercase font-black tracking-[0.2em] pl-1">Phone Number</Label>
                    <Input 
                      placeholder="+234..."
                      value={guestInfo.guest_phone}
                      onChange={e => setGuestInfo(i => ({...i, guest_phone: e.target.value}))}
                      className="h-14 bg-white/[0.05] border-white/10"
                    />
                  </div>
                  <div className="space-y-2">
                    <Label className="text-white/40 text-[10px] uppercase font-black tracking-[0.2em] pl-1">Number of Guests</Label>
                    <div className="relative">
                      <Users className="absolute left-4 top-1/2 -translate-y-1/2 h-4 w-4 text-white/20" />
                      <Input 
                        type="number" min="1"
                        value={guestInfo.adults}
                        onChange={e => setGuestInfo(i => ({...i, adults: e.target.value}))}
                        className="h-14 pl-11 bg-white/[0.05] border-white/10"
                      />
                    </div>
                  </div>
                  <div className="space-y-2">
                    <Label className="text-white/40 text-[10px] uppercase font-black tracking-[0.2em] pl-1">Number of Rooms</Label>
                    <div className="relative">
                      <BedDouble className="absolute left-4 top-1/2 -translate-y-1/2 h-4 w-4 text-white/20" />
                      <Input 
                        type="number" min="1" max={selectedRoom?.available_rooms || 1}
                        value={quantity}
                        onChange={e => setQuantity(parseInt(e.target.value) || 1)}
                        className="h-14 pl-11 bg-white/[0.05] border-white/10"
                      />
                    </div>
                    {selectedRoom && (
                      <p className="text-[10px] text-white/30 pl-1">Max available: {selectedRoom.available_rooms}</p>
                    )}
                  </div>
                </div>

                <div className="space-y-2">
                   <Label className="text-white/40 text-[10px] uppercase font-black tracking-[0.2em] pl-1">Special Requests</Label>
                   <textarea 
                     className="w-full min-h-[120px] rounded-3xl bg-white/[0.05] border border-white/10 p-4 text-white placeholder:text-white/20 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 resize-none transition-all"
                     placeholder="Early check-in, dietary needs, airport pickup requests..."
                     value={guestInfo.special_requests}
                     onChange={e => setGuestInfo(i => ({...i, special_requests: e.target.value}))}
                   />
                </div>

                <Button 
                  onClick={() => setSummaryOpen(true)}
                  disabled={!guestInfo.guest_name || !guestInfo.guest_email}
                  className="w-full h-16 rounded-2xl text-lg font-bold shadow-2xl shadow-indigo-500/20"
                  style={{ background: `linear-gradient(to right, ${primary}, ${accent})` }}
                >
                  Verify Booking Summary
                </Button>
              </section>
            )}

            {step === 'confirmed' && (
               <section className="max-w-md mx-auto py-12 text-center animate-in zoom-in-95 fade-in duration-700">
                  <div className="h-24 w-24 rounded-full bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center mx-auto mb-8">
                     <CheckCircle className="h-12 w-12 text-emerald-500" />
                  </div>
                  <h2 className="text-4xl font-black mb-4">You're All Set!</h2>
                  <p className="text-white/50 mb-10 leading-relaxed">
                    Your reservation <strong>#{reservationId}</strong> has been secured. A confirmation email was sent to {guestInfo.guest_email}.
                  </p>
                  <Button 
                    className="w-full h-14 rounded-2xl bg-white text-black hover:bg-white/90"
                    onClick={() => {
                        const target = bookingData?.hotel?.group_slug 
                           ? `/group/${bookingData.hotel.group_slug}` 
                           : '/';
                        window.location.href = target;
                    }}
                  >
                    Return to Website
                  </Button>
               </section>
            )}

          </div>

          {/* ── Right Column: Sidebar (Summary) ─── */}
          <div className="lg:col-span-4 lg:sticky lg:top-32 h-fit">
             <div className="p-8 rounded-[2.5rem] bg-gradient-to-b from-white/[0.06] to-transparent border border-white/10 backdrop-blur-sm space-y-8">
                <h4 className="text-xs font-black uppercase tracking-[0.3em] text-white/30 border-b border-white/5 pb-4">
                   Your Reservation
                </h4>
                
                <div className="space-y-6">
                   <div className="flex items-start gap-4">
                      <div className="h-10 w-10 rounded-2xl bg-white/5 border border-white/5 flex items-center justify-center shrink-0">
                         <MapPin className="h-4 w-4 text-white/40" />
                      </div>
                      <div>
                         <p className="text-[10px] text-white/30 font-bold uppercase tracking-widest">Property</p>
                         <p className="font-bold text-sm">{hotel.name}</p>
                      </div>
                   </div>

                   <div className="flex items-start gap-4">
                      <div className="h-10 w-10 rounded-2xl bg-white/5 border border-white/5 flex items-center justify-center shrink-0">
                         <Calendar className="h-4 w-4 text-white/40" />
                      </div>
                      <div>
                         <p className="text-[10px] text-white/30 font-bold uppercase tracking-widest">Stay Period</p>
                         <p className="font-bold text-sm tracking-tight">
                            {dates.check_in ? format(parseISO(dates.check_in), 'MMM dd') : '—'} 
                            <ArrowRight className="inline-block h-3 w-3 mx-1 text-white/20" />
                            {dates.check_out ? format(parseISO(dates.check_out), 'MMM dd') : '—'}
                         </p>
                         <p className="text-[10px] text-white/20 font-medium">({nights} Nights Stay)</p>
                      </div>
                   </div>

                   {selectedRoom && (
                     <div className="flex items-start gap-4 animate-in fade-in slide-in-from-top-2">
                        <div className="h-10 w-10 rounded-2xl bg-white/5 border border-white/5 flex items-center justify-center shrink-0">
                           <BedDouble className="h-4 w-4 text-white/40" />
                        </div>
                        <div>
                           <p className="text-[10px] text-white/30 font-bold uppercase tracking-widest">Category</p>
                           <p className="font-bold text-sm">{selectedRoom.name}</p>
                        </div>
                     </div>
                   )}
                </div>

                <div className="pt-8 border-t border-white/5 space-y-4">
                   <div className="flex justify-between text-xs text-white/40 font-medium">
                      <span>Rate Per Night</span>
                      <span>{theme.currency} {selectedRoom?.base_price?.toLocaleString() || '0'}</span>
                   </div>
                   <div className="flex justify-between text-xs text-white/40 font-medium">
                      <span>Stay Duration</span>
                      <span>{nights} Nights</span>
                   </div>
                   <div className="flex justify-between text-xs text-white/40 font-medium">
                      <span>Number of Rooms</span>
                      <span>{quantity} Room(s)</span>
                   </div>
                   <div className="flex justify-between text-xl font-black text-white pt-2">
                      <span>Total</span>
                      <span style={{ color: primary }}>
                         {theme.currency} {selectedRoom ? (selectedRoom.base_price * nights * quantity).toLocaleString() : '0'}
                      </span>
                   </div>
                </div>

                <div className="p-4 rounded-2xl bg-white/5 border border-white/5 flex items-center gap-3">
                   <CreditCard className="h-5 w-5 text-white/30" />
                   <div>
                      <p className="text-[10px] text-white/40 font-bold uppercase tracking-widest">Secure Payment</p>
                      <p className="text-[9px] text-white/20 font-medium tracking-tight">SSL encrypted transaction</p>
                   </div>
                </div>
             </div>
          </div>

        </div>
      </main>

      {/* ── Summary Modal / Verification  ─── */}
      <Dialog open={summaryOpen} onOpenChange={setSummaryOpen}>
        <DialogContent className="sm:max-w-md bg-[#050810] border-white/10 text-white rounded-[2rem] overflow-hidden p-0">
          <div className="p-8 space-y-8">
            <DialogHeader>
              <DialogTitle className="text-3xl font-black flex items-center gap-3">
                 Confirm Booking
              </DialogTitle>
              <p className="text-white/40 text-sm">Review your stay details at {hotel.name}</p>
            </DialogHeader>

            <div className="space-y-6">
               <div className="p-5 rounded-3xl bg-white/5 border border-white/5 space-y-4">
                  <div className="flex justify-between items-center text-sm">
                     <span className="text-white/40 font-bold uppercase text-[10px] tracking-widest">Room Type</span>
                     <span className="font-black text-white">{selectedRoom?.name}</span>
                  </div>
                  <div className="flex justify-between items-center text-sm">
                     <span className="text-white/40 font-bold uppercase text-[10px] tracking-widest">Guest Name</span>
                     <span className="font-bold text-white">{guestInfo.guest_name}</span>
                  </div>
                  <div className="flex justify-between items-center text-sm">
                     <span className="text-white/40 font-bold uppercase text-[10px] tracking-widest">Duration</span>
                     <span className="font-bold text-white">{nights} Nights</span>
                  </div>
                  <div className="flex justify-between items-center text-sm">
                     <span className="text-white/40 font-bold uppercase text-[10px] tracking-widest">Rooms</span>
                     <span className="font-bold text-white">{quantity} Room(s)</span>
                  </div>
               </div>

               <div className="p-6 rounded-[2rem] text-center space-y-1" style={{ background: `${primary}15` }}>
                  <p className="text-xs font-bold text-white/40 uppercase tracking-widest">Total Payable</p>
                  <p className="text-5xl font-black tracking-tighter" style={{ color: primary }}>
                    {theme.currency} {(selectedRoom ? selectedRoom.base_price * nights * quantity : 0).toLocaleString()}
                  </p>
               </div>
            </div>

            {error && <p className="text-xs text-red-400 text-center font-medium bg-red-500/10 p-3 rounded-xl">{error}</p>}

            <DialogFooter className="flex-col sm:flex-col gap-3">
              <Button 
                onClick={makeReservation}
                disabled={reserving}
                className="w-full h-16 rounded-2xl text-lg font-bold shadow-2xl hover:scale-[1.02] active:scale-[0.98] transition-all"
                style={{ background: `linear-gradient(to right, ${primary}, ${accent})` }}
              >
                {reserving ? <><Loader2 className="mr-2 h-5 w-5 animate-spin" /> Confirming...</> : 'Pay & Secure Reservation'}
              </Button>
              <Button 
                variant="ghost" 
                onClick={() => setSummaryOpen(false)}
                className="w-full text-white/40 hover:text-white"
              >
                Go Back & Edit
              </Button>
            </DialogFooter>
          </div>
        </DialogContent>
      </Dialog>

      {/* ── Footer ─── */}
      <footer className="border-t border-white/5 py-12 text-center">
         <p className="text-white/20 text-[10px] font-bold uppercase tracking-[0.4em]">Powered by DM Tech Hotel SaaS</p>
         <div className="flex justify-center gap-6 mt-6 opacity-30 grayscale hover:grayscale-0 transition-all">
            <Shield className="h-5 w-5" />
            <CreditCard className="h-5 w-5" />
         </div>
      </footer>
    </div>
  );
}
