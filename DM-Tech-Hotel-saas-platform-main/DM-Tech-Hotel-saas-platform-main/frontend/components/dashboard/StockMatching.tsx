'use client';

import React from 'react';
import { 
  ArrowRight, 
  AlertTriangle, 
  CheckCircle2, 
  ArrowDownLeft, 
  ArrowUpRight,
  Calculator,
  User,
  Package
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { 
  Table, 
  TableBody, 
  TableCell, 
  TableHead, 
  TableHeader, 
  TableRow 
} from '@/components/ui/table';

interface StockTransfer {
  id: number;
  item: { name: string };
  quantity_dispatched: number;
  quantity_received: number;
  dispatched_at: string;
  received_at: string;
  status: string;
}

interface StockMatchingProps {
  transfers: StockTransfer[];
}

export default function StockMatching({ transfers }: StockMatchingProps) {
  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div className="space-y-1">
          <h2 className="text-2xl font-black text-white tracking-tight uppercase italic flex items-center gap-2">
            <Calculator className="text-indigo-500" />
            Double Confirmation Audit
          </h2>
          <p className="text-xs text-slate-500 font-medium uppercase tracking-widest">
            Cross-checking Storekeeper Dispatch vs Outlet Receipt
          </p>
        </div>
        
        <div className="flex items-center gap-4">
           {/* Discrepancy Counter */}
           <div className="bg-rose-500/10 border border-rose-500/20 px-4 py-2 rounded-2xl flex items-center gap-3">
              <AlertTriangle className="text-rose-500" size={16} />
              <div className="text-left">
                 <p className="text-[10px] font-black text-rose-600 uppercase tracking-widest leading-none">Discrepancies</p>
                 <p className="text-sm font-bold text-rose-500">{transfers.filter(t => t.quantity_dispatched !== t.quantity_received).length} Found</p>
              </div>
           </div>
        </div>
      </div>

      <div className="bg-slate-900 border border-slate-800 rounded-[32px] overflow-hidden shadow-2xl">
        <Table>
          <TableHeader className="bg-white/5">
            <TableRow className="border-white/5 hover:bg-transparent">
              <TableHead className="text-[10px] font-black text-slate-500 uppercase tracking-widest py-6 pl-8">ITEM & ID</TableHead>
              <TableHead className="text-[10px] font-black text-slate-500 uppercase tracking-widest text-center">STORE DISPATCHED</TableHead>
              <TableHead className="text-[10px] font-black text-slate-500 uppercase tracking-widest text-center">OUTLET RECEIVED</TableHead>
              <TableHead className="text-[10px] font-black text-slate-500 uppercase tracking-widest text-center">VARIANCE</TableHead>
              <TableHead className="text-[10px] font-black text-slate-500 uppercase tracking-widest text-right pr-8">AUDIT STATUS</TableHead>
            </TableRow>
          </TableHeader>
          <TableBody>
            {transfers.map((transfer) => {
              const variance = transfer.quantity_dispatched - transfer.quantity_received;
              const hasDiscrepancy = variance !== 0;

              return (
                <TableRow key={transfer.id} className="border-white/5 hover:bg-white/5 transition-colors">
                  <TableCell className="py-6 pl-8">
                    <div className="flex items-center gap-3">
                      <div className="w-10 h-10 bg-slate-800 rounded-xl flex items-center justify-center text-indigo-400">
                        <Package size={20} />
                      </div>
                      <div>
                        <p className="font-bold text-white text-sm">{transfer.item.name}</p>
                        <p className="text-[10px] text-slate-600 font-bold uppercase tracking-widest">TRF-#{transfer.id}</p>
                      </div>
                    </div>
                  </TableCell>
                  
                  <TableCell className="text-center">
                    <div className="inline-flex items-center gap-2 px-3 py-1.5 bg-slate-800 rounded-xl">
                      <ArrowUpRight className="text-amber-500" size={14} />
                      <span className="text-sm font-black text-white">{transfer.quantity_dispatched}</span>
                    </div>
                  </TableCell>

                  <TableCell className="text-center">
                    <div className="inline-flex items-center gap-2 px-3 py-1.5 bg-slate-800 rounded-xl">
                      <ArrowDownLeft className="text-indigo-500" size={14} />
                      <span className="text-sm font-black text-white">{transfer.quantity_received}</span>
                    </div>
                  </TableCell>

                  <TableCell className="text-center">
                    <span className={`text-sm font-black ${
                      hasDiscrepancy ? 'text-rose-500 underline decoration-rose-500/30' : 'text-emerald-500'
                    }`}>
                      {variance > 0 ? `+${variance}` : variance}
                    </span>
                  </TableCell>

                  <TableCell className="text-right pr-8">
                    {hasDiscrepancy ? (
                      <Badge className="bg-rose-500/10 text-rose-500 border-rose-500/20 font-black text-[10px] px-3 py-1 rounded-lg">
                        <AlertTriangle className="mr-1.5" size={12} />
                        MISMATCH
                      </Badge>
                    ) : (
                      <Badge className="bg-emerald-500/10 text-emerald-500 border-emerald-500/20 font-black text-[10px] px-3 py-1 rounded-lg">
                        <CheckCircle2 className="mr-1.5" size={12} />
                        VERIFIED
                      </Badge>
                    )}
                  </TableCell>
                </TableRow>
              );
            })}
          </TableBody>
        </Table>
      </div>

    </div>
  );
}
