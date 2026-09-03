'use client';

import * as React from 'react';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { 
  Type, 
  Palette, 
  Layout as LayoutIcon, 
  Smartphone, 
  Monitor, 
  RotateCcw,
  Sparkles,
  Check,
  ChevronRight,
  Menu,
  Phone,
  Mail,
  Instagram,
  Facebook,
  Twitter,
  LayoutGrid,
  Maximize,
  Box,
  Settings2,
  Contrast,
  SlidersHorizontal,
  Paintbrush
} from 'lucide-react';
import { 
  Tabs, TabsContent, TabsList, TabsTrigger 
} from '@/components/ui/tabs';
import {
  Select, SelectContent, SelectItem, SelectTrigger, SelectValue
} from '@/components/ui/select';
import WebsiteTemplate from './WebsiteTemplate';

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
  theme_preset?: string;
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

interface DesignSystemEditorProps {
  settings: DesignSettings;
  onChange: (settings: DesignSettings) => void;
  branding: Branding;
  branches?: any[];
  title?: string;
  description?: string;
}

// Utility to generate harmonized colors (Simple HSL manipulation)
function hexToHsl(hex: string) {
  let r = 0, g = 0, b = 0;
  if (hex.length === 4) {
    r = parseInt(hex[1] + hex[1], 16);
    g = parseInt(hex[2] + hex[2], 16);
    b = parseInt(hex[3] + hex[3], 16);
  } else if (hex.length === 7) {
    r = parseInt(hex.substring(1, 3), 16);
    g = parseInt(hex.substring(3, 5), 16);
    b = parseInt(hex.substring(5, 7), 16);
  }
  r /= 255; g /= 255; b /= 255;
  const max = Math.max(r, g, b), min = Math.min(r, g, b);
  let h = 0, s = 0, l = (max + min) / 2;
  if (max !== min) {
    const d = max - min;
    s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
    switch (max) {
      case r: h = (g - b) / d + (g < b ? 6 : 0); break;
      case g: h = (b - r) / d + 2; break;
      case b: h = (r - g) / d + 4; break;
    }
    h /= 6;
  }
  return { h: h * 360, s: s * 100, l: l * 100 };
}

function hslToHex(h: number, s: number, l: number) {
  l /= 100;
  const a = s * Math.min(l, 1 - l) / 100;
  const f = (n: number) => {
    const k = (n + h / 30) % 12;
    const color = l - a * Math.max(Math.min(k - 3, 9 - k, 1), -1);
    return Math.round(255 * color).toString(16).padStart(2, '0');
  };
  return `#${f(0)}${f(8)}${f(4)}`;
}

export function DesignSystemEditor({ settings, onChange, branding, branches = [], title, description }: DesignSystemEditorProps) {
  const [viewMode, setViewMode] = React.useState<'desktop' | 'mobile'>('desktop');
  const currentSettings = React.useMemo(() => {
    const s = settings || {};
    return {
      template_id: s.template_id || 'modern',
      hero_alignment: s.hero_alignment || 'center',
      font_family: s.font_family || 'sans',
      font_weight: s.font_weight || 'normal',
      card_style: s.card_style || 'glass',
      button_style: s.button_style || 'full',
      primary_color: s.primary_color || '#4f46e5',
      secondary_color: s.secondary_color || '#7c3aed',
      accent_color: s.accent_color || '#f59e0b',
      background_color: s.background_color || '#ffffff',
      text_color: s.text_color || '#0f172a',
      navbar_bg: s.navbar_bg || 'transparent',
      footer_bg: s.footer_bg || '#0f172a',
      card_bg: s.card_bg || '#ffffff',
      heading_size: s.heading_size || 'xl',
      body_size: s.body_size || 'md',
      container_width: s.container_width || 'wide',
      hero_height: s.hero_height || 'medium',
      card_radius: s.card_radius || 'lg',
      button_padding: s.button_padding || 'normal',
      glass_intensity: s.glass_intensity || 'medium',
      animation_speed: s.animation_speed || 'normal',
    };
  }, [settings]);

  // Mock branches for preview if none provided
  const previewBranches = branches.length > 0 ? branches : [
    { id: 1, name: 'Ocean View Resort', slug: 'ocean', description: 'Serenable views of the Atlantic with private beach access.', address: 'Lagos, Nigeria', image_url: 'https://images.unsplash.com/photo-1540518614846-7eded433c457?auto=format&fit=crop&q=80&w=800' },
    { id: 2, name: 'Mountain Retreat', slug: 'mountain', description: 'Experience the heights of luxury in our alpine suites.', address: 'Obudu, Nigeria', image_url: 'https://images.unsplash.com/photo-1512918766671-ad65009edaa3?auto=format&fit=crop&q=80&w=800' },
    { id: 3, name: 'Urban Oasis', slug: 'urban', description: 'Modern sophistication in the heart of the business district.', address: 'Abuja, Nigeria', image_url: 'https://images.unsplash.com/photo-1445019980597-93fa8acb246c?auto=format&fit=crop&q=80&w=800' },
  ];

  const updateSetting = (key: keyof DesignSettings, value: string) => {
    onChange({ ...currentSettings, [key]: value });
  };

  const handleHarmonize = () => {
    const hsl = hexToHsl(currentSettings.primary_color);
    const secondary = hslToHex((hsl.h + 20) % 360, hsl.s, Math.max(20, hsl.l - 10));
    const accent = hslToHex((hsl.h + 180) % 360, hsl.s, Math.max(40, hsl.l + 10));
    
    onChange({
      ...currentSettings,
      secondary_color: secondary,
      accent_color: accent
    });
  };

  const ColorPicker = ({ label, value, onChange: onColorChange }: { label: string, value: string, onChange: (val: string) => void }) => (
    <div className="space-y-1.5">
      <Label className="text-[10px] font-bold text-muted-foreground uppercase">{label}</Label>
      <div className="relative group">
        <div 
          className="w-full h-9 rounded-lg border shadow-sm cursor-pointer transition-all hover:ring-2 hover:ring-primary/20" 
          style={{ backgroundColor: value }}
        >
          <Input 
            type="color" 
            className="absolute inset-0 opacity-0 cursor-pointer w-full h-full"
            value={value}
            onChange={(e) => onColorChange(e.target.value)}
          />
        </div>
      </div>
    </div>
  );

  return (
    <div className="flex flex-col gap-8">
      <div className="grid grid-cols-1 xl:grid-cols-12 gap-8">
        {/* Professional Sidebar Controls */}
        <div className="xl:col-span-4 flex flex-col h-[800px]">
          <Card className="border-border/40 shadow-2xl bg-card flex flex-col h-full overflow-hidden">
            <div className="p-6 border-b border-border/40 bg-muted/30">
              <div className="flex items-center justify-between mb-1">
                <h3 className="font-black text-xl tracking-tighter flex items-center gap-2">
                  <Paintbrush size={20} className="text-primary" />
                  DESIGN STUDIO
                </h3>
                <div className="flex gap-1">
                   <div className="w-2 h-2 rounded-full bg-red-400" />
                   <div className="w-2 h-2 rounded-full bg-yellow-400" />
                   <div className="w-2 h-2 rounded-full bg-green-400" />
                </div>
              </div>
              <p className="text-[10px] text-muted-foreground font-bold uppercase tracking-widest">Global Style Orchestration</p>
            </div>

            <Tabs defaultValue="templates" className="flex-1 flex flex-col">
              <TabsList className="grid grid-cols-5 w-full rounded-none border-b border-border/40 h-12 bg-muted/50 p-0">
                <TabsTrigger value="templates" className="rounded-none data-[state=active]:bg-background data-[state=active]:border-b-2 data-[state=active]:border-primary h-full transition-none"><LayoutGrid size={16} /></TabsTrigger>
                <TabsTrigger value="colors" className="rounded-none data-[state=active]:bg-background data-[state=active]:border-b-2 data-[state=active]:border-primary h-full transition-none"><Palette size={16} /></TabsTrigger>
                <TabsTrigger value="typography" className="rounded-none data-[state=active]:bg-background data-[state=active]:border-b-2 data-[state=active]:border-primary h-full transition-none"><Type size={16} /></TabsTrigger>
                <TabsTrigger value="layout" className="rounded-none data-[state=active]:bg-background data-[state=active]:border-b-2 data-[state=active]:border-primary h-full transition-none"><Box size={16} /></TabsTrigger>
                <TabsTrigger value="components" className="rounded-none data-[state=active]:bg-background data-[state=active]:border-b-2 data-[state=active]:border-primary h-full transition-none"><Settings2 size={16} /></TabsTrigger>
              </TabsList>

              <div className="flex-1 overflow-y-auto custom-scrollbar p-6">
                <TabsContent value="templates" className="mt-0 space-y-6">
                  <div className="space-y-4">
                    <Label className="text-[10px] font-black uppercase tracking-[0.2em] text-primary">Layout selection</Label>
                    <div className="grid grid-cols-1 gap-3">
                      {[
                        { id: 'modern', name: 'Premium Modern', desc: 'Vibrant gradients & glassmorphism' },
                        { id: 'classic', name: 'Classic Standard', desc: 'Time-tested & familiar layout' },
                        { id: 'minimalist', name: 'Ultra Minimal', desc: 'Clean white space & bold type' },
                        { id: 'luxury', name: 'Heritage Luxury', desc: 'Serif elegance & gold accents' },
                        { id: 'resort', name: 'Paradise Resort', desc: 'Soft curves & vibrant imagery' },
                        { id: 'urban', name: 'Urban Industrial', desc: 'High contrast & grid precision' },
                      ].map((t) => (
                        <button
                          key={t.id}
                          onClick={() => updateSetting('template_id', t.id)}
                          className={`flex flex-col text-left p-4 rounded-xl border-2 transition-all group ${
                            currentSettings.template_id === t.id 
                            ? 'border-primary bg-primary/5 shadow-[0_0_20px_rgba(79,70,229,0.1)]' 
                            : 'border-border/50 hover:border-border/80 hover:bg-muted/30'
                          }`}
                        >
                          <div className="flex items-center justify-between w-full mb-1">
                            <span className="font-black text-sm tracking-tight">{t.name}</span>
                            {currentSettings.template_id === t.id && <div className="h-2 w-2 rounded-full bg-primary animate-pulse" />}
                          </div>
                          <span className="text-[10px] text-muted-foreground italic">{t.desc}</span>
                        </button>
                      ))}
                    </div>
                  </div>
                </TabsContent>

                <TabsContent value="colors" className="mt-0 space-y-8">
                  <div className="space-y-6">
                     <div className="flex items-center justify-between">
                       <Label className="text-[10px] font-black uppercase tracking-[0.2em] text-primary">Brand Palette</Label>
                       <Button variant="outline" size="sm" onClick={handleHarmonize} className="h-7 text-[10px] gap-1 font-black shadow-none rounded-none border-2">
                         <Sparkles size={12} /> HARMONIZE
                       </Button>
                     </div>
                     <div className="grid grid-cols-3 gap-4">
                       <ColorPicker label="Primary" value={currentSettings.primary_color} onChange={(v) => updateSetting('primary_color', v)} />
                       <ColorPicker label="Secondary" value={currentSettings.secondary_color} onChange={(v) => updateSetting('secondary_color', v)} />
                       <ColorPicker label="Accent" value={currentSettings.accent_color} onChange={(v) => updateSetting('accent_color', v)} />
                     </div>
                  </div>

                  <div className="space-y-4 pt-6 border-t border-border/40">
                    <Label className="text-[10px] font-black uppercase tracking-[0.2em] text-primary">Interface shades</Label>
                    <div className="grid grid-cols-2 gap-4">
                       <ColorPicker label="Background" value={currentSettings.background_color} onChange={(v) => updateSetting('background_color', v)} />
                       <ColorPicker label="Text color" value={currentSettings.text_color} onChange={(v) => updateSetting('text_color', v)} />
                       <ColorPicker label="Navbar" value={currentSettings.navbar_bg} onChange={(v) => updateSetting('navbar_bg', v)} />
                       <ColorPicker label="Footer" value={currentSettings.footer_bg} onChange={(v) => updateSetting('footer_bg', v)} />
                    </div>
                  </div>
                </TabsContent>

                <TabsContent value="typography" className="mt-0 space-y-8">
                  <div className="space-y-4">
                    <Label className="text-[10px] font-black uppercase tracking-[0.2em] text-primary">Font family</Label>
                    <div className="grid grid-cols-1 gap-2">
                       {[
                         { id: 'sans', name: 'Modern Sans (Inter)', desc: 'Clean, professional, and readable' },
                         { id: 'serif', name: 'Elegant Serif (Playfair)', desc: 'Prestigious, luxury, and classic' },
                         { id: 'mono', name: 'Technical Mono (JetBrains)', desc: 'Modern, edgy, and structured' },
                       ].map((f) => (
                         <button
                           key={f.id}
                           onClick={() => updateSetting('font_family', f.id)}
                           className={`flex flex-col text-left p-3 rounded-xl border-2 transition-all ${
                             currentSettings.font_family === f.id ? 'border-primary bg-primary/5' : 'border-border/40 hover:bg-muted/20'
                           }`}
                         >
                           <span className="text-sm font-bold">{f.name}</span>
                           <span className="text-[9px] text-muted-foreground italic">{f.desc}</span>
                         </button>
                       ))}
                    </div>
                  </div>

                  <div className="space-y-4 pt-6 border-t border-border/40">
                    <div className="grid grid-cols-2 gap-6">
                       <div className="space-y-2">
                          <Label className="text-[10px] font-bold uppercase text-muted-foreground">Heading Size</Label>
                          <Select value={currentSettings.heading_size} onValueChange={(v) => v && updateSetting('heading_size', v)}>
                             <SelectTrigger className="h-9 text-xs rounded-none border-2 shadow-none"><SelectValue /></SelectTrigger>
                             <SelectContent>
                               {['sm', 'md', 'lg', 'xl', '2xl'].map(s => <SelectItem key={s} value={s}>{s.toUpperCase()}</SelectItem>)}
                             </SelectContent>
                          </Select>
                       </div>
                       <div className="space-y-2">
                          <Label className="text-[10px] font-bold uppercase text-muted-foreground">Headline Weight</Label>
                          <Select value={currentSettings.font_weight} onValueChange={(v) => v && updateSetting('font_weight', v)}>
                             <SelectTrigger className="h-9 text-xs rounded-none border-2 shadow-none"><SelectValue /></SelectTrigger>
                             <SelectContent>
                               {['normal', 'medium', 'bold', 'extra'].map(w => <SelectItem key={w} value={w}>{w.toUpperCase()}</SelectItem>)}
                             </SelectContent>
                          </Select>
                       </div>
                    </div>
                  </div>
                </TabsContent>

                <TabsContent value="layout" className="mt-0 space-y-8">
                   <div className="space-y-4">
                      <Label className="text-[10px] font-black uppercase tracking-[0.2em] text-primary">Canvas scale</Label>
                      <div className="space-y-2">
                         <Label className="text-[10px] font-bold uppercase text-muted-foreground">Container Width</Label>
                         <Select value={currentSettings.container_width} onValueChange={(v) => v && updateSetting('container_width', v)}>
                            <SelectTrigger className="h-9 text-xs rounded-none border-2 shadow-none"><SelectValue /></SelectTrigger>
                            <SelectContent>
                               {['narrow', 'medium', 'wide', 'full'].map(w => <SelectItem key={w} value={w}>{w.toUpperCase()}</SelectItem>)}
                            </SelectContent>
                         </Select>
                      </div>
                      <div className="space-y-2">
                         <Label className="text-[10px] font-bold uppercase text-muted-foreground">Hero Magnitude</Label>
                         <Select value={currentSettings.hero_height} onValueChange={(v) => v && updateSetting('hero_height', v)}>
                            <SelectTrigger className="h-9 text-xs rounded-none border-2 shadow-none"><SelectValue /></SelectTrigger>
                            <SelectContent>
                               {['low', 'medium', 'high', 'full'].map(h => <SelectItem key={h} value={h}>{h.toUpperCase()}</SelectItem>)}
                            </SelectContent>
                         </Select>
                      </div>
                   </div>

                   <div className="space-y-4 pt-6 border-t border-border/40">
                      <Label className="text-[10px] font-black uppercase tracking-[0.2em] text-primary">Content Alignment</Label>
                      <div className="grid grid-cols-3 gap-2">
                         {['left', 'center', 'right'].map(a => (
                            <button
                               key={a}
                               onClick={() => updateSetting('hero_alignment', a)}
                               className={`py-2 text-[10px] font-bold uppercase border-2 transition-all ${
                                  currentSettings.hero_alignment === a ? 'border-primary bg-primary/5' : 'border-border/40'
                               }`}
                            >
                               {a}
                            </button>
                         ))}
                      </div>
                   </div>
                </TabsContent>

                <TabsContent value="components" className="mt-0 space-y-8">
                   <div className="space-y-4">
                      <Label className="text-[10px] font-black uppercase tracking-[0.2em] text-primary">Border radii</Label>
                      <div className="grid grid-cols-3 gap-2">
                         {['none', 'sm', 'md', 'lg', 'full'].map(r => (
                            <button
                               key={r}
                               onClick={() => updateSetting('card_radius', r)}
                               className={`py-2 text-[10px] font-bold border-2 transition-all ${
                                  currentSettings.card_radius === r ? 'border-primary bg-primary/5' : 'border-border/40'
                               } ${r === 'full' ? 'rounded-full' : r === 'none' ? 'rounded-none' : `rounded-${r}`}`}
                            >
                               {r.toUpperCase()}
                            </button>
                         ))}
                      </div>
                   </div>

                   <div className="space-y-4 pt-6 border-t border-border/40">
                      <Label className="text-[10px] font-black uppercase tracking-[0.2em] text-primary">Visual effects</Label>
                      <div className="space-y-2">
                         <Label className="text-[10px] font-bold uppercase text-muted-foreground">Glass Intensity</Label>
                         <Select value={currentSettings.glass_intensity} onValueChange={(v) => v && updateSetting('glass_intensity', v)}>
                            <SelectTrigger className="h-9 text-xs rounded-none border-2 shadow-none"><SelectValue /></SelectTrigger>
                            <SelectContent>
                               {['none', 'low', 'medium', 'high'].map(i => <SelectItem key={i} value={i}>{i.toUpperCase()}</SelectItem>)}
                            </SelectContent>
                         </Select>
                      </div>
                      <div className="space-y-2">
                         <Label className="text-[10px] font-bold uppercase text-muted-foreground">Animation Tempo</Label>
                         <Select value={currentSettings.animation_speed} onValueChange={(v) => v && updateSetting('animation_speed', v)}>
                            <SelectTrigger className="h-9 text-xs rounded-none border-2 shadow-none"><SelectValue /></SelectTrigger>
                            <SelectContent>
                               {['none', 'slow', 'normal', 'fast'].map(s => <SelectItem key={s} value={s}>{s.toUpperCase()}</SelectItem>)}
                            </SelectContent>
                         </Select>
                      </div>
                   </div>
                </TabsContent>
              </div>
            </Tabs>

            <div className="p-4 bg-muted/20 border-t border-border/40">
               <Button className="w-full h-11 font-black tracking-widest bg-primary text-primary-foreground hover:scale-[1.02] shadow-xl shadow-primary/20 transition-all rounded-none border-2 border-black" onClick={() => updateSetting('template_id', currentSettings.template_id)}>
                  <Maximize size={16} className="mr-2" /> PUBLISH STUDIO
               </Button>
            </div>
          </Card>
        </div>

        {/* Realistic Website Preview Panel */}
        <div className="xl:col-span-8 flex flex-col gap-4">
          <div className="flex items-center justify-between px-2">
            <h4 className="text-sm font-bold flex items-center gap-2 text-muted-foreground uppercase tracking-tighter">
              <Monitor size={16} className="text-primary" />
              Interactive Canvas preview
            </h4>
            <div className="flex gap-1.5 bg-muted/50 p-1 rounded-lg border border-border/20">
              <Button 
                size="icon" 
                variant="ghost" 
                className={`h-6 w-6 ${viewMode === 'mobile' ? 'bg-background border shadow-sm' : ''}`}
                onClick={() => setViewMode('mobile')}
              >
                <Smartphone size={12} />
              </Button>
              <Button 
                size="icon" 
                variant="ghost" 
                className={`h-6 w-6 ${viewMode === 'desktop' ? 'bg-background border shadow-sm' : ''}`}
                onClick={() => setViewMode('desktop')}
              >
                <Monitor size={12} />
              </Button>
            </div>
          </div>
 
          <div className={`flex-1 min-h-[800px] w-full border-4 border-zinc-900 rounded-[2.5rem] overflow-hidden shadow-[0_40px_100px_-20px_rgba(0,0,0,0.5)] relative bg-black transition-all duration-500 ease-in-out mx-auto ${viewMode === 'mobile' ? 'max-w-[375px]' : ''}`}>
              <WebsiteTemplate 
                settings={currentSettings as any} 
                branding={branding} 
                branches={previewBranches}
                isPreview={true}
              />
          </div>
        </div>
      </div>
    </div>
  );
}

