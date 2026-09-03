'use client';

import { useSearchParams } from 'next/navigation';
import { motion } from 'framer-motion';
import { CreditCard, Mail, ArrowRight, ShieldAlert, Phone } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';

import { Suspense } from 'react';

function SubscriptionExpiredContent() {
  const searchParams = useSearchParams();
  const managerEmail = searchParams.get('manager');
  const ownerEmail = searchParams.get('owner');

  return (
    <div className="min-h-screen bg-[#0a0a0a] flex items-center justify-center p-4 selection:bg-red-500/30">
      <div className="absolute inset-0 bg-[radial-gradient(circle_at_center,_var(--tw-gradient-stops))] from-red-500/5 via-transparent to-transparent pointer-events-none" />
      
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        className="w-full max-w-lg"
      >
        <Card className="bg-[#111] border-white/10 shadow-2xl relative overflow-hidden">
          <div className="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-red-500 via-rose-500 to-red-500 shadow-[0_0_15px_rgba(239,68,68,0.5)]" />
          
          <CardHeader className="text-center space-y-4 pt-10">
            <motion.div
              animate={{ 
                scale: [1, 1.05, 1],
                opacity: [1, 0.8, 1] 
              }}
              transition={{ repeat: Infinity, duration: 2 }}
              className="mx-auto w-20 h-20 bg-red-500/10 rounded-full flex items-center justify-center mb-2 border border-red-500/20 shadow-[0_0_20px_rgba(239,68,68,0.1)]"
            >
              <ShieldAlert className="w-10 h-10 text-red-500" />
            </motion.div>
            <div className="space-y-1">
              <CardTitle className="text-3xl font-bold text-white tracking-tight">
                Subscription Suspended
              </CardTitle>
              <CardDescription className="text-gray-400 text-base">
                Your branch access has been restricted by Digital Fortress.
              </CardDescription>
            </div>
          </CardHeader>

          <CardContent className="space-y-8 pb-10 px-8">
            <div className="bg-white/5 rounded-2xl p-6 border border-white/5 space-y-4">
              <h3 className="text-sm font-semibold text-gray-500 uppercase tracking-widest flex items-center gap-2">
                <Mail className="w-4 h-4" />
                Contact Decision Makers
              </h3>
              
              <div className="grid gap-3">
                {managerEmail && (
                  <div className="flex items-center justify-between p-3 bg-black/40 rounded-xl border border-white/5 group hover:border-red-500/30 transition-all">
                    <div className="space-y-1">
                      <p className="text-[10px] font-bold text-red-500/70 uppercase tracking-wider">Branch Manager</p>
                      <p className="text-sm text-gray-200 font-medium">{managerEmail}</p>
                    </div>
                    <Button variant="ghost" size="icon" className="hover:bg-red-500/10 text-gray-500 hover:text-red-500" onClick={() => window.location.href = `mailto:${managerEmail}`}>
                      <ArrowRight className="w-4 h-4" />
                    </Button>
                  </div>
                )}
                
                {ownerEmail && (
                  <div className="flex items-center justify-between p-3 bg-black/40 rounded-xl border border-white/5 group hover:border-red-500/30 transition-all">
                    <div className="space-y-1">
                      <p className="text-[10px] font-bold text-red-500/70 uppercase tracking-wider">Platform Owner</p>
                      <p className="text-sm text-gray-200 font-medium">{ownerEmail}</p>
                    </div>
                    <Button variant="ghost" size="icon" className="hover:bg-red-500/10 text-gray-500 hover:text-red-500" onClick={() => window.location.href = `mailto:${ownerEmail}`}>
                      <ArrowRight className="w-4 h-4" />
                    </Button>
                  </div>
                )}

                {!managerEmail && !ownerEmail && (
                  <p className="text-sm text-gray-500 italic text-center py-2">
                    Contact your administrator for resolution.
                  </p>
                )}
              </div>
            </div>

            <div className="flex flex-col gap-3">
              <Button
                className="w-full h-14 bg-red-600 hover:bg-red-500 text-white font-bold text-lg transition-all shadow-[0_0_30px_rgba(239,68,68,0.3)]"
                onClick={() => window.location.href = '/login'}
              >
                Return to Portal
              </Button>
              <div className="flex items-center justify-center gap-4 text-xs font-medium text-gray-600">
                <span className="flex items-center gap-1.5"><CreditCard className="w-3.5 h-3.5" /> Billing Issue</span>
                <span className="w-1 h-1 bg-gray-800 rounded-full" />
                <span className="flex items-center gap-1.5"><Phone className="w-3.5 h-3.5" /> Support 24/7</span>
              </div>
            </div>
          </CardContent>
        </Card>
      </motion.div>
    </div>
  );
}

export default function SubscriptionExpiredPage() {
  return (
    <Suspense fallback={<div className="min-h-screen bg-[#0a0a0a]" />}>
      <SubscriptionExpiredContent />
    </Suspense>
  );
}
