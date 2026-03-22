'use client';

import * as React from 'react';
import { useRouter } from 'next/navigation';
import { motion } from 'framer-motion';
import { 
  QrCode, 
  Utensils, 
  ConciergeBell, 
  History, 
  ChevronRight, 
  Home,
  Star
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import api from '@/lib/api';

export default function GuestLandingPage() {
  const router = useRouter();
  const [sessionToken, setSessionToken] = React.useState('');
  const [recommendations, setRecommendations] = React.useState<any>(null);
  const [isLoadingRecs, setIsLoadingRecs] = React.useState(false);

  React.useEffect(() => {
    // Intercept QR Code structural parameters and lock session internally
    if (typeof window !== 'undefined') {
      const params = new URLSearchParams(window.location.search);
      const tenant = params.get('tenant');
      const branch = params.get('branch');
      const room = params.get('room_id');
      const outlet = params.get('outlet_id');
      const table = params.get('table');

      if (tenant) localStorage.setItem('tenant_id', tenant);
      if (branch) localStorage.setItem('branch_id', branch);
      if (room) localStorage.setItem('room_id', room);
      if (outlet) localStorage.setItem('outlet_id', outlet);
      if (table) localStorage.setItem('table_number', table);
    }

    const fetchRecs = async () => {
      try {
        setIsLoadingRecs(true);
        // Defaulting to outlet 1 for demo purposes
        const { data } = await api.get('/api/v1/guest/menus/1/recommendations');
        setRecommendations(data);
      } catch (e) {
        console.error("Failed to load recommendations", e);
      } finally {
        setIsLoadingRecs(false);
      }
    };
    fetchRecs();
  }, []);

  const featuredServices = [
    { title: 'Room Service', icon: Utensils, description: 'Order delicious meals to your room', color: 'bg-orange-500/20 text-orange-400', link: '/menu' },
    { title: 'Guest Services', icon: ConciergeBell, description: 'Housekeeping, towels, and more', color: 'bg-blue-500/20 text-blue-400', link: '/services' },
    { title: 'Stay History', icon: History, description: 'View your orders and requests', color: 'bg-emerald-500/20 text-emerald-400', link: '/history' },
  ];

  return (
    <div className="space-y-8 pb-24">
      <header className="space-y-2 mt-4 text-center">
        <motion.div 
          initial={{ opacity: 0, y: -20 }}
          animate={{ opacity: 1, y: 0 }}
          className="inline-flex items-center justify-center p-2 rounded-2xl bg-white/5 border border-white/10 mb-2"
        >
          <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center shadow-lg shadow-indigo-500/20">
            <span className="text-white font-bold text-xl">DM</span>
          </div>
        </motion.div>
        <motion.h1 
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          transition={{ delay: 0.1 }}
          className="text-4xl font-extrabold tracking-tight bg-clip-text text-transparent bg-gradient-to-b from-white to-white/60"
        >
          Welcome to <br />DM Tech Hotel
        </motion.h1>
      </header>

      {/* Access Card */}
      <motion.div
        initial={{ opacity: 0, scale: 0.95 }}
        animate={{ opacity: 1, scale: 1 }}
        transition={{ delay: 0.3 }}
      >
        <Card className="bg-white/5 border-white/10 backdrop-blur-xl shadow-2xl overflow-hidden relative">
          <CardHeader>
            <CardTitle className="text-xl text-white">Express Check-In</CardTitle>
            <CardDescription className="text-slate-400">Enter access code for full room control.</CardDescription>
          </CardHeader>
          <CardContent className="space-y-4">
            <div className="grid gap-2">
              <Input 
                placeholder="CODE: 123456" 
                value={sessionToken}
                onChange={(e) => setSessionToken(e.target.value.toUpperCase())}
                className="bg-white/5 border-white/10 text-white h-12 text-center text-lg tracking-widest"
                maxLength={6}
              />
            </div>
            <Button className="w-full h-12 bg-indigo-600 hover:bg-indigo-500 text-white font-semibold rounded-xl">
              Access My Room
            </Button>
          </CardContent>
        </Card>
      </motion.div>

      {/* Featured Services */}
      <div className="space-y-4">
        <h2 className="text-lg font-semibold text-white/90 px-1">Quick Services</h2>
        <div className="grid gap-3">
          {featuredServices.map((service, index) => (
            <motion.div
              key={service.title}
              initial={{ opacity: 0, x: -20 }}
              animate={{ opacity: 1, x: 0 }}
              transition={{ delay: 0.4 + index * 0.1 }}
              whileTap={{ scale: 0.98 }}
              onClick={() => router.push(service.link)}
              className="flex items-center p-4 rounded-2xl bg-white/5 border border-white/10 hover:bg-white/[0.08] cursor-pointer transition-all"
            >
              <div className={`p-3 rounded-xl ${service.color} mr-4`}>
                <service.icon size={24} />
              </div>
              <div className="flex-1">
                <h3 className="font-semibold text-white">{service.title}</h3>
                <p className="text-sm text-slate-400 line-clamp-1">{service.description}</p>
              </div>
              <ChevronRight size={18} className="text-slate-600" />
            </motion.div>
          ))}
        </div>
      </div>

      {/* Smart Recommendations */}
      {recommendations && (
        <div className="space-y-4 animate-in fade-in slide-in-from-bottom-4 duration-700">
          <div className="flex items-center justify-between px-1">
            <h2 className="text-lg font-semibold text-white/90">Popular Today</h2>
            <div className="flex items-center text-indigo-400 text-xs font-medium">
              Refresh <RefreshCcw size={12} className="ml-1" />
            </div>
          </div>
          <div className="flex gap-4 overflow-x-auto pb-4 no-scrollbar -mx-4 px-4">
            {recommendations.popular?.map((item: any, i: number) => (
              <motion.div 
                key={i} 
                className="min-w-[160px] bg-white/5 border border-white/10 rounded-2xl p-3 space-y-2 group"
                whileTap={{ scale: 0.95 }}
              >
                <div className="h-24 w-full bg-slate-800 rounded-xl flex items-center justify-center relative overflow-hidden">
                   <div className="absolute top-2 right-2 p-1 bg-white/10 rounded-full">
                     <Star size={12} className="text-yellow-500 fill-yellow-500" />
                   </div>
                   <Utensils size={24} className="text-slate-600 group-hover:scale-110 transition-transform" />
                </div>
                <div>
                  <h4 className="text-sm font-medium text-white truncate">{item.name || 'Gourmet Dish'}</h4>
                  <p className="text-xs text-indigo-400 font-bold">${parseFloat(item.price || 0).toFixed(2)}</p>
                </div>
              </motion.div>
            ))}
          </div>
        </div>
      )}

      {/* Floating Bottom Nav */}
      <div className="fixed bottom-6 left-1/2 -translate-x-1/2 w-[90%] max-w-[360px] h-16 rounded-3xl bg-slate-900/80 backdrop-blur-2xl border border-white/10 shadow-2xl z-50 flex items-center justify-around px-4">
        <button onClick={() => router.push('/')} className="p-2 text-indigo-400 flex flex-col items-center">
          <Home size={20} />
          <span className="text-[10px] mt-1 font-medium">Home</span>
        </button>
        <button className="p-4 bg-indigo-600 text-white rounded-2xl -mt-12 shadow-xl shadow-indigo-600/30 border-4 border-slate-950">
          <QrCode size={24} />
        </button>
        <button onClick={() => router.push('/services')} className="p-2 text-slate-500 flex flex-col items-center">
          <ConciergeBell size={20} />
          <span className="text-[10px] mt-1 font-medium">Services</span>
        </button>
      </div>
    </div>
  );
}

const RefreshCcw = ({ size, className }: { size: number, className: string }) => (
  <svg 
    xmlns="http://www.w3.org/2000/svg" 
    width={size} 
    height={size} 
    viewBox="0 0 24 24" 
    fill="none" 
    stroke="currentColor" 
    strokeWidth="2" 
    strokeLinecap="round" 
    strokeLinejoin="round" 
    className={className}
  >
    <path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8" />
    <path d="M3 3v5h5" />
    <path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16" />
    <path d="M16 16h5v5" />
  </svg>
);
