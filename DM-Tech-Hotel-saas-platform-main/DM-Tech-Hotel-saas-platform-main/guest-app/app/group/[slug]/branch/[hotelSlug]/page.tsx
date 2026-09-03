'use client';

import * as React from 'react';
import { useParams, useRouter } from 'next/navigation';
import { motion, AnimatePresence } from 'framer-motion';
import { 
  ArrowLeft, 
  MapPin, 
  Wifi, 
  Coffee, 
  Wind, 
  Trophy, 
  CheckCircle2,
  ChevronRight,
  TrendingUp,
  Star,
  Sparkles,
  Calendar,
  X,
  CreditCard,
  User,
  Phone,
  Mail,
  Loader2
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import api from '@/lib/api';
import { toast } from 'sonner';
import { cn } from '@/lib/utils';

export default function BranchBookingPage() {
  const { slug, hotelSlug } = useParams();
  const router = useRouter();
  const [data, setData] = React.useState<any>(null);
  const [loading, setLoading] = React.useState(true);
  const [selectedRoomType, setSelectedRoomType] = React.useState<any>(null);
  const [showDrawer, setShowDrawer] = React.useState(false);
  const [bookingLoading, setBookingLoading] = React.useState(false);
  const [confirmedRes, setConfirmedRes] = React.useState<any>(null);

  const [bookingForm, setBookingForm] = React.useState({
    guest_name: '',
    guest_email: '',
    guest_phone: '',
    check_in: new Date().toISOString().split('T')[0],
    check_out: new Date(Date.now() + 86400000).toISOString().split('T')[0],
    adults: 1,
    children: 0,
    special_requests: ''
  });

  const nights = React.useMemo(() => {
    if (!bookingForm.check_in || !bookingForm.check_out) return 1;
    const d1 = new Date(bookingForm.check_in);
    const d2 = new Date(bookingForm.check_out);
    const diff = Math.ceil((d2.getTime() - d1.getTime()) / (1000 * 3600 * 24));
    return diff > 0 ? diff : 1;
  }, [bookingForm.check_in, bookingForm.check_out]);

  React.useEffect(() => {
    const fetchDetails = async () => {
      try {
        setLoading(true);
        const { data: res } = await api.get(`/api/v1/booking/group/${slug}/branch/${hotelSlug}`);
        setData(res);
      } catch (e) {
        console.error("Failed to load branch details", e);
      } finally {
        setLoading(false);
      }
    };
    fetchDetails();
  }, [slug, hotelSlug]);

  const handleReserve = async () => {
    if (!bookingForm.guest_name || !bookingForm.guest_email) {
        toast.error("Please provide your name and email.");
        return;
    }

    try {
        setBookingLoading(true);
        // 1. Create Pending Reservation
        const { data: res } = await api.post(`/api/v1/booking/${hotelSlug}/reserve`, {
            room_type_id: selectedRoomType.id,
           ...bookingForm
        });

        // 2. Mock Payment Confirmation (Auto-confirm for this demo requirement)
        await api.post(`/api/v1/booking/${hotelSlug}/confirm-payment`, {
            reservation_id: res.reservation_id,
            reference: `PAY-GUEST-${Date.now()}`
        });

        setConfirmedRes(res);
        toast.success("Reservation Secured! Your suite is awaiting your arrival.");
    } catch (e: any) {
        toast.error(e.response?.data?.message || "Failed to orchestrate reservation.");
        console.error(e);
    } finally {
        setBookingLoading(false);
    }
  };

  if (loading) {
    return (
      <div className="min-h-screen bg-slate-950 flex items-center justify-center">
        <div className="w-12 h-12 border-4 border-indigo-500/30 border-t-indigo-500 rounded-full animate-spin"></div>
      </div>
    );
  }

  if (!data) return <div className="min-h-screen bg-slate-950 text-white flex items-center justify-center">Branch not found</div>;

  const hotel = data.hotel;
  const roomTypes = data.room_types;

  const amenities = [
    { name: 'Free WiFi', icon: Wifi },
    { name: 'Breakfast', icon: Coffee },
    { name: 'Air Conditioning', icon: Wind },
    { name: 'Luxury Pool', icon: Trophy },
  ];

  return (
    <div className="min-h-screen bg-stone-50 text-slate-900 selection:bg-slate-950/10 font-sans overflow-x-hidden">
      {/* Dynamic Navigation */}
      <nav className="fixed top-0 inset-x-0 h-24 bg-white/60 backdrop-blur-3xl border-b border-stone-200/50 z-50 flex items-center px-4 md:px-10 justify-between transition-all">
        <Button variant="ghost" className="gap-3 text-slate-400 hover:text-slate-950 font-bold text-xs uppercase tracking-widest p-0 transition-colors" onClick={() => router.back()}>
          <ArrowLeft size={16} />
          Back to Portfolio
        </Button>
        <div className="flex items-center gap-4">
          <div className="w-10 h-10 rounded-full bg-slate-950 flex items-center justify-center text-white font-serif italic text-lg shadow-xl shadow-slate-950/10">DM</div>
          <span className="font-black text-sm tracking-tighter uppercase text-slate-950 hidden sm:inline-block">{hotel.name}</span>
        </div>
        <Button className="bg-slate-950 text-white hover:bg-slate-800 rounded-full px-10 h-12 font-black text-[10px] uppercase tracking-[0.2em] shadow-xl shadow-slate-950/20 transition-all active:scale-95">
          My Account
        </Button>
      </nav>

      {/* Hero / Header - Full Width Alignment */}
       <section className="relative pt-44 pb-24 w-full px-4 md:px-10 overflow-hidden">
        {/* Ambient Mesh Gradient */}
        <div className="absolute inset-0 z-0 pointer-events-none">
            <div className="absolute top-[-20%] right-[-10%] w-[60%] h-[60%] bg-indigo-500/5 rounded-full blur-[150px] animate-pulse" />
            <div className="absolute bottom-[-10%] left-[-10%] w-[40%] h-[40%] bg-emerald-500/5 rounded-full blur-[120px] animate-pulse" style={{ animationDelay: '2s' }} />
        </div>

        <div className="relative z-10 grid grid-cols-1 lg:grid-cols-2 gap-32 items-end">
            <motion.div initial={{ opacity: 0, x: -30 }} animate={{ opacity: 1, x: 0 }} transition={{ duration: 1, ease: [0.16, 1, 0.3, 1] }} className="space-y-12">
              <div className="flex items-center gap-6">
                 <div className="h-[2px] w-16 bg-indigo-600"></div>
                 <span className="text-[11px] font-black uppercase tracking-[0.5em] text-indigo-600/60">Selected Property</span>
              </div>
              <h1 className="text-6xl md:text-8xl xl:text-9xl font-black tracking-tighter leading-[0.8] text-slate-950 italic font-serif font-normal">
                {hotel.name.split(' ').slice(0, -1).join(' ')} <br />
                <span className="not-italic text-slate-600">{hotel.name.split(' ').slice(-1)}</span>
              </h1>
              <div className="flex flex-wrap items-center gap-12 text-slate-400 font-bold text-xs uppercase tracking-widest pt-4">
                 <div className="flex items-center gap-3">
                   <MapPin size={18} className="text-indigo-600" />
                   {hotel.address}
                 </div>
                 <div className="flex items-center gap-2 text-emerald-600">
                   {[1,2,3,4,5].map(i => <Star key={i} size={14} fill="currentColor" />) }
                   <span className="ml-4 text-slate-400 font-black tracking-[0.2em]">Exclusive Class</span>
                 </div>
              </div>
            </motion.div>
            <motion.div initial={{ opacity: 0 }} animate={{ opacity: 1 }} transition={{ delay: 0.5 }} className="pb-6">
               <p className="text-slate-500 text-2xl font-medium leading-relaxed max-w-xl border-l-2 border-indigo-600 pl-12 italic">
                  An enclave of sophisticated comfort situated in {hotel.address?.split(',')[0]}, where architectural heritage meets modern luxury.
               </p>
            </motion.div>
        </div>
      </section>

      {/* Sticky Amenities - Full Width Strip */}
      <div className="bg-white border-y border-stone-100 py-10 mb-24 overflow-x-auto no-scrollbar">
         <div className="w-full px-4 md:px-10 flex items-center justify-start gap-24 whitespace-nowrap">
          {amenities.map((item, i) => (
            <div key={i} className="flex items-center gap-6 text-slate-400 font-black text-[10px] uppercase tracking-[0.4em] group cursor-default">
              <div className="p-4 rounded-full bg-stone-50 border border-stone-100 group-hover:bg-slate-950 group-hover:text-white group-hover:border-slate-950 transition-all duration-500">
                <item.icon size={20} />
              </div>
              {item.name}
            </div>
          ))}
        </div>
      </div>

       <div className="w-full px-4 md:px-10 grid grid-cols-1 lg:grid-cols-12 gap-16 xl:gap-24">
        {/* Room List - Spacious Editorial Layout */}
        <div className="lg:col-span-8 space-y-24">
          <section className="space-y-16">
            <h2 className="text-5xl font-black tracking-tighter italic font-serif font-normal text-slate-950">Curated Accommodations</h2>
            <div className="space-y-32">
              {roomTypes.map((room: any, i: number) => (
                <motion.div
                  key={room.id}
                  initial={{ opacity: 0, y: 40 }}
                  whileInView={{ opacity: 1, y: 0 }}
                  viewport={{ once: true }}
                  transition={{ delay: i * 0.1, duration: 1, ease: [0.16, 1, 0.3, 1] }}
                  className="group"
                >
                  <div className="grid grid-cols-1 md:grid-cols-2 gap-16 items-start">
                     <div className="relative aspect-[4/5] overflow-hidden bg-stone-100 shadow-sm group-hover:shadow-2xl group-hover:shadow-slate-950/10 transition-all duration-1000">
                        <img 
                            src={room.image_url || 'https://images.unsplash.com/photo-1611892440504-42a792e24d32?q=80&w=2070&auto=format&fit=crop'} 
                            className="w-full h-full object-cover grayscale-[0.1] transition-transform duration-[2s] group-hover:scale-110"
                            alt={room.name}
                        />
                        <div className="absolute top-8 left-8">
                           <Badge className="bg-white/90 backdrop-blur-md text-slate-950 border-none font-black text-[10px] uppercase tracking-widest px-4 py-2">
                              {room.base_capacity} Guests
                           </Badge>
                        </div>
                     </div>
                     <div className="space-y-10 py-4">
                        <div className="space-y-4">
                           <h3 className="text-5xl font-black tracking-tighter text-slate-950">{room.name}</h3>
                           <p className="text-slate-500 text-lg font-medium leading-relaxed italic">
                             {room.description || "A masterfully designed sanctuary featuring premium textiles, curated art, and floor-to-ceiling vistas."}
                           </p>
                        </div>

                        <div className="space-y-6 pt-6 border-t border-stone-100">
                           <p className="text-[10px] font-black text-slate-400 uppercase tracking-[0.3em]">Signature Features</p>
                           <div className="flex flex-wrap gap-x-12 gap-y-6">
                              {['Gourmet Bar', 'Rainfall Suite', 'Smart Control', 'Turn-down'].map(tag => (
                                <div key={tag} className="flex items-center gap-3 text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                                   <div className="w-1 h-1 rounded-full bg-slate-950" />
                                   {tag}
                                </div>
                              ))}
                           </div>
                        </div>

                        <div className="pt-10 flex items-center justify-between">
                           <div>
                              <div className="text-4xl font-black text-slate-950">
                                 ₦{parseFloat(room.base_price).toLocaleString()}
                              </div>
                              <div className="text-[9px] font-black text-slate-400 uppercase tracking-[0.3em] mt-1">Per Evening</div>
                           </div>
                           <Button 
                            className={cn(
                                "h-16 px-12 rounded-full font-black text-xs uppercase tracking-[0.2em] transition-all",
                                selectedRoomType?.id === room.id 
                                    ? "bg-slate-950 text-white shadow-2xl" 
                                    : "bg-white text-slate-950 border border-stone-200 hover:border-slate-950"
                            )}
                            onClick={() => setSelectedRoomType(room)}
                           >
                              {selectedRoomType?.id === room.id ? "Selected" : "Select Suite"}
                           </Button>
                        </div>
                     </div>
                  </div>
                </motion.div>
              ))}
            </div>
          </section>
        </div>

        {/* Sidebar / Concierge Summary */}
        <div className="lg:col-span-4 xl:col-span-3 relative">
          <div className="sticky top-40 space-y-10">
            <Card className="border-none bg-white shadow-2xl shadow-slate-950/5 overflow-hidden rounded-[3rem]">
              <div className="bg-slate-950 p-12 text-white space-y-2">
                <p className="text-[10px] font-black text-slate-400 uppercase tracking-[0.4em]">Reservation Summary</p>
                <h3 className="text-3xl font-black font-serif italic font-normal tracking-tight">Your Journey</h3>
              </div>
              <CardContent className="p-12 space-y-12">
                {selectedRoomType ? (
                  <motion.div initial={{ opacity: 0, scale: 0.98 }} animate={{ opacity: 1, scale: 1 }} className="space-y-12">
                    <div className="p-10 rounded-[2.5rem] bg-indigo-50/50 border border-indigo-100/50 space-y-3">
                       <p className="text-[9px] font-black text-indigo-400 uppercase tracking-[0.4em]">Confirmed Selection</p>
                       <div className="flex items-center justify-between">
                          <div className="font-black text-2xl text-slate-950 tracking-tight font-serif italic">{selectedRoomType.name}</div>
                          <Button variant="link" className="text-indigo-400 hover:text-indigo-600 p-0 h-auto text-[10px] font-black uppercase tracking-[0.3em]" onClick={() => setSelectedRoomType(null)}>Revise</Button>
                       </div>
                    </div>
                    
                      <div className="flex justify-between text-[11px] font-black text-slate-400 uppercase tracking-[0.3em]">
                        <span>Base Rate</span>
                        <span className="text-slate-950">₦{parseFloat(selectedRoomType.base_price).toLocaleString()}</span>
                      </div>
                      <div className="flex justify-between text-[11px] font-black text-emerald-600 uppercase tracking-[0.3em]">
                        <span>Service Protocols</span>
                        <span className="font-black">Included</span>
                      </div>
                      <div className="pt-12 border-t border-stone-100 flex flex-col gap-3">
                        <p className="text-[11px] font-black text-slate-400 uppercase tracking-[0.5em]">Total Commitment</p>
                        <p className="text-6xl font-black text-slate-950 tracking-tighter">₦{(parseFloat(selectedRoomType?.base_price || 0) * nights).toLocaleString()}</p>
                      </div>

                    <Button className="w-full h-24 bg-slate-950 hover:bg-indigo-600 rounded-full text-[11px] font-black uppercase tracking-[0.1em] shadow-2xl shadow-indigo-600/20 group transition-all active:scale-95"
                      onClick={() => setShowDrawer(true)}
                    >
                      Verify Reservation
                      <ChevronRight size={22} className="ml-4 group-hover:translate-x-2 transition-transform" />
                    </Button>
                  </motion.div>
                ) : (
                  <div className="py-24 text-center space-y-8">
                    <div className="w-24 h-24 rounded-full bg-stone-50 flex items-center justify-center mx-auto text-indigo-200 border border-stone-100">
                       <TrendingUp size={32} />
                    </div>
                    <p className="text-slate-400 font-black text-[11px] uppercase tracking-[0.3em] leading-relaxed">Please select a suite to <br />initiate your reservation.</p>
                  </div>
                )}
                <div className="flex items-center justify-center gap-3 pt-4 opacity-30">
                   <div className="h-[1px] w-8 bg-slate-950" />
                   <Sparkles size={14} />
                   <div className="h-[1px] w-8 bg-slate-950" />
                </div>
              </CardContent>
            </Card>

            <AnimatePresence>
                {showDrawer && (
                    <>
                    <motion.div 
                        initial={{ opacity: 0 }} animate={{ opacity: 1 }} exit={{ opacity: 0 }}
                        onClick={() => !bookingLoading && setShowDrawer(false)}
                        className="fixed inset-0 bg-slate-950/40 backdrop-blur-md z-[100]" 
                    />
                    <motion.div 
                        initial={{ x: '100%' }} animate={{ x: 0 }} exit={{ x: '100%' }}
                        transition={{ duration: 0.8, ease: [0.16, 1, 0.3, 1] }}
                        className="fixed right-0 top-0 bottom-0 w-full max-w-2xl bg-white shadow-[-40px_0_80px_-20px_rgba(0,0,0,0.1)] z-[101] flex flex-col pt-32 pb-12 px-12 md:px-20 overflow-y-auto"
                    >
                        <button 
                            onClick={() => setShowDrawer(false)}
                            className="absolute top-12 right-12 p-4 rounded-full bg-stone-50 border border-stone-100 hover:bg-slate-950 hover:text-white transition-all duration-500"
                        >
                            <X size={20} />
                        </button>

                        {!confirmedRes ? (
                            <div className="flex flex-col h-full">
                                <div className="space-y-4 mb-16">
                                    <p className="text-[10px] font-black text-indigo-600 uppercase tracking-[0.5em]">Concierge Checkout</p>
                                    <h2 className="text-5xl font-black tracking-tighter text-slate-950 italic font-serif">Secure Your Stay</h2>
                                </div>

                                <div className="flex-1 space-y-12 text-slate-900">
                                    {/* Dates */}
                                    <div className="grid grid-cols-2 gap-8">
                                        <div className="space-y-4">
                                            <label className="text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-2">
                                                <Calendar size={12} className="text-indigo-600" /> Check In
                                            </label>
                                            <input 
                                                type="date" 
                                                value={bookingForm.check_in}
                                                onChange={e => setBookingForm({...bookingForm, check_in: e.target.value})}
                                                className="w-full bg-stone-50 border border-stone-100 rounded-2xl h-16 px-6 font-bold text-slate-950 focus:outline-none focus:border-indigo-600 transition-colors" 
                                            />
                                        </div>
                                        <div className="space-y-4">
                                            <label className="text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-2">
                                                <Calendar size={12} className="text-indigo-600" /> Check Out
                                            </label>
                                            <input 
                                                type="date" 
                                                value={bookingForm.check_out}
                                                onChange={e => setBookingForm({...bookingForm, check_out: e.target.value})}
                                                className="w-full bg-stone-50 border border-stone-100 rounded-2xl h-16 px-6 font-bold text-slate-950 focus:outline-none focus:border-indigo-600 transition-colors" 
                                            />
                                        </div>
                                    </div>

                                    {/* Guest Info */}
                                    <div className="space-y-8">
                                        <div className="space-y-4">
                                            <label className="text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-2">
                                                <User size={12} className="text-indigo-600" /> Full Name
                                            </label>
                                            <input 
                                                type="text" 
                                                placeholder="e.g. John Doe"
                                                value={bookingForm.guest_name}
                                                onChange={e => setBookingForm({...bookingForm, guest_name: e.target.value})}
                                                className="w-full bg-stone-50 border border-stone-100 rounded-2xl h-16 px-6 font-bold text-slate-950 focus:outline-none focus:border-indigo-600 transition-colors" 
                                            />
                                        </div>
                                        <div className="grid grid-cols-2 gap-8">
                                            <div className="space-y-4">
                                                <label className="text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-2">
                                                    <Mail size={12} className="text-indigo-600" /> Email Address
                                                </label>
                                                <input 
                                                    type="email" 
                                                    placeholder="john@example.com"
                                                    value={bookingForm.guest_email}
                                                    onChange={e => setBookingForm({...bookingForm, guest_email: e.target.value})}
                                                    className="w-full bg-stone-50 border border-stone-100 rounded-2xl h-16 px-6 font-bold text-slate-950 focus:outline-none focus:border-indigo-600 transition-colors" 
                                                />
                                            </div>
                                            <div className="space-y-4">
                                                <label className="text-[10px] font-black text-slate-400 uppercase tracking-widest flex items-center gap-2">
                                                    <Phone size={12} className="text-indigo-600" /> Phone Number
                                                </label>
                                                <input 
                                                    type="tel" 
                                                    placeholder="+234..."
                                                    value={bookingForm.guest_phone}
                                                    onChange={e => setBookingForm({...bookingForm, guest_phone: e.target.value})}
                                                    className="w-full bg-stone-50 border border-stone-100 rounded-2xl h-16 px-6 font-bold text-slate-950 focus:outline-none focus:border-indigo-600 transition-colors" 
                                                />
                                            </div>
                                        </div>
                                    </div>

                                    {/* Payment Mock */}
                                    <div className="p-10 rounded-[2.5rem] bg-slate-950 text-white space-y-8">
                                        <div className="flex items-center justify-between">
                                            <div className="space-y-1">
                                                <p className="text-[9px] font-black text-slate-500 uppercase tracking-widest">Selected Protocol</p>
                                                <p className="font-bold">Premium Suite: {selectedRoomType?.name}</p>
                                            </div>
                                            <CreditCard size={24} className="text-indigo-400" />
                                        </div>
                                        <div className="space-y-1 pt-6 border-t border-white/10">
                                            <p className="text-[9px] font-black text-slate-500 uppercase tracking-widest">Total Commitment ({nights} nights)</p>
                                            <p className="text-4xl font-black font-mono tracking-tighter">₦{(parseFloat(selectedRoomType?.base_price || 0) * nights).toLocaleString()}</p>
                                        </div>
                                        <Button 
                                            className="w-full h-20 bg-indigo-600 hover:bg-indigo-500 rounded-full font-black text-xs uppercase tracking-[0.2em] shadow-2xl shadow-indigo-600/40 transition-all active:scale-95 flex items-center justify-center gap-3"
                                            onClick={handleReserve}
                                            disabled={bookingLoading}
                                        >
                                            {bookingLoading ? <Loader2 size={20} className="animate-spin" /> : "Initiate Secure Payment"}
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        ) : (
                            <div className="flex flex-col items-center justify-center h-full text-center space-y-12 animate-in zoom-in duration-700">
                                <div className="w-32 h-32 rounded-full bg-emerald-500/10 flex items-center justify-center border border-emerald-500/20">
                                    <CheckCircle2 size={48} className="text-emerald-500" />
                                </div>
                                <div className="space-y-4">
                                    <h2 className="text-5xl font-black tracking-tighter text-slate-950 italic font-serif">Journey Confirmed</h2>
                                    <p className="text-lg text-slate-500 font-medium max-w-sm text-balance">Your sanctuary at {hotel.name} has been secured. A confirmation has been dispatched to {bookingForm.guest_email}.</p>
                                </div>
                                <div className="w-full p-10 bg-stone-50 rounded-[2.5rem] border border-stone-100 flex items-center justify-between">
                                    <div className="text-left">
                                        <p className="text-[9px] font-black text-slate-400 uppercase tracking-widest">Reference Protocol</p>
                                        <p className="font-black text-xl text-slate-950">#CONF-{confirmedRes.reservation_id}</p>
                                    </div>
                                    <div className="text-right">
                                        <p className="text-[9px] font-black text-slate-400 uppercase tracking-widest">Arrival Date</p>
                                        <p className="font-black text-xl text-slate-950">{bookingForm.check_in}</p>
                                    </div>
                                </div>
                                <Button 
                                    className="w-full h-20 bg-slate-950 text-white rounded-full font-black text-xs uppercase tracking-[0.3em] transition-all"
                                    onClick={() => {
                                        setShowDrawer(false);
                                        setConfirmedRes(null);
                                        setSelectedRoomType(null);
                                    }}
                                >
                                    Dismiss Concierge
                                </Button>
                            </div>
                        )}
                    </motion.div>
                    </>
                )}
            </AnimatePresence>

            <div className="p-10 rounded-[3rem] bg-stone-100 border border-stone-200 space-y-8 relative overflow-hidden group">
               <div className="relative z-10">
                 <h4 className="font-black text-xs uppercase tracking-[0.3em] text-slate-950 flex items-center gap-3">
                   <Star size={14} className="mb-0.5" /> Circle Membership
                 </h4>
                 <p className="text-xs text-slate-500 mt-4 leading-relaxed font-medium">Join our global network for early check-ins, bespoke rewards, and private event access.</p>
                 <Button className="w-full mt-8 bg-white border border-stone-200 text-slate-950 hover:bg-slate-950 hover:text-white rounded-full font-black h-12 text-[10px] uppercase tracking-widest transition-all">Enroll Now</Button>
               </div>
            </div>
          </div>
        </div>
      </div>

      {/* Footer - Full Width Edge to Edge */}
      <footer className="bg-white border-t border-stone-100 py-40 mt-60">
        <div className="w-full px-8 md:px-20 lg:px-32 text-center space-y-16">
           <div className="flex items-center justify-center gap-6">
             <div className="w-14 h-14 rounded-full bg-slate-950 flex items-center justify-center font-serif italic text-2xl text-white shadow-xl shadow-slate-950/10">DM</div>
             <span className="font-black text-2xl tracking-tighter uppercase text-slate-950">{hotel.name}</span>
           </div>
           <p className="text-slate-300 text-[11px] font-black uppercase tracking-[0.5em] max-w-2xl mx-auto">© 2026 DM Tech Luxury Portfolio. All standards strictly maintained to provide an unparalleled experience.</p>
        </div>
      </footer>
    </div>
  );
}
