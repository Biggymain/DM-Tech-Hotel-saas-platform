'use client';

import React from 'react';
import { useRouter } from 'next/navigation';
import { motion } from 'framer-motion';
import { CheckCircle2, Home, Star, ArrowRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

export default function SessionExpiredPage() {
  const router = useRouter();

  return (
    <div className="min-h-screen flex items-center justify-center p-4 bg-slate-950 overflow-hidden relative">
      {/* Decorative Gradients */}
      <div className="absolute top-[-10%] left-[-10%] w-[40%] h-[40%] bg-indigo-500/20 blur-[120px] rounded-full" />
      <div className="absolute bottom-[-10%] right-[-10%] w-[40%] h-[40%] bg-purple-500/20 blur-[120px] rounded-full" />

      <Card className="w-full max-w-md bg-white/5 border-white/10 backdrop-blur-2xl shadow-2xl relative z-10">
        <CardHeader className="text-center pb-2">
          <motion.div 
            initial={{ scale: 0 }}
            animate={{ scale: 1 }}
            transition={{ type: "spring", stiffness: 260, damping: 20 }}
            className="flex justify-center mb-6"
          >
            <div className="p-4 rounded-full bg-emerald-500/20 text-emerald-500 ring-8 ring-emerald-500/10">
              <CheckCircle2 size={56} />
            </div>
          </motion.div>
          <motion.div
            initial={{ opacity: 0, y: 10 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.2 }}
          >
            <CardTitle className="text-3xl font-extrabold text-white mb-2 tracking-tight">
              Session Completed
            </CardTitle>
            <p className="text-slate-400 text-lg">
              Thank you for choosing DM Tech Hotels.
            </p>
          </motion.div>
        </CardHeader>
        
        <CardContent className="space-y-8 pt-6">
          <motion.div 
             initial={{ opacity: 0 }}
             animate={{ opacity: 1 }}
             transition={{ delay: 0.4 }}
             className="p-6 rounded-2xl bg-white/5 border border-white/10 text-center space-y-4"
          >
            <p className="text-slate-300 leading-relaxed">
              Your stay session has been successfully closed. All temporary digital keys and access permissions have been revoked.
            </p>
            <div className="flex justify-center gap-1 text-yellow-500">
              {[1, 2, 3, 4, 5].map((i) => (
                <Star key={i} size={16} fill="currentColor" />
              ))}
            </div>
            <p className="text-xs text-slate-500 uppercase tracking-widest font-semibold">
              Premium Guest Experience
            </p>
          </motion.div>
          
          <motion.div 
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.6 }}
            className="grid gap-3"
          >
            <Button 
              onClick={() => router.push('/')}
              className="w-full h-14 bg-indigo-600 hover:bg-indigo-500 text-white font-bold rounded-2xl shadow-lg shadow-indigo-600/20 transition-all group"
            >
              <Home className="mr-2 h-5 w-5" /> 
              Return to Homepage
              <ArrowRight className="ml-2 h-4 w-4 opacity-0 group-hover:opacity-100 transition-all translate-x-[-4px] group-hover:translate-x-0" />
            </Button>
            
            <p className="text-center text-sm text-slate-500">
              Need assistance? Contact our 24/7 concierge.
            </p>
          </motion.div>
        </CardContent>
      </Card>
    </div>
  );
}
