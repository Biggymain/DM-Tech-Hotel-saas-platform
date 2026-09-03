'use client';

import React from 'react';
import { 
  QrCode, 
  MapPin, 
  Calendar, 
  ShieldCheck,
  Building2,
  Lock,
  Printer
} from 'lucide-react';

interface Membership {
  id: number;
  user: {
    name: string;
    email: string;
  };
  type: string;
  starts_at: string;
  expires_at: string;
  status: string;
}

interface MembershipCardProps {
  membership: Membership;
  hotelName: string;
}

export default function MembershipCard({ membership, hotelName }: MembershipCardProps) {
  const handlePrint = () => {
    window.print();
  };

  return (
    <div className="space-y-8 no-print">
      
      {/* Visual Preview */}
      <div className="relative w-[400px] h-[250px] bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 rounded-[30px] p-8 overflow-hidden shadow-2xl border border-white/10 group print:border-slate-800 print:shadow-none print:bg-white print:text-black">
        
        {/* Background Patterns */}
        <div className="absolute top-0 right-0 w-64 h-64 bg-indigo-500/5 blur-3xl rounded-full -translate-y-1/2 translate-x-1/2 group-hover:bg-indigo-500/10 transition-all duration-700"></div>
        <div className="absolute bottom-0 left-0 w-32 h-32 bg-emerald-500/5 blur-3xl rounded-full translate-y-1/2 -translate-x-1/2 group-hover:bg-emerald-500/10 transition-all duration-700"></div>
        
        {/* Card Content */}
        <div className="relative h-full flex flex-col justify-between">
          
          <div className="flex justify-between items-start">
            <div className="space-y-1">
              <div className="flex items-center gap-2">
                <div className="w-6 h-6 bg-indigo-600 rounded-lg flex items-center justify-center">
                  <Lock className="text-white" size={12} />
                </div>
                <h2 className="text-[10px] font-black tracking-tighter uppercase italic text-white print:text-black">Port 3003 Access</h2>
              </div>
              <p className="text-[10px] text-slate-500 font-bold tracking-widest uppercase print:text-slate-700">{hotelName}</p>
            </div>
            
            <div className="w-16 h-16 bg-white rounded-xl p-2 shadow-xl">
               <QrCode className="text-black w-full h-full" />
            </div>
          </div>

          <div className="space-y-4">
            <div>
              <p className="text-[8px] text-indigo-400 font-black tracking-widest uppercase mb-1">Authenticated Member</p>
              <h3 className="text-xl font-black tracking-tight text-white uppercase truncate print:text-black">{membership.user.name}</h3>
            </div>

            <div className="flex justify-between items-end">
               <div className="flex gap-4">
                  <div className="space-y-1">
                    <p className="text-[8px] text-slate-500 font-bold uppercase tracking-widest print:text-slate-600">Validity</p>
                    <div className="flex items-center gap-1">
                      <Calendar className="text-indigo-500" size={10} />
                      <span className="text-[10px] font-black text-slate-300 print:text-black">{new Date(membership.expires_at).toLocaleDateString()}</span>
                    </div>
                  </div>
                  <div className="space-y-1">
                    <p className="text-[8px] text-slate-500 font-bold uppercase tracking-widest print:text-slate-600">Tier</p>
                    <div className="flex items-center gap-1">
                      <ShieldCheck className="text-emerald-500" size={10} />
                      <span className="text-[10px] font-black text-slate-300 print:text-black uppercase">{membership.type}</span>
                    </div>
                  </div>
               </div>

               <div className="text-[8px] text-slate-600 font-bold tracking-widest uppercase text-right">
                  ID: {membership.id.toString().padStart(6, '0')}
               </div>
            </div>
          </div>
        </div>
      </div>

      <button
        onClick={handlePrint}
        className="w-full flex items-center justify-center gap-3 py-4 bg-white/5 border border-white/10 rounded-2xl text-xs font-black tracking-widest uppercase text-slate-400 hover:bg-white/10 hover:text-white transition-all group"
      >
        <Printer size={16} className="group-hover:text-indigo-500 transition-colors" />
        Print Physical Membership Card
      </button>

      {/* Global Print Styles */}
      <style jsx global>{`
        @media print {
          body * {
            visibility: hidden;
          }
          .print-only, .print-only * {
            visibility: visible;
          }
          .no-print {
            display: none !important;
          }
          @page {
            size: 85mm 54mm;
            margin: 0;
          }
          .relative.w-\[400px\] {
            visibility: visible !important;
            position: absolute !important;
            left: 0 !important;
            top: 0 !important;
            width: 100% !important;
            height: 100% !important;
            padding: 20px !important;
            border-radius: 0 !important;
            border: none !important;
          }
          .relative.w-\[400px\] * {
            visibility: visible !important;
          }
        }
      `}</style>
    </div>
  );
}
