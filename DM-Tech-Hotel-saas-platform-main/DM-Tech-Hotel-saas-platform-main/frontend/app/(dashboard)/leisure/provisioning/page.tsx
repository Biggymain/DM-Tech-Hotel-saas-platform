'use client';

import React, { useState, useEffect } from 'react';
import { 
  Building2, 
  Cpu, 
  PackageCheck, 
  UserCheck, 
  ArrowRight,
  ShieldCheck,
  CheckCircle2,
  Loader2
} from 'lucide-react';
import { useAuth } from '@/context/AuthProvider';
import api from '@/lib/api';
import { toast } from 'sonner';

export default function ProvisioningPage() {
  const { user } = useAuth();
  const [outlets, setOutlets] = useState<any[]>([]);
  const [staff, setStaff] = useState<any[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const [formData, setFormData] = useState({
    outlet_id: '',
    hardware_bridge_id: '',
    inventory_source_outlet_id: '',
    supervisor_id: ''
  });

  useEffect(() => {
    const fetchData = async () => {
      try {
        const [outletsRes, staffRes] = await Promise.all([
          api.get('/api/v1/outlets'),
          api.get('/api/v1/users')
        ]);
        setOutlets(outletsRes.data.data || []);
        setStaff(staffRes.data.data || []);
      } catch (err) {
        toast.error('Failed to load provisioning data.');
      } finally {
        setIsLoading(false);
      }
    };
    fetchData();
  }, []);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setIsSubmitting(true);
    try {
      await api.post('/api/v1/leisure/provision', formData);
      toast.success('Leisure Hub provisioned successfully!');
    } catch (err: any) {
      toast.error(err.response?.data?.message || 'Provisioning failed.');
    } finally {
      setIsSubmitting(false);
    }
  };

  if (isLoading) {
    return (
      <div className="h-full flex items-center justify-center bg-slate-950">
        <Loader2 className="animate-spin text-indigo-500" size={32} />
      </div>
    );
  }

  return (
    <div className="p-8 max-w-5xl mx-auto space-y-12 animate-in fade-in slide-in-from-bottom-4 duration-500">
      
      <div className="space-y-4">
        <div className="flex items-center gap-3">
          <div className="w-12 h-12 bg-indigo-600 rounded-2xl flex items-center justify-center shadow-lg shadow-indigo-600/20">
            <Cpu className="text-white" size={24} />
          </div>
          <h1 className="text-4xl font-black tracking-tighter uppercase italic">Module Provisioning</h1>
        </div>
        <p className="text-slate-500 font-bold uppercase tracking-widest text-xs max-w-2xl">
          Setup hardware bridges and inventory custody for your Leisure Hub. Port 3003 isolation requires explicit link between physical sensors and stock transactions.
        </p>
      </div>

      <form onSubmit={handleSubmit} className="grid md:grid-cols-2 gap-8">
        
        {/* Core Links */}
        <div className="space-y-6">
          <div className="p-8 bg-slate-900/50 border border-white/5 rounded-[40px] space-y-8 backdrop-blur-xl">
             <div className="flex items-center gap-3 border-b border-white/5 pb-6">
                <Building2 className="text-indigo-400" size={20} />
                <h3 className="font-black text-sm uppercase tracking-wider">Facility Alignment</h3>
             </div>

             <div className="space-y-6">
                <div className="space-y-2">
                  <label className="text-[10px] font-black text-slate-500 uppercase tracking-widest pl-1">Select Outlet</label>
                  <select 
                    required
                    value={formData.outlet_id}
                    onChange={e => setFormData({...formData, outlet_id: e.target.value})}
                    className="w-full bg-slate-950 border border-white/10 rounded-2xl p-4 text-sm font-bold focus:ring-2 focus:ring-indigo-500 outline-none appearance-none"
                  >
                    <option value="">Select an outlet...</option>
                    {outlets.map(o => (
                      <option key={o.id} value={o.id}>{o.name}</option>
                    ))}
                  </select>
                </div>

                <div className="space-y-2">
                  <label className="text-[10px] font-black text-slate-500 uppercase tracking-widest pl-1">Hardware Bridge ID</label>
                  <div className="relative">
                    <Cpu className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-500" size={18} />
                    <input 
                      type="text"
                      required
                      placeholder="e.g. HUB-POOL-001"
                      value={formData.hardware_bridge_id}
                      onChange={e => setFormData({...formData, hardware_bridge_id: e.target.value})}
                      className="w-full bg-slate-950 border border-white/10 rounded-2xl p-4 pl-12 text-sm font-bold focus:ring-2 focus:ring-indigo-500"
                    />
                  </div>
                </div>
             </div>
          </div>

          <div className="p-8 bg-slate-900/50 border border-white/5 rounded-[40px] space-y-8 backdrop-blur-xl">
             <div className="flex items-center gap-3 border-b border-white/5 pb-6">
                <PackageCheck className="text-emerald-400" size={20} />
                <h3 className="font-black text-sm uppercase tracking-wider">Chain of Custody</h3>
             </div>

             <div className="space-y-2">
                <label className="text-[10px] font-black text-slate-500 uppercase tracking-widest pl-1">Inventory Source (Storekeeper)</label>
                <select 
                  required
                  value={formData.inventory_source_outlet_id}
                  onChange={e => setFormData({...formData, inventory_source_outlet_id: e.target.value})}
                  className="w-full bg-slate-950 border border-white/10 rounded-2xl p-4 text-sm font-bold focus:ring-2 focus:ring-indigo-500 outline-none appearance-none"
                >
                  <option value="">Select source outlet...</option>
                  {outlets.map(o => (
                    <option key={o.id} value={o.id}>{o.name} (Chain of Custody)</option>
                  ))}
                </select>
                <p className="text-[10px] text-slate-600 font-bold p-2 italic">
                  * Mandatory link: Every Pool Pass requires 1x Item deduction from this source.
                </p>
             </div>
          </div>
        </div>

        {/* Supervision & Status */}
        <div className="space-y-6">
          <div className="p-8 bg-slate-900/50 border border-white/5 rounded-[40px] space-y-8 backdrop-blur-xl h-full">
             <div className="flex items-center gap-3 border-b border-white/5 pb-6">
                <UserCheck className="text-amber-400" size={20} />
                <h3 className="font-black text-sm uppercase tracking-wider">Supervision</h3>
             </div>

             <div className="space-y-6">
                <div className="space-y-2">
                  <label className="text-[10px] font-black text-slate-500 uppercase tracking-widest pl-1">Module Supervisor</label>
                  <select 
                    value={formData.supervisor_id}
                    onChange={e => setFormData({...formData, supervisor_id: e.target.value})}
                    className="w-full bg-slate-950 border border-white/10 rounded-2xl p-4 text-sm font-bold focus:ring-2 focus:ring-indigo-500 outline-none appearance-none"
                  >
                    <option value="">Select supervisor...</option>
                    {staff.map(s => (
                      <option key={s.id} value={s.id}>{s.name} ({s.role?.name})</option>
                    ))}
                  </select>
                </div>

                <div className="pt-8 space-y-6">
                  <h4 className="text-[10px] font-black text-slate-600 uppercase tracking-widest">Active Guardrails</h4>
                  <div className="space-y-4">
                    {[
                      { icon: ShieldCheck, text: "Waitress Daily PIN Isolation (12h TTL)", color: "text-indigo-500" },
                      { icon: CheckCircle2, text: "Mandatory Drink-Check Validation", color: "text-emerald-500" },
                      { icon: ArrowRight, text: "Triangle of Truth Enforcement", color: "text-amber-500" }
                    ].map((item, id) => (
                      <div key={id} className="flex items-center gap-3 p-4 bg-white/5 rounded-2xl border border-white/5">
                        <item.icon className={item.color} size={16} />
                        <span className="text-[10px] font-bold text-slate-400 uppercase">{item.text}</span>
                      </div>
                    ))}
                  </div>
                </div>

                <div className="pt-12">
                   <button 
                     type="submit"
                     disabled={isSubmitting}
                     className="w-full py-6 bg-indigo-600 rounded-[30px] font-black tracking-tighter uppercase italic flex items-center justify-center gap-3 hover:bg-indigo-500 transition-all shadow-xl shadow-indigo-600/20 disabled:opacity-50"
                   >
                     {isSubmitting ? <Loader2 className="animate-spin" size={20} /> : "Finalize Provisioning"}
                   </button>
                </div>
             </div>
          </div>
        </div>

      </form>
    </div>
  );
}
