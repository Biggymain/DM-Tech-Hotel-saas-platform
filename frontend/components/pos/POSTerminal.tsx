'use client';

import React, { useState, useEffect } from 'react';
import { 
  Loader2, 
  Search, 
  Plus, 
  Grid3X3, 
  LayoutList,
  ChevronRight,
  User 
} from 'lucide-react';
import { useAuth } from '@/context/AuthProvider';
import StaffTabs from './StaffTabs';
import PinOverlay from './PinOverlay';
import POSMobilePage from '@/app/(dashboard)/pos/mobile/page';
import api from '@/lib/api';
import { toast } from 'sonner';

interface StaffTab {
  id: number;
  name: string;
  token: string;
}

export default function POSTerminal() {
  const { user } = useAuth();
  const [activeTabs, setActiveTabs] = useState<StaffTab[]>([]);
  const [activeId, setActiveId] = useState<number | null>(null);
  const [verifyingStaff, setVerifyingStaff] = useState<{ id: number; name: string } | null>(null);
  const [staffList, setStaffList] = useState<any[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isGridMode, setIsGridMode] = useState(true); // Staff selection grid mode

  // Load tabs from localStorage on mount
  useEffect(() => {
    const saved = localStorage.getItem('pos_active_tabs');
    if (saved) {
      try {
        const parsed = JSON.parse(saved);
        setActiveTabs(parsed);
        // Default to the first tab if any
        if (parsed.length > 0) {
          setActiveId(parsed[0].id);
          setIsGridMode(false);
        }
      } catch (e) {
        console.error('Failed to load POS tabs', e);
      }
    }
    fetchOnDutyStaff();
  }, []);

  // 60s Auto-Lock Idle Timer (Port 3003 Security)
  useEffect(() => {
    if (isGridMode) return; // Already locked/in grid mode

    let timer: NodeJS.Timeout;
    const resetTimer = () => {
      if (timer) clearTimeout(timer);
      timer = setTimeout(() => {
        handleLock();
        toast.info('Session locked due to inactivity.');
      }, 60000); // 60 seconds
    };

    // Events to track activity
    const events = ['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart'];
    events.forEach(event => document.addEventListener(event, resetTimer));

    resetTimer(); // Initialize timer

    return () => {
      if (timer) clearTimeout(timer);
      events.forEach(event => document.removeEventListener(event, resetTimer));
    };
  }, [isGridMode, activeId]);

  const fetchOnDutyStaff = async () => {
    setIsLoading(true);
    try {
      const { data } = await api.get('/api/v1/users?on_duty=true');
      setStaffList(data.data || []);
    } catch (err) {
      toast.error('Could not load on-duty staff list.');
    } finally {
      setIsLoading(false);
    }
  };

  const handleStaffSelect = (staff: any) => {
    // If the staff already has an active tab, switch to it (PIN might still be required depending on security policy)
    const existing = activeTabs.find(t => t.id === staff.id);
    if (existing) {
      setActiveId(existing.id);
      setIsGridMode(false);
      return;
    }
    // Otherwise, trigger PIN verification
    setVerifyingStaff({ id: staff.id, name: staff.name });
  };

  const handlePinSuccess = (token: string) => {
    if (!verifyingStaff) return;

    const newTab = { ...verifyingStaff, token };
    const updatedTabs = [...activeTabs, newTab];
    
    setActiveTabs(updatedTabs);
    localStorage.setItem('pos_active_tabs', JSON.stringify(updatedTabs));
    setActiveId(newTab.id);
    setVerifyingStaff(null);
    setIsGridMode(false);
  };

  const handleLock = () => {
    setIsGridMode(true);
    setActiveId(null);
  };

  if (isLoading && staffList.length === 0) {
    return (
      <div className="h-screen flex flex-col items-center justify-center bg-slate-950 text-white gap-4">
        <Loader2 className="animate-spin text-indigo-500" size={48} />
        <p className="font-black tracking-widest text-xs uppercase animate-pulse">WARMING UP TERMINAL...</p>
      </div>
    );
  }

  return (
    <div className="h-screen flex bg-slate-950 text-white overflow-hidden">
      
      {/* Sidebar - Always persistent */}
      <StaffTabs 
        activeTabs={activeTabs}
        activeId={activeId}
        onSwitch={(tab) => {
          setActiveId(tab.id);
          setIsGridMode(false);
        }}
        onAddStaff={() => setIsGridMode(true)}
        onLock={handleLock}
      />

      {/* Main Workspace */}
      <main className="flex-1 relative overflow-hidden">
        
        {isGridMode ? (
          <div className="h-full flex flex-col p-8 md:p-12 space-y-12 animate-in fade-in slide-in-from-bottom-4 duration-500">
            
            <div className="flex flex-col md:flex-row md:items-end justify-between gap-6">
              <div className="space-y-2">
                <h2 className="text-4xl font-black tracking-tighter uppercase italic">Workstation Login</h2>
                <p className="text-slate-500 font-bold uppercase tracking-widest text-xs">SELECT YOUR IDENTITY TO OPEN A TAB</p>
              </div>
              
              <div className="relative w-full max-w-sm">
                <Search className="absolute left-4 top-1/2 -translate-y-1/2 text-slate-500" size={18} />
                <input 
                  type="text"
                  placeholder="Search staff..."
                  className="w-full bg-white/5 border border-white/10 rounded-2xl py-4 pl-12 pr-4 text-sm focus:ring-2 focus:ring-indigo-500 transition-all font-bold"
                />
              </div>
            </div>

            <div className="grid grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 gap-6 overflow-y-auto pr-2 pb-12">
              {staffList.map((staff) => (
                <button
                  key={staff.id}
                  onClick={() => handleStaffSelect(staff)}
                  className="group relative flex flex-col items-center p-8 bg-slate-900/50 border border-white/5 rounded-[40px] hover:bg-indigo-600 transition-all duration-500 hover:shadow-2xl hover:shadow-indigo-600/20 hover:-translate-y-2"
                >
                  <div className="w-24 h-24 rounded-full bg-slate-800 border-4 border-slate-900 flex items-center justify-center text-3xl font-black mb-6 group-hover:scale-110 group-hover:bg-white/20 transition-all duration-500 group-hover:border-indigo-400">
                    {staff.name.charAt(0)}
                  </div>
                  <h3 className="text-lg font-black tracking-tight uppercase group-hover:text-white transition-colors">{staff.name}</h3>
                  <p className="text-[10px] text-slate-600 font-black tracking-widest uppercase mt-2 group-hover:text-indigo-200 transition-colors">
                    {staff.outlet?.name || 'Waitress'}
                  </p>
                  
                  <div className="absolute top-6 right-6 w-10 h-10 rounded-full bg-white/5 flex items-center justify-center opacity-0 group-hover:opacity-100 transition-all duration-500">
                    <Plus size={20} className="text-white" />
                  </div>
                </button>
              ))}
            </div>
          </div>
        ) : (
          <div className="h-full flex flex-col animate-in fade-in duration-300">
             {/* Pass active context to the actual POS mobile page */}
             <POSMobilePage 
                activeWaitressId={activeId} 
                isTerminalMode={true}
             />
          </div>
        )}

        {/* PIN Overlay */}
        {verifyingStaff && (
          <PinOverlay 
            staffMember={verifyingStaff}
            onSuccess={handlePinSuccess}
            onCancel={() => setVerifyingStaff(null)}
          />
        )}

      </main>
    </div>
  );
}
