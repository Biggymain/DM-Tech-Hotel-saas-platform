'use client';

import * as React from 'react';
import { motion } from 'framer-motion';
import { 
  Building2, MapPin, ArrowRight, Star, Globe, 
  Phone, Mail, Facebook, Twitter, Instagram, 
  Sparkles, ChevronRight
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

interface DesignSettings {
  template_id: string;
  hero_alignment: string;
  font_family: string;
  font_weight: string;
  card_style: string;
  button_style: string;
  primary_color: string;
  secondary_color: string;
  accent_color: string;
  // Advanced Colors
  background_color?: string;
  text_color?: string;
  navbar_bg?: string;
  footer_bg?: string;
  card_bg?: string;
  // Granular Typography
  heading_font?: string;
  body_font?: string;
  heading_size?: 'sm' | 'md' | 'lg' | 'xl' | '2xl' | '3xl';
  body_size?: 'sm' | 'md' | 'lg';
  body_weight?: string;
  // Layout & Components
  container_width?: 'narrow' | 'medium' | 'wide' | 'full';
  hero_height?: 'low' | 'medium' | 'high' | 'full';
  card_radius?: 'none' | 'sm' | 'md' | 'lg' | 'full';
  button_padding?: 'compact' | 'normal' | 'spacious';
  glass_intensity?: 'none' | 'low' | 'medium' | 'high';
  animation_speed?: 'none' | 'slow' | 'normal' | 'fast';
}

interface Branding {
  title: string;
  description: string;
  logo_url?: string;
  banner_url?: string;
  email?: string;
  phone?: string;
  address?: string;
}

interface Branch {
  id: string | number;
  name: string;
  slug: string;
  description: string;
  image_url?: string;
  address?: string;
  design_settings?: any;
}

interface WebsiteTemplateProps {
  settings: DesignSettings;
  branding: Branding;
  branches: Branch[];
  isPreview?: boolean;
}

// ─── Helper: Google Font URL ──────────────────────────────────────────────────
const getFontUrl = (font: string) => {
  if (font === 'serif') return 'https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;700;900&display=swap';
  if (font === 'mono') return 'https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;700;800&display=swap';
  return 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap';
};

const getFontFamily = (font: string) => {
  if (font === 'serif') return "'Playfair Display', serif";
  if (font === 'mono') return "'JetBrains Mono', monospace";
  return "'Inter', sans-serif";
};

// ─── Template: Modern ─────────────────────────────────────────────────────────── (Refined)
const ModernTemplate = ({ settings, branding, branches, isPreview }: WebsiteTemplateProps) => {
  const p = 'var(--primary)';
  const s = 'var(--secondary)';
  const a = 'var(--accent)';
  const fontWeight = 'var(--font-weight)';
  const align = settings.hero_alignment || 'center';
  const btnStyle = 'btn-custom';
  const cardStyle = 'card-effect';

  return (
    <div className="bg-[#050810] text-white min-h-full overflow-x-hidden selection:bg-primary/30" style={{ fontFamily: getFontFamily(settings.font_family) }}>
      <link rel="stylesheet" href={getFontUrl(settings.font_family)} />
      
      <div className="fixed top-0 left-0 w-full h-full -z-10 pointer-events-none">
        <div className="absolute top-1/4 left-1/4 w-[500px] h-[500px] blur-[120px] rounded-full opacity-10" style={{ backgroundColor: p }} />
        <div className="absolute bottom-1/4 right-1/4 w-[500px] h-[500px] blur-[120px] rounded-full opacity-10" style={{ backgroundColor: s }} />
      </div>

      <nav className="h-24 flex items-center justify-between px-12 sticky top-0 z-[100] transition-all border-b border-white/5" style={{ backgroundColor: 'var(--navbar-bg)', backdropFilter: 'blur(10px)' }}>
        <div className="flex items-center gap-4">
          {branding.logo_url ? (
            <div className="w-10 h-10 rounded-xl overflow-hidden shadow-2xl transition-transform duration-500">
               <img src={branding.logo_url} alt="Logo" className="w-full h-full object-contain" />
            </div>
          ) : (
            <div className="w-10 h-10 bg-white/10 backdrop-blur-xl rounded-xl flex items-center justify-center border border-white/20 shadow-2xl">
               <Building2 size={20} className="text-white" />
            </div>
          )}
          <span className="font-black text-[9px] @sm:text-xs @md:text-lg tracking-tighter hidden xs:block uppercase italic">
            {branding.title}
          </span>
        </div>
        
        <div className="flex items-center gap-6 @lg:gap-10">
      <div className="hidden @lg:flex gap-10 text-[10px] font-black uppercase tracking-[0.4em] text-white/30">
            <span className="hover:text-white cursor-pointer transition-colors">Portfolios</span>
            <span className="hover:text-white cursor-pointer transition-colors">Excursions</span>
          </div>
          
          <div className="hidden @sm:block">
            <Button 
              className={`${btnStyle} h-12 shadow-2xl hover:scale-105 active:scale-95 text-[10px] tracking-widest`}
              style={{ backgroundColor: p, color: 'white' }}
            >
              BOOK EXPERIENCE
            </Button>
          </div>


        </div>
      </nav>

      <section className="hero-section relative flex items-center overflow-hidden">
        <div className="absolute inset-0 -z-10 bg-black">
          <div className="absolute inset-0 bg-gradient-to-b from-black/80 via-black/10 to-[#050810] z-10" />
          {branding.banner_url ? (
            <img src={branding.banner_url} alt="Banner" className="w-full h-full object-cover scale-105 animate-in fade-in duration-1000 opacity-60" />
          ) : (
            <div className="w-full h-full bg-slate-900" />
          )}
        </div>

        <div className={`container mx-auto px-6 @md:px-12 relative z-20 ${
          align === 'left' ? 'text-left' : align === 'right' ? 'text-right' : 'text-center'
        }`}>
          <motion.div
            initial={{ opacity: 0, y: 30 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.8 }}
            className="max-w-4xl"
            style={{ margin: align === 'center' ? '0 auto' : align === 'right' ? '0 0 0 auto' : '0 auto 0 0' }}
          >
            <div className={`flex items-center gap-6 font-black text-[10px] tracking-[0.5em] uppercase mb-8 ${
              align === 'center' ? 'justify-center' : align === 'right' ? 'justify-end' : 'justify-start'
            }`} style={{ color: p }}>
              <div className="flex items-center gap-2">
                <div className="h-px w-8 bg-current opacity-30" />
                Direct Booking Portal
              </div>
              <div className="hidden @sm:flex items-center gap-6 opacity-40">
                {branding.phone && <span>{branding.phone}</span>}
                {branding.email && <span>{branding.email}</span>}
              </div>
            </div>
            
            <h1 
              className="text-3xl @sm:text-5xl @md:text-[10rem] leading-[0.8] tracking-tighter mb-6 @md:mb-12 bg-gradient-to-br from-white via-white/80 to-white/20 bg-clip-text text-transparent italic"
              style={{ fontSize: 'var(--heading-size)' }}
            >
              Beyond the <br />
              <span className="text-primary">Horizon</span>
            </h1>
            
            <p className={`text-sm @md:text-xl text-white/60 leading-relaxed mb-8 @md:mb-16 max-w-3xl font-medium ${
              align === 'center' ? 'mx-auto' : align === 'right' ? 'ml-auto' : ''
            }`} style={{ fontSize: 'var(--body-size)' }}>
              {branding.description || 'Discover a symphony of architectural excellence and legendary hospitality across our world-class hotels.'}
            </p>

            <div className={`flex flex-wrap items-center gap-8 ${
              align === 'center' ? 'justify-center' : align === 'right' ? 'justify-end' : ''
            }`}>
                <Button 
                  size="lg" 
                  onClick={() => document.getElementById('branches')?.scrollIntoView({ behavior: 'smooth' })}
                  className={`${btnStyle} h-16 @md:h-20 px-10 @md:px-16 font-black text-xs @md:text-sm tracking-widest shadow-2xl hover:scale-110 transition-all border-2 border-white/20`}
                  style={{ backgroundColor: p, color: 'white' }}
                >
                  EXPLORE DESTINATIONS
                </Button>
            </div>
          </motion.div>
        </div>
      </section>

      <section id="branches" className="py-20 @md:py-40 border-t border-white/5">
        <div className="max-w-7xl mx-auto px-6 @md:px-12">
          <div className="mb-10 @md:mb-24 flex flex-col @md:flex-row @md:items-end justify-between gap-8 @md:gap-12">
            <div className="space-y-4 @md:space-y-6">
              <h2 className="text-4xl @sm:text-6xl @md:text-8xl font-black tracking-tighter italic leading-none">Curated <br/>Selection</h2>
              <div className="h-1.5 w-24 @md:h-2 @md:w-32 bg-primary animate-pulse" style={{ backgroundColor: p }} />
            </div>
            <p className="text-white/30 text-base @md:text-xl max-w-lg font-medium leading-relaxed italic border-l-4 border-white/10 pl-6 @md:pl-8">
               Our collection represents the pinnacle of luxury, meticulously chosen to provide an escape that transcends the ordinary.
            </p>
          </div>

          <div className="grid grid-cols-1 @md:grid-cols-2 @lg:grid-cols-3 gap-12">
            {branches.map((branch, i) => (
              <motion.div
                key={branch.id}
                initial={{ opacity: 0, scale: 0.95 }}
                whileInView={{ opacity: 1, scale: 1 }}
                viewport={{ once: true }}
                transition={{ delay: i * 0.15, duration: 0.8 }}
              >
                <Card className="card-effect group relative overflow-hidden border-0 shadow-none hover:-translate-y-4 transition-all duration-700 bg-transparent rounded-[2rem] @md:rounded-[var(--radius)]">
                  <div className="aspect-square w-full relative overflow-hidden rounded-[2rem] @md:rounded-[var(--radius)]">
                    {branch.image_url ? (
                      <img src={branch.image_url} alt={branch.name} className="w-full h-full object-cover transition-transform duration-[2s] group-hover:scale-125 brightness-75 group-hover:brightness-100" />
                    ) : (
                      <div className="w-full h-full bg-zinc-900 flex items-center justify-center text-white/5 font-black italic text-4xl">DESTINATION</div>
                    )}
                    <div className="absolute inset-0 bg-gradient-to-t from-black via-transparent to-transparent opacity-80" />
                    <div className="absolute top-8 right-8 flex gap-1.5 bg-black/40 backdrop-blur-md p-3 rounded-2xl border border-white/10">
                      {[1,2,3,4,5].map(s => <Star key={s} size={12} className="fill-yellow-500 text-yellow-500" />)}
                    </div>
                    <div className="absolute bottom-10 left-10 right-10">
                       <h3 className="text-4xl font-black text-white mb-4 leading-none tracking-tighter italic uppercase">{branch.name}</h3>
                       <div className="flex items-center gap-3 text-[10px] text-white/50 uppercase font-black tracking-[0.2em] mb-8">
                          <MapPin size={16} style={{ color: p }} />
                          {branch.address}
                       </div>
                       <Button 
                        onClick={() => !isPreview && (window.location.href = `/${branch.slug}/reserve`)}
                        className={`${btnStyle} w-full h-16 font-black text-xs tracking-[0.2em] bg-white text-black hover:bg-white/90 shadow-2xl`}
                      >
                        RESERVE SUITE
                        <ArrowRight size={18} className="ml-3 group-hover:translate-x-2 transition-transform" />
                      </Button>
                    </div>
                  </div>
                </Card>
              </motion.div>
            ))}
          </div>
        </div>
      </section>

      <RefinedFooter branding={branding} p={p} />
    </div>
  );
};

// ─── Shared Component: RefinedFooter ──────────────────────────────────────────
const RefinedFooter = ({ branding, p }: { branding: Branding; p: string }) => (
  <footer className="py-24 border-t border-white/5 relative overflow-hidden" style={{ backgroundColor: 'var(--footer-bg)' }}>
    <div className="absolute top-0 left-1/4 w-[400px] h-[400px] bg-primary/5 blur-[120px] rounded-full pointer-events-none" style={{ backgroundColor: p + '10' }} />
    <div className="max-w-7xl mx-auto px-6 @md:px-12 relative z-10">
      <div className="grid grid-cols-1 @md:grid-cols-12 gap-16 mb-20">
        <div className="@md:col-span-5 space-y-8">
          <div className="flex items-center gap-4">
            {branding.logo_url ? (
              <img src={branding.logo_url} alt="Logo" className="h-10 w-auto object-contain" />
            ) : (
              <div className="h-12 w-12 rounded-2xl flex items-center justify-center text-white font-black text-2xl shadow-2xl" style={{ backgroundColor: p }}>
                {branding.title.charAt(0)}
              </div>
            )}
            <span className="font-bold text-2xl tracking-tighter @md:block">{branding.title}</span>
          </div>
          <p className="text-white/40 text-sm leading-relaxed max-w-md font-medium">
            {branding.description || 'Experience the pinnacle of hospitality across our global portfolio of boutique stays and premium resorts.'}
          </p>
          <div className="flex gap-6">
            <Facebook size={20} className="text-white/20 hover:text-white transition-colors cursor-pointer" />
            <Twitter size={20} className="text-white/20 hover:text-white transition-colors cursor-pointer" />
            <Instagram size={20} className="text-white/20 hover:text-white transition-colors cursor-pointer" />
          </div>
        </div>
        
        <div className="@md:col-span-3 space-y-8">
          <h4 className="text-[10px] font-black uppercase tracking-[0.4em] text-white/30">Connect With Us</h4>
          <div className="space-y-6 text-sm text-white/50 font-medium">
            {branding.phone && (
              <div className="flex items-center gap-4 group cursor-pointer">
                <div className="w-10 h-10 rounded-xl bg-white/5 flex items-center justify-center group-hover:bg-primary/10 transition-colors">
                  <Phone size={16} style={{ color: p }} />
                </div>
                {branding.phone}
              </div>
            )}
            {branding.email && (
              <div className="flex items-center gap-4 group cursor-pointer">
                <div className="w-10 h-10 rounded-xl bg-white/5 flex items-center justify-center group-hover:bg-primary/10 transition-colors">
                  <Mail size={16} style={{ color: p }} />
                </div>
                {branding.email}
              </div>
            )}
          </div>
        </div>

        <div className="@md:col-span-4 space-y-8">
          <h4 className="text-[10px] font-black uppercase tracking-[0.4em] text-white/30">Our Location</h4>
          {branding.address && (
            <div className="flex items-start gap-4 p-6 rounded-3xl bg-white/5 border border-white/5">
              <MapPin size={20} style={{ color: p }} className="mt-1 flex-shrink-0" />
              <p className="text-sm leading-relaxed text-white/60 font-medium">{branding.address}</p>
            </div>
          )}
        </div>
      </div>
      
      <div className="pt-12 border-t border-white/5 flex flex-col @md:flex-row justify-between items-center gap-6 text-[10px] text-white/20 font-bold uppercase tracking-[0.3em]">
        <p>© 2026 {branding.title}. ALL RIGHTS RESERVED.</p>
        <div className="flex gap-8 items-center">
          <span className="hover:text-white cursor-pointer transition-colors">Privacy Policy</span>
          <span className="hover:text-white cursor-pointer transition-colors">Terms of Service</span>
          <div className="flex items-center gap-2">
            <Globe size={14} />
            <span>EN / GLOBAL</span>
          </div>
        </div>
      </div>
    </div>
  </footer>
);

// ─── Template: Classic ───────────────────────────────────────────────────────── (Refined)
const ClassicTemplate = ({ settings, branding, branches, isPreview }: WebsiteTemplateProps) => {
  const p = 'var(--primary)';
  const s = 'var(--secondary)';
  const a = 'var(--accent)';
  const align = settings?.hero_alignment || 'center';
  const fontWeight = 'var(--font-weight)';
  const btnStyle = 'btn-custom';

  return (
    <div className="relative overflow-x-hidden">
      <nav className="h-24 border-b border-white/5 flex items-center justify-between px-12 sticky top-0 z-[100] transition-all" style={{ backgroundColor: 'var(--navbar-bg)', backdropFilter: 'blur(10px)' }}>
        <div className="flex items-center gap-6">
          {branding.logo_url ? (
            <img src={branding.logo_url} alt="Logo" className="h-10 w-auto object-contain" />
          ) : (
            <div className="h-12 w-12 rounded-xl flex items-center justify-center text-white font-black text-2xl shadow-xl" style={{ backgroundColor: p }}>
              {branding.title.charAt(0)}
            </div>
          )}
          <span className="font-black text-[9px] @sm:text-xs @md:text-xl tracking-tight uppercase italic">{branding.title}</span>
        </div>

        <div className="flex items-center gap-10">
           <div className="hidden @sm:block">
             <Button 
               onClick={() => document.getElementById('branches')?.scrollIntoView({ behavior: 'smooth' })}
               className={`${btnStyle} h-12 px-10 font-black text-[10px] tracking-[0.2em] shadow-2xl transition-all`} 
               style={{ backgroundColor: p, color: 'white' }}
             >
               CHECK AVAILABILITY
             </Button>
           </div>
           

        </div>
      </nav>

      {/* HERO */}
      <section className="hero-section relative flex items-center justify-center overflow-hidden">
        <div className="absolute inset-0 z-0">
          <div className="absolute inset-0 bg-gradient-to-b from-black/80 via-black/40 to-[#050810] z-10" />
          {branding.banner_url ? (
            <img src={branding.banner_url} alt={branding.title} className="w-full h-full object-cover scale-105" />
          ) : (
            <div className="w-full h-full bg-slate-900" />
          )}
        </div>

        <div className={`max-w-7xl mx-auto relative z-20 px-6 @md:px-12 ${
          align === 'left' ? 'text-left' : align === 'right' ? 'text-right' : 'text-center'
        }`}>
          <motion.div initial={{ opacity: 0, y: 50 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 1 }} className="max-w-5xl mx-auto">
            <div className={`flex items-center gap-8 font-black text-[11px] tracking-[0.6em] uppercase mb-12 ${
              align === 'center' ? 'justify-center' : align === 'right' ? 'justify-end' : 'justify-start'
            }`} style={{ color: p }}>
              <div className="flex items-center gap-4">
                <Sparkles size={18} />
                Legacy of Hospitality
                <div className="h-px w-12 bg-current opacity-40" />
              </div>
            </div>
            <h1 className="text-3xl @sm:text-5xl @md:text-[8rem] font-black tracking-tighter mb-6 @md:mb-12 leading-[0.85] uppercase italic" style={{ fontSize: 'var(--heading-size)' }}>
              {branding.title}
            </h1>
            <p className={`max-w-3xl text-sm @md:text-xl text-white/50 leading-relaxed mb-8 @md:mb-16 font-medium ${
              align === 'center' ? 'mx-auto' : align === 'right' ? 'ml-auto' : ''
            }`} style={{ fontSize: 'var(--body-size)' }}>
              {branding.description || 'Welcome to a world where heritage meets modern luxury, creating an unforgettable stay experience.'}
            </p>
            <div className={`flex flex-wrap items-center gap-6 ${
              align === 'center' ? 'justify-center' : align === 'right' ? 'justify-end' : ''
            }`}>
              <Button 
                size="lg" 
                onClick={() => document.getElementById('branches')?.scrollIntoView({ behavior: 'smooth' })}
                className={`${btnStyle} h-16 @md:h-20 px-12 @md:px-16 font-black text-xs @md:text-sm tracking-widest shadow-2xl transition-all hover:scale-105`} 
                style={{ backgroundColor: p, color: 'white' }}
              >
                EXPLORE PORTFOLIO
              </Button>
            </div>
          </motion.div>
        </div>
      </section>

      {/* BRANCHES */}
      <section id="branches" className="py-20 @md:py-40">
        <div className="max-w-7xl mx-auto px-6 @md:px-12">
          <div className="grid grid-cols-1 @lg:grid-cols-2 gap-16">
            {branches.map((branch, idx) => (
              <Card key={branch.id} className="card-effect group overflow-hidden bg-white/[0.03] border-white/5 hover:border-primary/20 transition-all duration-700 rounded-3xl @md:rounded-[var(--radius)]">
                <CardContent className="p-0 flex flex-col @md:flex-row">
                  <div className="w-full relative overflow-hidden aspect-square">
                    <img src={branch.image_url} alt={branch.name} className="w-full h-full object-cover transition-transform duration-[1.5s] group-hover:scale-125" />
                    <div className="absolute inset-0 bg-black/10" />
                  </div>
                  <div className="p-8 @md:p-12 flex-1 flex flex-col justify-between">
                    <div>
                      <div className="flex gap-1.5 mb-6">
                        {[1,2,3,4,5].map(s => <Star key={s} size={12} className="fill-yellow-500 text-yellow-500 opacity-80" />)}
                      </div>
                      <h3 className="text-4xl font-black mb-4 uppercase italic tracking-tighter leading-none">{branch.name}</h3>
                      <div className="flex items-center gap-3 text-white/30 text-[10px] uppercase font-black tracking-[0.3em] mb-8">
                        <MapPin size={16} style={{ color: p }} />
                        {branch.address}
                      </div>
                      <p className="text-white/40 text-base leading-relaxed line-clamp-3 font-medium">{branch.description}</p>
                    </div>
                    <Button 
                      onClick={() => !isPreview && (window.location.href = `/${branch.slug}/reserve`)}
                      className={`${btnStyle} w-full h-16 bg-white text-black font-black text-xs tracking-widest mt-12 hover:bg-white/90 shadow-2xl transition-all`}
                    >
                      SECURE RESERVATION
                    </Button>
                  </div>
                </CardContent>
              </Card>
            ))}
          </div>
        </div>
      </section>

      <RefinedFooter branding={branding} p={p} />
    </div>
  );
};

// ─── Template: Minimalist ──────────────────────────────────────────────────── (Refined)
const MinimalistTemplate = ({ settings, branding, branches, isPreview }: WebsiteTemplateProps) => {
  const p = 'var(--primary)';
  const s = 'var(--secondary)';
  const a = 'var(--accent)';
  const align = settings?.hero_alignment || 'center';
  const fontWeight = 'var(--font-weight)';
  const btnStyle = 'btn-custom';

  return (
    <div className="relative overflow-x-hidden min-h-screen" style={{ backgroundColor: 'var(--bg-color)', color: 'var(--text-color)' }}>
      <nav className="h-24 flex items-center justify-between px-12 sticky top-0 z-[100] transition-all border-b border-white/5" style={{ backgroundColor: 'var(--navbar-bg)', backdropFilter: 'blur(10px)' }}>
        <div className="flex items-center gap-6">
          {branding.logo_url && <img src={branding.logo_url} alt="Logo" className="h-8 w-auto grayscale opacity-50 hover:opacity-100 transition-opacity" />}
          <span className="font-light tracking-[0.4em] uppercase text-[8px] @md:text-[10px] opacity-70 italic">{branding.title}</span>
        </div>
        
        <div className="flex items-center gap-12">
          <div className="hidden @lg:flex gap-12 text-[10px] uppercase tracking-[0.4em] font-medium opacity-50">
            <span className="hover:text-current cursor-pointer transition-colors border-b border-transparent hover:border-current pb-1">Archive</span>
            <span className="hover:text-current cursor-pointer transition-colors border-b border-transparent hover:border-current pb-1">Inquiry</span>
          </div>


        </div>
      </nav>

      <section className="hero-section flex items-center justify-center overflow-hidden">
        <div className="max-w-7xl mx-auto px-6 @md:px-12">
          <div className={`space-y-16 ${align === 'center' ? 'text-center' : align === 'right' ? 'text-right' : 'text-left'}`}>
            <div className={`flex items-center gap-4 text-[10px] font-black uppercase tracking-[0.8em] opacity-40 ${
              align === 'center' ? 'justify-center' : align === 'right' ? 'justify-end' : ''
            }`}>
               <Sparkles size={14} style={{ color: p }} />
               VOLUME _ 01
            </div>
            <h2 className="text-3xl @sm:text-5xl @md:text-[10rem] tracking-tighter leading-[0.8] uppercase italic" style={{ fontSize: 'var(--heading-size)', fontWeight: 'var(--font-weight)' }}>
              {branding.title.split(' ').map((word, i) => (
                <span key={i} className="block">{word}</span>
              ))}
            </h2>
            <div className={`h-px w-24 @md:w-32 bg-current opacity-10 ${align === 'center' ? 'mx-auto' : align === 'right' ? 'ml-auto' : ''}`} />
            <p className={`text-sm @md:text-xl opacity-50 font-light max-w-2xl leading-relaxed ${align === 'center' ? 'mx-auto' : align === 'right' ? 'ml-auto' : ''}`} style={{ fontSize: 'var(--body-size)' }}>
              {branding.description || 'A minimalist sanctuary where every detail is a testament to pure architectural form.'}
            </p>
            <Button 
              onClick={() => document.getElementById('branches')?.scrollIntoView({ behavior: 'smooth' })}
              className={`${btnStyle} h-16 @md:h-20 px-10 @md:px-16 text-[10px] uppercase font-black tracking-[0.4em] bg-white text-black hover:bg-black hover:text-white transition-all shadow-2xl invert dark:invert-0`}
            >
               Begin Experience
            </Button>
          </div>
        </div>
      </section>

      <section id="branches" className="py-20 @md:py-40 border-t border-current/5">
        <div className="max-w-7xl mx-auto px-6 @md:px-12">
          <div className="grid grid-cols-1 @md:grid-cols-2 gap-px bg-current/5 border border-current/5">
            {branches.map((branch) => (
              <div key={branch.id} className="group cursor-pointer transition-colors overflow-hidden relative aspect-square rounded-[2rem] @md:rounded-none" style={{ backgroundColor: 'var(--bg-color)' }}>
                <div className="h-full w-full relative overflow-hidden grayscale group-hover:grayscale-0 transition-all duration-[2s]">
                  <img src={branch.image_url} alt={branch.name} className="w-full h-full object-cover group-hover:scale-125 transition-transform duration-[2s]" />
                  <div className="absolute inset-0 bg-black/20 group-hover:bg-transparent transition-colors" />
                </div>
                <div className="absolute bottom-6 left-6 right-6 @md:bottom-16 @md:left-16 @md:right-16 flex justify-between items-end transform translate-y-8 group-hover:translate-y-0 transition-transform duration-700 opacity-0 group-hover:opacity-100">
                  <div className="space-y-4">
                    <span className="text-[9px] font-black uppercase tracking-[0.5em] opacity-40">LOC. ID {branch.id}</span>
                    <h3 className="text-3xl @md:text-5xl font-light tracking-tight italic leading-tight">{branch.name}</h3>
                    <p className="text-[9px] @md:text-[10px] uppercase tracking-[0.3em] opacity-60 max-w-xs">{branch.address}</p>
                  </div>
                  <div className="w-12 h-12 @md:w-20 @md:h-20 border border-current/20 flex items-center justify-center bg-white/10 backdrop-blur-xl group-hover:bg-white group-hover:text-black transition-all duration-700">
                     <ArrowRight size={24} className="@md:size-[32px]" />
                  </div>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      <RefinedFooter branding={branding} p={p} />
    </div>
  );
};

// ─── Template: Luxury ──────────────────────────────────────────────────────── (Refined)
const LuxuryTemplate = ({ settings, branding, branches, isPreview }: WebsiteTemplateProps) => {
  const p = 'var(--primary)';
  const s = 'var(--secondary)';
  const a = 'var(--accent)';
  const align = settings?.hero_alignment || 'center';
  const fontWeight = 'var(--font-weight)';
  const btnStyle = 'btn-custom';

  return (
    <div className="relative overflow-x-hidden min-h-screen" style={{ backgroundColor: 'var(--bg-color)', color: 'var(--text-color)' }}>
      <nav className="h-28 flex items-center justify-between px-12 border-b border-white/5 sticky top-0 z-[100] transition-all" style={{ backgroundColor: 'var(--navbar-bg)', backdropFilter: 'blur(20px)' }}>
        <div className="hidden @lg:flex gap-10 text-[10px] font-bold uppercase tracking-[0.5em] opacity-40">
           <span className="hover:text-primary cursor-pointer transition-colors" style={{ color: p }}>Archive</span>
           <span className="hover:text-primary cursor-pointer transition-colors" style={{ color: p }}>Heritage</span>
        </div>
        <div className="flex items-center flex-col absolute left-1/2 -translate-x-1/2">
          {branding.logo_url ? (
            <img src={branding.logo_url} alt="Logo" className="h-10 mb-2 grayscale brightness-200" />
          ) : (
            <span className="text-[9px] @sm:text-xs @md:text-2xl tracking-[0.4em] @md:tracking-[0.6em] font-light uppercase opacity-80" style={{ color: p }}>{branding.title}</span>
          )}
          <div className="h-px w-12 opacity-30" style={{ backgroundColor: p }} />
        </div>
        <div className="flex items-center gap-10">

           <span className="hidden @lg:block text-[10px] font-bold uppercase tracking-[0.4em] border-b pb-1 cursor-pointer transition-colors" style={{ color: p, borderColor: p }}>RSVP</span>
        </div>
      </nav>

      <section className="hero-section relative flex items-center justify-center overflow-hidden">
        <div className="absolute inset-0 grayscale opacity-30 mix-blend-luminosity">
           {branding.banner_url && <img src={branding.banner_url} alt="Banner" className="w-full h-full object-cover scale-110" />}
        </div>
        <div className="absolute inset-0 bg-gradient-to-b from-black/90 via-black/40 to-[#0A0A0A] z-10" />
        
        <div className={`max-w-7xl mx-auto relative z-20 px-6 @md:px-12 ${align === 'center' ? 'text-center' : align === 'right' ? 'text-right' : 'text-left'}`}>
          <motion.div initial={{ opacity: 0, scale: 0.98 }} animate={{ opacity: 1, scale: 1 }} transition={{ duration: 1.5 }}>
            <div className={`mb-12 flex items-center gap-4 text-[11px] font-black uppercase tracking-[0.8em] ${
              align === 'center' ? 'justify-center' : align === 'right' ? 'justify-end' : ''
            }`} style={{ color: p }}>
               ESTABLISHED _ MCMXCV
            </div>
            <h1 className="text-3xl @sm:text-5xl @md:text-[10rem] italic mb-6 @md:mb-12 leading-[0.8] tracking-tighter uppercase font-light" style={{ fontSize: 'var(--heading-size)' }}>
              {branding.title}
            </h1>
            <div className={`h-px w-64 bg-current opacity-20 mb-12 ${align === 'center' ? 'mx-auto' : align === 'right' ? 'ml-auto' : ''}`} />
            <p className={`max-w-4xl text-lg @md:text-2xl opacity-40 leading-relaxed italic font-light ${align === 'center' ? 'mx-auto' : align === 'right' ? 'ml-auto' : ''}`} style={{ fontSize: 'var(--body-size)' }}>
              "{branding.description || 'Defining the apex of luxury through architectural mastery and timeless grace.'}"
            </p>
          </motion.div>
        </div>
        <div className="absolute bottom-12 left-1/2 -translate-x-1/2 z-20 animate-bounce">
           <div className="w-px h-24 opacity-20" style={{ backgroundColor: p }} />
        </div>
      </section>

      <section id="branches" className="py-20 @md:py-60">
        <div className="max-w-7xl mx-auto px-6 @md:px-12">
          <div className="text-center mb-20 @md:mb-40">
            <span className="uppercase tracking-[0.8em] text-[11px] font-black underline underline-offset-8 decoration-primary opacity-60" style={{ color: p, textDecorationColor: p }}>The Collection</span>
            <h2 className="text-4xl @md:text-[6rem] mt-10 @md:mt-16 italic font-light tracking-tighter uppercase leading-none">Signature Residences</h2>
          </div>

          <div className="grid grid-cols-1 gap-32 @md:gap-64">
            {branches.map((branch, i) => (
              <div key={branch.id} className={`flex flex-col ${i % 2 === 0 ? 'lg:flex-row' : 'lg:flex-row-reverse'} items-center gap-12 @md:gap-24 group`}>
                <div className="w-full @lg:w-[55%] aspect-square overflow-hidden relative shadow-2xl border border-white/5 bg-zinc-900 rounded-[2rem] @md:rounded-none">
                  <img src={branch.image_url} alt={branch.name} className="w-full h-full object-cover transition-transform duration-[2.5s] group-hover:scale-110 group-hover:rotate-1" />
                  <div className="absolute inset-0 border-[40px] border-black/20 pointer-events-none group-hover:border-black/5 transition-all duration-1000" />
                  <div className="absolute inset-0 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity duration-1000">
                     <div className="h-32 w-32 rounded-full border border-white/10 backdrop-blur-2xl flex items-center justify-center text-[11px] font-black uppercase tracking-[0.4em] bg-white/5">View</div>
                  </div>
                </div>
                <div className="w-full @lg:w-[45%] space-y-16">
                  <div className="space-y-4 @md:space-y-6">
                    <span className="text-xs uppercase tracking-[0.5em] font-black opacity-50" style={{ color: p }}>{branch.address}</span>
                    <h3 className="text-4xl @md:text-8xl italic font-light leading-tight tracking-tighter uppercase">{branch.name}</h3>
                  </div>
                  <p className="opacity-30 leading-relaxed text-lg @md:text-2xl font-light italic border-l-4 pl-8 @md:pl-12" style={{ borderLeftColor: p + '40' }}>
                    {branch.description}
                  </p>
                  <Button 
                    onClick={() => !isPreview && (window.location.href = `/${branch.slug}/reserve`)}
                    className={`${btnStyle} h-20 px-16 border-current text-current hover:bg-white hover:text-black transition-all rounded-none uppercase tracking-[0.5em] text-[11px] font-black shadow-none border-2 bg-transparent`}
                    style={{ borderColor: p, color: p }}
                  >
                    EXPLORE LEGACY
                  </Button>
                </div>
              </div>
            ))}
          </div>
        </div>
      </section>

      <RefinedFooter branding={branding} p={p} />
    </div>
  );
};

// ─── Template: Resort ──────────────────────────────────────────────────────── (Refined)
const ResortTemplate = ({ settings, branding, branches, isPreview }: WebsiteTemplateProps) => {
  const p = 'var(--primary)';
  const s = 'var(--secondary)';
  const a = 'var(--accent)';
  const align = settings?.hero_alignment || 'center';
  const fontWeight = 'var(--font-weight)';
  const btnStyle = 'btn-custom';

  return (
    <div className="min-h-full bg-[#f8fafc] text-slate-900" style={{ fontFamily: getFontFamily(settings.font_family) }}>
      <link rel="stylesheet" href={getFontUrl(settings.font_family)} />
      
      <nav className="h-20 flex items-center justify-between px-8 bg-white/70 backdrop-blur-md sticky top-0 z-[100]">
        <div className="flex items-center gap-2">
          {branding.logo_url ? (
            <img src={branding.logo_url} alt="Logo" className="h-10 w-auto object-contain" />
          ) : (
            <div className="w-10 h-10 rounded-full bg-sky-100 flex items-center justify-center text-sky-600">
               <Building2 size={20} />
            </div>
          )}
          <span className="font-bold text-[9px] @sm:text-xs @md:text-lg text-slate-800">{branding.title}</span>
        </div>
        <div className="flex items-center gap-4">

          <Button 
            onClick={() => document.getElementById('branches')?.scrollIntoView({ behavior: 'smooth' })}
            className={`bg-sky-500 hover:bg-sky-600 text-white font-bold h-10 px-6 ${btnStyle}`}
          >
            Check Availability
          </Button>
        </div>
      </nav>

      <section className="relative py-16 @md:py-24 px-6 @md:px-12 overflow-hidden">
        <div className="max-w-7xl mx-auto grid grid-cols-1 @lg:grid-cols-2 gap-12 @md:gap-16 items-center">
          <motion.div initial={{ opacity: 0, x: -30 }} animate={{ opacity: 1, x: 0 }} className="space-y-8">
            <span className="bg-sky-100 text-sky-700 px-4 py-1 rounded-full text-xs font-bold uppercase tracking-widest">Paradise Found</span>
            <h1 className="text-4xl @md:text-5xl @lg:text-7xl font-black text-slate-900 leading-[1.1]">
              Your Perfect <span className="text-sky-500">Escape</span> Awaits.
            </h1>
            <p className="text-lg text-slate-500 leading-relaxed max-w-xl">
              {branding.description}
            </p>
            <div className="flex gap-4">
              <Button 
                size="lg" 
                onClick={() => document.getElementById('branches')?.scrollIntoView({ behavior: 'smooth' })}
                className={`bg-slate-900 text-white h-14 px-8 font-bold ${btnStyle}`}
              >
                Explore Stays
              </Button>
              <Button size="lg" variant="outline" className={`h-14 px-8 font-bold ${btnStyle}`}>Our Story</Button>
            </div>
          </motion.div>
          <div className="relative">
             <div className="aspect-square rounded-2xl @md:rounded-[3rem] overflow-hidden @md:rotate-3 shadow-2xl">
                {branding.banner_url && <img src={branding.banner_url} alt="Hero" className="w-full h-full object-cover" />}
             </div>
             <div className="absolute -bottom-8 -left-8 w-48 h-48 bg-sky-400 rounded-full mix-blend-multiply opacity-20 animate-pulse" />
          </div>
        </div>
      </section>

      <section id="branches" className="py-16 @md:py-24 px-6 @md:px-12 bg-white">
        <div className="max-w-7xl mx-auto">
          <div className="flex flex-col @md:flex-row @md:items-end justify-between mb-16 gap-6">
            <div>
              <h2 className="text-4xl font-black text-slate-900">Island Collection</h2>
              <div className="h-1.5 w-12 bg-sky-400 rounded-full mt-3" />
            </div>
            <p className="text-slate-500 max-w-xs">{branches.length} unique properties for your next adventure.</p>
          </div>

          <div className="grid grid-cols-1 @md:grid-cols-2 @lg:grid-cols-3 gap-10">
            {branches.map(branch => (
              <Card key={branch.id} className="border-none shadow-xl shadow-slate-200/50 rounded-[2.5rem] overflow-hidden group">
                 <div className="aspect-square w-full overflow-hidden relative">
                    <img src={branch.image_url} alt={branch.name} className="w-full h-full object-cover group-hover:scale-110 transition-transform duration-700" />
                    <div className="absolute top-4 right-4 bg-white/90 backdrop-blur-sm px-3 py-1 rounded-full text-[10px] font-bold text-sky-600 uppercase">Featured</div>
                 </div>
                 <CardContent className="p-8">
                    <h3 className="text-2xl font-black mb-2 text-slate-800">{branch.name}</h3>
                    <div className="flex items-center gap-2 text-slate-400 text-xs mb-4">
                      <MapPin size={14} className="text-sky-400" />
                      {branch.address}
                    </div>
                    <Button 
                      onClick={() => !isPreview && (window.location.href = `/${branch.slug}/reserve`)}
                      className={`w-full h-12 bg-sky-50 text-sky-600 hover:bg-sky-500 hover:text-white transition-all font-bold ${btnStyle}`}
                    >
                      Discover More
                    </Button>
                 </CardContent>
              </Card>
            ))}
          </div>
        </div>
      </section>
      <RefinedFooter branding={branding} p={p} />
    </div>
  );
};

// ─── Template: Urban ───────────────────────────────────────────────────────── (Refined)
const UrbanTemplate = ({ settings, branding, branches, isPreview }: WebsiteTemplateProps) => {
  const p = 'var(--primary)';
  const s = 'var(--secondary)';
  const a = 'var(--accent)';
  const align = settings?.hero_alignment || 'left';
  const fontWeight = 'var(--font-weight)';
  const btnStyle = 'btn-custom';

  return (
    <div className="relative overflow-x-hidden min-h-screen" style={{ backgroundColor: 'var(--bg-color)', color: 'var(--text-color)' }}>
      <nav className="h-24 flex items-center justify-between px-12 sticky top-0 z-[100] transition-all border-b border-white/5" style={{ backgroundColor: 'var(--navbar-bg)', backdropFilter: 'blur(10px)' }}>
        <div className="flex items-center gap-6">
          {branding.logo_url ? (
            <img src={branding.logo_url} alt="Logo" className="h-10 w-auto object-contain" style={{ filter: 'invert(1)' }} />
          ) : (
            <div className="w-12 h-12 flex items-center justify-center font-black text-xl shadow-[4px_4px_0px_white] invert dark:invert-0" style={{ backgroundColor: p, color: 'black' }}>
               {branding.title.charAt(0)}
            </div>
          )}
          <span className="font-black text-[9px] @sm:text-xs @md:text-2xl tracking-tighter uppercase italic">{branding.title}</span>
        </div>
        <div className="flex items-center gap-10">

          <Button 
            onClick={() => document.getElementById('branches')?.scrollIntoView({ behavior: 'smooth' })}
            className={`${btnStyle} bg-white text-black hover:bg-primary hover:text-white font-black text-[10px] tracking-widest h-14 px-10 shadow-[6px_6px_0px_rgba(255,255,255,0.1)] transition-all`} 
            style={{ border: `2px solid ${p}` }}
          >
             BOOK_NOW
          </Button>
        </div>
      </nav>

      <section className="hero-section relative flex items-center overflow-hidden border-b-2 border-white/5">
        <div className="absolute inset-0 z-0 opacity-20 grayscale filter brightness-50">
           {branding.banner_url && <img src={branding.banner_url} alt="Hero" className="w-full h-full object-cover scale-110" />}
        </div>
        <div className="absolute top-0 right-0 w-1/2 h-full border-l-2 border-white/5 bg-gradient-to-l from-primary/5 to-transparent pointer-events-none" style={{ backgroundColor: p + '05' }} />
        
        <div className="max-w-7xl mx-auto relative z-10 px-6 @md:px-12">
          <div className="max-w-6xl">
            <motion.div initial={{ opacity: 0, x: -50 }} animate={{ opacity: 1, x: 0 }} transition={{ duration: 0.8, ease: "easeOut" }}>
              <div className="flex items-center gap-6 font-black text-[11px] tracking-[0.8em] mb-16 uppercase" style={{ color: p }}>
                <div className="h-1 w-24 shadow-[4px_4px_0px_rgba(0,0,0,0.5)]" style={{ backgroundColor: p }} />
                EST_2026 // DIRECT_BOOKING
              </div>
              <h1 className="text-3xl @sm:text-5xl @md:text-[12rem] font-black uppercase tracking-tighter mb-8 @md:mb-20 leading-[0.8] italic" style={{ fontSize: 'var(--heading-size)' }}>
                Urban <br /> 
                <span className="outline-text" style={{ color: p }}>Network</span>
              </h1>
              <div className="flex flex-col @md:flex-row gap-16 items-start">
                 <p className="opacity-60 text-lg @md:text-2xl font-bold max-w-2xl leading-snug border-l-8 pl-8 @md:pl-12" style={{ borderLeftColor: p, fontSize: 'var(--body-size)' }}>
                   {branding.description || 'Redefining metropolitan living through architectural precision and unparalleled urban luxury.'}
                 </p>
                 <div className="bg-white text-black p-8 @md:p-12 shadow-[12px_12px_0px_rgba(255,255,255,0.1)] transform @md:-rotate-2 border-4" style={{ borderColor: p }}>
                    <span className="text-[10px] font-black uppercase tracking-[0.6em] block mb-4 underline decoration-2 decoration-primary">Status: Active Service</span>
                    <span className="text-3xl @md:text-5xl font-black italic tracking-tighter uppercase leading-none">98.4% OCCUPANCY</span>
                 </div>
              </div>
            </motion.div>
          </div>
        </div>
      </section>

      <section id="branches" className="py-20 @md:py-40">
        <div className="max-w-7xl mx-auto px-6 @md:px-12">
          <div className="flex items-baseline justify-between mb-16 @md:mb-24 border-b-4 border-white/5 pb-12">
             <h2 className="text-4xl @md:text-6xl font-black uppercase tracking-tighter italic leading-none">Nodes_Catalog</h2>
             <span className="text-[11px] font-black opacity-30 tracking-[0.5em] uppercase">Count: {branches.length} Properties</span>
          </div>

          <div className="grid grid-cols-1 @md:grid-cols-2 @lg:grid-cols-3 gap-10 bg-white/5 border border-white/5 shadow-2xl">
             {branches.map((branch, i) => (
               <div key={branch.id} className="relative aspect-square w-full bg-zinc-950 group overflow-hidden grayscale hover:grayscale-0 transition-all duration-1000 border border-white/5 rounded-[2rem] @md:rounded-none">
                  <img src={branch.image_url} alt={branch.name} className="w-full h-full object-cover scale-100 group-hover:scale-110 transition-transform duration-[2s] opacity-30 group-hover:opacity-100" />
                  <div className="absolute inset-0 bg-gradient-to-t from-black via-transparent to-transparent opacity-90" />
                  <div className="absolute inset-x-8 bottom-8 @md:inset-x-12 @md:bottom-12">
                    <div className="h-1 w-full bg-white/10 mb-6 @md:mb-8 transform scale-x-0 group-hover:scale-x-100 transition-transform origin-left duration-1000" />
                    <span className="text-[10px] font-black tracking-[0.5em] uppercase block mb-3 @md:mb-4 opacity-0 group-hover:opacity-100 transition-all translate-y-4 group-hover:translate-y-0 duration-700" style={{ color: p }}>
                       REF_CODE: {branch.slug.substring(0, 3).toUpperCase()}_0{i+1}
                    </span>
                    <h3 className="text-3xl @md:text-5xl font-black uppercase italic leading-[0.85] tracking-tighter mb-6 @md:mb-8 hover:text-white transition-colors" style={{ color: p }}>{branch.name}</h3>
                    <div className="mt-12 flex items-center justify-between opacity-0 group-hover:opacity-100 transition-all translate-y-6 group-hover:translate-y-0 duration-1000 delay-100">
                      <div className="flex items-center gap-4 text-[11px] font-black opacity-50 uppercase tracking-widest">
                        <MapPin size={18} style={{ color: p }} />
                        {branch.address}
                      </div>
                      <div className="w-16 h-16 flex items-center justify-center shadow-[6px_6px_0px_white] transition-all hover:scale-110 active:scale-95" style={{ backgroundColor: p, color: 'black' }}>
                        <ArrowRight size={32} />
                      </div>
                    </div>
                  </div>
               </div>
             ))}
          </div>
        </div>
      </section>

      <RefinedFooter branding={branding} p={p} />
    </div>
  );
};

// ─── Main Controller ──────────────────────────────────────────────────────────
export default function WebsiteTemplate({ 
  settings, 
  branding, 
  branches = [], 
  isPreview = false 
}: WebsiteTemplateProps) {
  // Map settings to CSS variables
  const cssVars = React.useMemo(() => {
    const s = settings || {};
    
    // Scale mappings
    const spacingMap = { compact: '1rem', normal: '2rem', spacious: '4rem' };
    const radiusMap = { none: '0', sm: '0.25rem', md: '0.5rem', lg: '1rem', full: '9999px' };
    const widthMap = { narrow: '800px', medium: '1000px', wide: '1280px', full: '100%' };
    const heightMap = { low: '40vh', medium: '60vh', high: '85vh', full: '100vh' };
    const fontScale = { sm: '0.875rem', md: '1rem', lg: '1.25rem', xl: '1.5rem', '2xl': '2rem', '3xl': '3rem' };
    const weightMap = { normal: '400', medium: '500', bold: '700', extra: '900' };
    const speedMap = { none: '0s', slow: '0.5s', normal: '0.3s', fast: '0.15s' };

    return {
      '--primary': s.primary_color || '#4f46e5',
      '--secondary': s.secondary_color || '#7c3aed',
      '--accent': s.accent_color || '#f59e0b',
      '--background': s.background_color || '#ffffff',
      '--text': s.text_color || '#0f172a',
      '--navbar-bg': s.navbar_bg || 'transparent',
      '--footer-bg': s.footer_bg || '#0f172a',
      '--card-bg': s.card_bg || '#ffffff',
      '--heading-size': fontScale[s.heading_size as keyof typeof fontScale] || '2rem',
      '--body-size': fontScale[s.body_size as keyof typeof fontScale] || '1rem',
      '--font-weight': weightMap[s.font_weight as keyof typeof weightMap] || '400',
      '--container-width': widthMap[s.container_width as keyof typeof widthMap] || '1280px',
      '--hero-height': heightMap[s.hero_height as keyof typeof heightMap] || '60vh',
      '--radius': radiusMap[s.card_radius as keyof typeof radiusMap] || '0.5rem',
      '--btn-padding': spacingMap[s.button_padding as keyof typeof spacingMap] || '24px',
      '--glass-opacity': s.glass_intensity === 'none' ? '0' : s.glass_intensity === 'low' ? '0.05' : s.glass_intensity === 'high' ? '0.2' : '0.1',
      '--glass-blur': s.glass_intensity === 'none' ? '0px' : s.glass_intensity === 'low' ? '4px' : s.glass_intensity === 'high' ? '20px' : '10px',
      '--transition-speed': speedMap[s.animation_speed as keyof typeof speedMap] || '0.3s',
      '--font-family': s.font_family === 'serif' ? '"Playfair Display", serif' : s.font_family === 'mono' ? '"JetBrains Mono", monospace' : '"Inter", sans-serif',
    } as React.CSSProperties;
  }, [settings]);

  // Template common styles
  const commonStyles = `
    .template-container { 
      font-family: var(--font-family); 
      color: var(--text); 
      background: var(--background);
      transition: all var(--transition-speed) ease;
    }
    .content-width { max-width: var(--container-width); margin: 0 auto; width: 100%; padding: 0 1.5rem; }
    @media (min-width: 768px) { .content-width { padding: 0 3rem; } }
    .hero-section { height: var(--hero-height); min-height: 400px; transition: height 0.5s ease; }
    .card-effect { 
      border-radius: var(--radius); 
      background: var(--card-bg);
      backdrop-filter: blur(var(--glass-blur));
      border: 1px solid rgba(255,255,255, var(--glass-opacity));
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .btn-custom {
      padding-left: var(--btn-padding);
      padding-right: var(--btn-padding);
      border-radius: var(--radius);
      font-weight: var(--font-weight);
      transition: all 0.2s ease;
    }
    h1, h2, h3 { font-weight: var(--font-weight); }
    .text-primary { color: var(--primary); }
    .text-secondary { color: var(--secondary); }
    .text-accent { color: var(--accent); }
    .bg-primary { background-color: var(--primary); }
    .border-primary { border-color: var(--primary); }
  `;

  const templateId = settings?.template_id || 'modern';
  
  return (
    <div className="@container template-container min-h-screen w-full relative" style={cssVars}>
      <style dangerouslySetInnerHTML={{ __html: commonStyles }} />
      {templateId === 'modern' && <ModernTemplate branding={branding} branches={branches} settings={settings} isPreview={isPreview} />}
      {templateId === 'classic' && <ClassicTemplate branding={branding} branches={branches} settings={settings} isPreview={isPreview} />}
      {templateId === 'minimalist' && <MinimalistTemplate branding={branding} branches={branches} settings={settings} isPreview={isPreview} />}
      {templateId === 'luxury' && <LuxuryTemplate branding={branding} branches={branches} settings={settings} isPreview={isPreview} />}
      {templateId === 'resort' && <ResortTemplate branding={branding} branches={branches} settings={settings} isPreview={isPreview} />}
      {templateId === 'urban' && <UrbanTemplate branding={branding} branches={branches} settings={settings} isPreview={isPreview} />}
    </div>
  );
}
