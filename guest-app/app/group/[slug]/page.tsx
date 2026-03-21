'use client';

import * as React from 'react';
import { useParams, useRouter } from 'next/navigation';
import { motion } from 'framer-motion';
import { 
  MapPin, 
  Star, 
  ChevronRight, 
  ArrowRight,
  Globe,
  Facebook,
  Twitter,
  Instagram,
  ArrowDown,
  Sparkles
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import api from '@/lib/api';
import { AnimatePresence } from 'framer-motion';

export default function GroupLandingPage() {
  const { slug } = useParams();
  const router = useRouter();
  const [data, setData] = React.useState<any>(null);
  const [loading, setLoading] = React.useState(true);
  const [activeBranch, setActiveBranch] = React.useState<any>(null);

  React.useEffect(() => {
    const fetchWebsite = async () => {
      try {
        setLoading(true);
        const { data: res } = await api.get(`/api/v1/booking/group/${slug}`);
        setData(res);
        if (res.branches && res.branches.length > 0) {
          setActiveBranch(res.branches[0]);
        }
      } catch (e) {
        console.error("Failed to load group website", e);
      } finally {
        setLoading(false);
      }
    };
    fetchWebsite();
  }, [slug]);

  if (loading) {
    return (
      <div className="min-h-screen bg-slate-950 flex items-center justify-center">
        <div className="w-12 h-12 border-4 border-indigo-500/30 border-t-indigo-500 rounded-full animate-spin"></div>
      </div>
    );
  }

  if (!data) return <div className="min-h-screen bg-slate-950 text-white flex items-center justify-center">Group not found</div>;

  const website = data.group_website;
  const branches = data.branches;

  return (
    <div className="min-h-screen bg-stone-50 text-slate-900 selection:bg-slate-950/10 font-sans overflow-x-hidden">
      {/* Dynamic Navigation */}
      <nav className="fixed top-0 inset-x-0 h-24 bg-white/70 backdrop-blur-3xl border-b border-stone-200/50 z-50 flex items-center px-4 md:px-10 justify-between transition-all">
        <div className="flex items-center gap-6">
          <div className="w-12 h-12 rounded-full bg-slate-950 flex items-center justify-center text-white font-serif italic text-2xl shadow-xl shadow-slate-950/20">
            {website.logo_url ? <img src={website.logo_url} className="w-8 h-8 object-contain invert" /> : "DM"}
          </div>
          <div className="flex flex-col">
            <span className="font-black text-base tracking-tighter uppercase leading-none text-slate-950">{website.title}</span>
            <span className="text-[8px] font-black text-indigo-600 uppercase tracking-[0.3em] mt-1">Premier Selection</span>
          </div>
        </div>
        <div className="hidden lg:flex items-center gap-16 text-[10px] font-black uppercase tracking-[0.4em]">
          <a href="#destinations" className="text-indigo-600 hover:text-slate-950 transition-colors">Portfolios</a>
          <a href="#" className="text-slate-400 hover:text-slate-950 transition-colors">The Narrative</a>
          <a href="#" className="text-slate-400 hover:text-slate-950 transition-colors">Philosophy</a>
          <a href="#" className="text-slate-400 hover:text-indigo-600 transition-colors">Member Area</a>
        </div>
        <Button className="bg-slate-950 text-white hover:bg-indigo-600 rounded-full px-12 h-14 font-black text-xs uppercase tracking-[0.2em] shadow-2xl shadow-slate-950/20 transition-all active:scale-95">
          Book Stay
        </Button>
      </nav>

      {/* Hero Section - High Contrast Minimal Full-Bleed */}
      <section className="relative min-h-[110vh] flex items-center justify-start pt-24 overflow-hidden bg-white">
        <div className="absolute inset-0 z-0">
          <div className="absolute top-0 right-0 w-[60%] h-[60%] bg-indigo-500/5 rounded-full blur-[150px] animate-pulse" />
          <div className="absolute bottom-0 left-0 w-[40%] h-[40%] bg-emerald-500/5 rounded-full blur-[120px] animate-pulse" style={{ animationDelay: '2s' }} />
          <motion.div 
            initial={{ scale: 1.05 }}
            animate={{ scale: 1 }}
            transition={{ duration: 15, repeat: Infinity, repeatType: 'reverse' }}
            className="w-full h-full"
          >
            <img 
              src={website.banner_url || 'https://images.unsplash.com/photo-1542314831-068cd1dbfeeb?q=80&w=2070&auto=format&fit=crop'} 
              className="w-full h-full object-cover grayscale-[0.5] opacity-80"
              alt={website.title}
            />
          </motion.div>
          <div className="absolute inset-0 bg-stone-50/10"></div>
          <div className="absolute inset-0 bg-gradient-to-r from-stone-50 via-transparent to-transparent"></div>
          <div className="absolute inset-x-0 bottom-0 h-64 bg-gradient-to-t from-stone-50 to-transparent"></div>
        </div>

        <div className="w-full px-8 md:px-20 lg:px-32 relative z-10 text-left space-y-16">
          <motion.div
            initial={{ opacity: 0, x: -40 }}
            animate={{ opacity: 1, x: 0 }}
            transition={{ duration: 1.2, ease: [0.16, 1, 0.3, 1] }}
            className="space-y-12 max-w-7xl"
          >
            <div className="flex items-center gap-6">
               <div className="h-[2px] w-16 bg-indigo-600"></div>
               <span className="text-[11px] font-black uppercase tracking-[0.5em] text-indigo-600/60">Exquisite Standard</span>
            </div>
            <h1 className="text-6xl md:text-8xl xl:text-9xl font-black tracking-tighter leading-[0.85] text-slate-950">
              {website.title.split(' ').map((word: string, i: number) => (
                <React.Fragment key={i}>
                  <span className={i % 2 === 1 ? "md:block text-slate-600 font-serif italic font-normal md:ml-40 lg:ml-60" : ""}>
                    {word}{' '}
                  </span>
                </React.Fragment>
              ))}
            </h1>
            <p className="text-xl md:text-2xl text-slate-500 font-medium max-w-2xl leading-relaxed border-l-2 border-indigo-600 pl-12 italic">
              {website.description || "Refining the essence of hospitality through timeless design and unparalleled service across our curated collection."}
            </p>
          </motion.div>

          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            transition={{ delay: 0.8 }}
            className="flex flex-col sm:flex-row items-center justify-start gap-12"
          >
            <Button 
                size="lg" 
                className="h-20 px-20 bg-slate-950 hover:bg-slate-800 text-white rounded-full text-sm font-black uppercase tracking-widest shadow-2xl shadow-slate-950/30 group transition-all"
                onClick={() => document.getElementById('destinations')?.scrollIntoView({ behavior: 'smooth' })}
            >
              Discover Portfolio
              <ArrowRight className="ml-3 group-hover:translate-x-2 transition-transform" />
            </Button>
            <Button size="lg" variant="ghost" className="h-20 px-12 text-slate-950 hover:bg-stone-100 rounded-full text-sm font-black uppercase tracking-widest border border-stone-200">
               Our Heritage
            </Button>
          </motion.div>
        </div>
      </section>

      {/* Immersive Destination Selection - Full Width */}
      <section id="destinations" className="py-60 bg-stone-50">
        <div className="w-full px-8 md:px-20 lg:px-32">
          <div className="grid grid-cols-1 lg:grid-cols-2 gap-32 items-start mb-40">
            <div className="space-y-12">
              <div className="flex items-center gap-6">
                 <div className="h-20 w-[2px] bg-slate-950"></div>
                 <h2 className="text-8xl md:text-[11rem] font-black tracking-tighter leading-none text-slate-950 capitalize">
                   The <br />Collection
                 </h2>
              </div>
              <p className="text-slate-500 text-3xl font-medium leading-relaxed max-w-2xl border-stone-200">
                A selection of architectural landmarks and bespoke retreats, each defined by its own distinct character and impeccable standard.
              </p>
            </div>
            <div className="grid grid-cols-3 gap-20 pt-20">
                 <div className="space-y-4">
                   <div className="text-6xl font-serif italic text-slate-950">{branches.length}</div>
                   <div className="text-[10px] font-black text-slate-400 uppercase tracking-[0.4em]">Global Sites</div>
                 </div>
                 <div className="space-y-4">
                   <div className="text-6xl font-serif italic text-slate-950">07+</div>
                   <div className="text-[10px] font-black text-slate-400 uppercase tracking-[0.4em]">Cities</div>
                 </div>
                 <div className="space-y-4">
                   <div className="text-6xl font-serif italic text-slate-950">4.9</div>
                   <div className="text-[10px] font-black text-slate-400 uppercase tracking-[0.4em]">Guest Score</div>
                 </div>
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-16 lg:gap-24">
            {branches.map((branch: any, i: number) => (
              <motion.div
                key={branch.id}
                initial={{ opacity: 0, y: 60 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true }}
                transition={{ delay: i * 0.1, duration: 1.2, ease: [0.16, 1, 0.3, 1] }}
                className="group relative cursor-pointer"
                onClick={() => router.push(`/group/${slug}/branch/${branch.slug}`)}
              >
                <div className="relative aspect-[16/10] overflow-hidden bg-stone-200 shadow-sm transition-all duration-1000 group-hover:shadow-[0_40px_100px_-20px_rgba(0,0,0,0.15)]">
                  <img 
                    src={branch.image_url || 'https://images.unsplash.com/photo-1566073771259-6a8506099945?q=80&w=2070&auto=format&fit=crop'} 
                    className="w-full h-full object-cover grayscale-[0.2] transition-transform duration-[2s] group-hover:scale-105"
                    alt={branch.name}
                  />
                  <div className="absolute inset-x-0 bottom-0 h-2/3 bg-gradient-to-t from-slate-950 via-slate-950/20 to-transparent opacity-90 group-hover:opacity-60 transition-opacity duration-700"></div>
                  
                  <div className="absolute inset-0 flex flex-col items-center justify-center text-center p-12 transition-transform duration-1000 ease-[0.16,1,0.3,1]">
                    <div className="space-y-8">
                      <div className="flex items-center justify-center gap-4 text-white/60 text-[10px] font-black uppercase tracking-[0.5em]">
                        <MapPin size={14} className="text-indigo-400" />
                        {branch.address?.split(',')[0]}
                      </div>
                      <h3 className="text-3xl md:text-5xl font-black text-white tracking-tighter leading-none font-serif italic font-normal">
                        {branch.name}
                      </h3>
                      <div className="overflow-hidden flex justify-center">
                        <p className="text-stone-300 text-lg font-medium opacity-0 -translate-y-8 group-hover:opacity-100 group-hover:translate-y-0 transition-all duration-700 delay-100 max-w-sm">
                          {branch.description || "A sanctuary of comfort and elegance."}
                        </p>
                      </div>
                    </div>
                  </div>
                </div>
                
                <div className="mt-12 flex items-center justify-between border-b border-stone-200 pb-12 group-hover:border-indigo-600 transition-colors duration-500">
                   <div className="flex flex-col gap-1">
                      <span className="text-[11px] font-black text-slate-400 uppercase tracking-[0.4em]">Signature Property</span>
                      <span className="text-2xl font-bold text-slate-950 italic font-serif">{branch.name}</span>
                   </div>
                   <div className="w-16 h-16 rounded-full border border-stone-200 flex items-center justify-center group-hover:bg-indigo-600 group-hover:text-white group-hover:border-indigo-600 transition-all duration-500">
                      <ChevronRight size={24} />
                   </div>
                </div>
              </motion.div>
            ))}
          </div>

          <div className="mt-72 flex flex-col items-center text-center space-y-16 py-32 border-y border-stone-200/50 relative overflow-hidden">
             <div className="absolute left-0 top-0 w-full h-[1px] bg-gradient-to-r from-transparent via-slate-950/10 to-transparent"></div>
             <div className="absolute left-0 bottom-0 w-full h-[1px] bg-gradient-to-r from-transparent via-slate-950/10 to-transparent"></div>
             
             <div className="w-32 h-32 rounded-full bg-slate-950 flex items-center justify-center text-white relative z-10 shadow-2xl shadow-slate-950/20">
               <Sparkles size={48} />
             </div>
             <h4 className="text-6xl md:text-8xl font-black tracking-tight leading-tight text-slate-950 italic font-serif font-normal max-w-5xl">
               Experience the absolute <br />pinnacle of hospitality.
             </h4>
             <p className="text-2xl text-slate-500 max-w-3xl leading-relaxed font-medium">
               Our global concierge remains at your disposal to orchestrate a journey defined by elegance, privacy, and impeccable service.
             </p>
             <div className="flex flex-col sm:flex-row items-center gap-16 pt-12">
                 <Button variant="link" className="text-slate-950 font-black text-[11px] uppercase tracking-[0.4em] h-auto p-0 border-b-2 border-slate-950/20 hover:border-slate-950 transition-all pb-2">Engage with Concierge</Button>
                 <div className="flex items-center gap-12 text-slate-300 text-lg font-black uppercase tracking-widest">
                    <Facebook size={24} className="hover:text-slate-950 cursor-pointer transition-colors" />
                    <Twitter size={24} className="hover:text-slate-950 cursor-pointer transition-colors" />
                    <Instagram size={24} className="hover:text-slate-950 cursor-pointer transition-colors" />
                 </div>
             </div>
          </div>
        </div>
      </section>

      {/* Footer - Full Width */}
      <footer className="py-40 bg-white">
        <div className="w-full px-8 md:px-20 lg:px-32 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-24 items-start">
          <div className="space-y-10">
            <div className="flex items-center gap-6">
              <div className="w-12 h-12 rounded-full bg-slate-950 flex items-center justify-center font-serif italic text-2xl text-white">DM</div>
              <span className="font-extrabold text-2xl tracking-tighter text-slate-950 uppercase">{website.title}</span>
            </div>
            <p className="text-slate-400 text-[10px] leading-relaxed font-black uppercase tracking-[0.4em]">Refining luxury across the globe. Part of the DM Tech Portfolio.</p>
          </div>
          
          <div className="space-y-8">
            <h5 className="text-[11px] font-black uppercase tracking-[0.4em] text-slate-950">Portfolios</h5>
            <div className="flex flex-col gap-4">
              {branches.slice(0, 4).map((b: any) => (
                <a key={b.id} href="#" className="text-[10px] font-bold text-slate-400 hover:text-slate-950 transition-colors uppercase tracking-[0.3em]">{b.name}</a>
              ))}
            </div>
          </div>

          <div className="space-y-8">
            <h5 className="text-[11px] font-black uppercase tracking-[0.4em] text-slate-950">Inquiries</h5>
            <div className="flex flex-col gap-4">
              <a href="#" className="text-[10px] font-bold text-slate-400 hover:text-slate-950 transition-colors uppercase tracking-[0.3em]">Global Press</a>
              <a href="#" className="text-[10px] font-bold text-slate-400 hover:text-slate-950 transition-colors uppercase tracking-[0.3em]">Bespoke Partnerships</a>
              <a href="#" className="text-[10px] font-bold text-slate-400 hover:text-slate-950 transition-colors uppercase tracking-[0.3em]">The Elite Circle</a>
            </div>
          </div>

          <div className="space-y-8">
            <h5 className="text-[11px] font-black uppercase tracking-[0.4em] text-slate-950">Manifesto</h5>
            <div className="flex flex-col gap-4">
              <a href="#" className="text-[10px] font-bold text-slate-400 hover:text-slate-950 transition-colors uppercase tracking-[0.3em]">Privacy Protocol</a>
              <a href="#" className="text-[10px] font-bold text-slate-400 hover:text-slate-950 transition-colors uppercase tracking-[0.3em]">Sustainability</a>
              <a href="#" className="text-[10px] font-bold text-slate-400 hover:text-slate-950 transition-colors uppercase tracking-[0.3em]">Legal Heritage</a>
            </div>
          </div>
        </div>
        <div className="w-full px-8 md:px-20 lg:px-32 mt-40 pt-16 border-t border-stone-100 flex flex-col md:flex-row justify-between items-center gap-10 text-[10px] font-black text-slate-300 uppercase tracking-[0.5em]">
           <span>© 2026 {website.title} Hospitality Group</span>
           <span>Crafting Timeless Moments</span>
        </div>
      </footer>
    </div>
  );
}

const cn = (...classes: any[]) => classes.filter(Boolean).join(' ');
