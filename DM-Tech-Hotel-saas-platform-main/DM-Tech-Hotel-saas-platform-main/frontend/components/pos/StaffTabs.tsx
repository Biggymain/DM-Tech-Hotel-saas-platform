'use client';

import React from 'react';
import { 
  Users, 
  Lock, 
  UserPlus, 
  ChevronLeft,
  ChevronRight,
  LogOut,
  Settings
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/components/ui/tooltip';

interface StaffTab {
  id: number;
  name: string;
  token: string;
}

interface StaffTabsProps {
  activeTabs: StaffTab[];
  activeId: number | null;
  onSwitch: (tab: StaffTab) => void;
  onAddStaff: () => void;
  onLock: () => void;
  isManagerView?: boolean;
}

export default function StaffTabs({ 
  activeTabs, 
  activeId, 
  onSwitch, 
  onAddStaff, 
  onLock,
  isManagerView = false
}: StaffTabsProps) {
  return (
    <div className="flex flex-col h-full bg-slate-950 border-r border-slate-900 w-20 md:w-64 transition-all duration-300">
      
      {/* Brand / Logo Section */}
      <div className="p-6 flex items-center gap-3 border-b border-white/5">
        <div className="w-10 h-10 bg-indigo-600 rounded-xl flex items-center justify-center shadow-lg shadow-indigo-600/20">
          <Lock className="text-white" size={20} />
        </div>
        <div className="hidden md:block">
          <h1 className="text-sm font-black tracking-tighter text-white uppercase italic">TERMINAL OPS</h1>
          <p className="text-[10px] text-slate-500 font-bold tracking-widest uppercase">STATION PORT 3003</p>
        </div>
      </div>

      {/* Staff Tabs List */}
      <div className="flex-1 overflow-y-auto py-6 space-y-2 px-3">
        <p className="hidden md:block text-[10px] text-slate-600 font-black tracking-widest uppercase mb-4 pl-3">ACTIVE SESSIONS</p>
        
        {activeTabs.map((tab) => {
          const isActive = tab.id === activeId;
          return (
            <TooltipProvider key={tab.id}>
              <Tooltip>
                <TooltipTrigger
                  onClick={() => onSwitch(tab)}
                  className={`relative w-full flex items-center gap-3 p-3 rounded-2xl transition-all duration-300 group ${
                    isActive 
                      ? 'bg-indigo-600 text-white shadow-xl shadow-indigo-600/10' 
                      : 'text-slate-500 hover:bg-white/5 hover:text-slate-300'
                  }`}
                >
                  <div className={`w-10 h-10 rounded-xl flex items-center justify-center text-sm font-bold border-2 transition-colors ${
                    isActive ? 'bg-white/20 border-white/20' : 'bg-slate-900 border-slate-800 group-hover:border-slate-700'
                  }`}>
                    {tab.name.charAt(0)}
                  </div>
                  <div className="hidden md:block text-left overflow-hidden">
                    <p className="text-sm font-bold truncate">{tab.name}</p>
                    <p className={`text-[10px] font-bold uppercase transition-colors ${isActive ? 'text-indigo-200' : 'text-slate-600'}`}>
                      {isActive ? 'Active Now' : 'Tap to Switch'}
                    </p>
                  </div>
                  {isActive && (
                    <div className="absolute right-3 hidden md:block">
                      <ChevronRight size={16} />
                    </div>
                  )}
                </TooltipTrigger>
                <TooltipContent side="right" className="md:hidden bg-slate-900 border-slate-800">
                  <p className="font-bold text-xs">{tab.name}</p>
                </TooltipContent>
              </Tooltip>
            </TooltipProvider>
          );
        })}

        {/* Add Staff Button */}
        <button
          onClick={onAddStaff}
          className="w-full flex items-center gap-3 p-3 rounded-2xl text-emerald-500 hover:bg-emerald-500/5 transition-all duration-300 group mt-4 border border-dashed border-emerald-500/20 hover:border-emerald-500/50"
        >
          <div className="w-10 h-10 rounded-xl flex items-center justify-center bg-emerald-500/10 transition-colors group-hover:bg-emerald-500/20">
            <UserPlus size={20} />
          </div>
          <div className="hidden md:block text-left">
            <p className="text-sm font-bold">New Shift</p>
            <p className="text-[10px] font-bold text-emerald-600 uppercase">JOIN WORKSTATION</p>
          </div>
        </button>
      </div>

      {/* Footer / Global Actions */}
      <div className="p-4 border-t border-white/5 space-y-2">
        <button
          onClick={onLock}
          className="w-full flex items-center gap-3 p-3 rounded-2xl text-rose-500 hover:bg-rose-500/5 transition-all duration-300 group"
        >
          <div className="w-10 h-10 rounded-xl flex items-center justify-center bg-rose-500/10 group-hover:bg-rose-500/20">
            <LogOut size={20} />
          </div>
          <div className="hidden md:block text-left">
            <p className="text-sm font-bold">Quick Exit</p>
            <p className="text-[10px] font-bold text-rose-600 uppercase">LOCK TERMINAL</p>
          </div>
        </button>
        
        {isManagerView && (
          <div className="hidden md:block p-3 rounded-xl bg-slate-900/50 border border-white/5">
             <div className="flex items-center justify-between mb-2">
                <span className="text-[10px] font-black text-slate-500 uppercase">Manager Mode</span>
                <Settings size={12} className="text-slate-500" />
             </div>
             <p className="text-[10px] text-slate-400 font-medium leading-relaxed italic">
                Master View is active. You can see all active orders.
             </p>
          </div>
        )}
      </div>
    </div>
  );
}
