'use client';

import * as React from 'react';
import Link from 'next/link';
import { usePathname, useParams } from 'next/navigation';
import { cn } from '@/lib/utils';
import { useAuth } from '@/context/AuthProvider';
import {
  LayoutDashboard,
  CalendarDays,
  Hotel,
  Tags,
  CreditCard,
  UserCircle,
  Settings,
  UtensilsCrossed,
  Users,
  ChevronLeft,
  Store,
  Calendar,
  ChefHat
} from 'lucide-react';
import { useUI } from '@/context/UIContext';

export function SidebarContent({ onItemClick, forcedCollapsed }: { onItemClick?: () => void; forcedCollapsed?: boolean }) {
  const { isSidebarCollapsed: globalIsSidebarCollapsed } = useUI();
  // If onItemClick is provided, we assume we are in the mobile sheet and should not be collapsed
  const isSidebarCollapsed = onItemClick ? false : (forcedCollapsed !== undefined ? forcedCollapsed : globalIsSidebarCollapsed);

  const pathname = usePathname();
  const params = useParams();
  const { user } = useAuth();
  const branchSlug = params.slug as string;

  const navItems = [
    { title: 'Home',         href: `/branch/${branchSlug}/dashboard`,   icon: LayoutDashboard },
    { title: 'Reception',    href: `/reception/${branchSlug}`,           icon: Calendar },
    { title: 'Reservations', href: `/branch/${branchSlug}/reservations`, icon: CalendarDays },
    { title: 'Rooms',        href: `/branch/${branchSlug}/rooms`,        icon: Hotel },
    { title: 'Outlets',      href: `/branch/${branchSlug}/outlets`,      icon: Store },
    { title: 'Pricing',      href: `/branch/${branchSlug}/pricing`,      icon: Tags },
    { title: 'Menu Blueprint', href: `/branch/${branchSlug}/menu-blueprint`, icon: UtensilsCrossed },
    { title: 'Outlet Monitor', href: `/branch/${branchSlug}/outlet-monitor`, icon: ChefHat },
    { title: 'Payments',     href: `/branch/${branchSlug}/payments`,     icon: CreditCard },
    { title: 'Guest Portal', href: `/branch/${branchSlug}/guest-portal`, icon: UserCircle },
    { title: 'Staff',        href: `/branch/${branchSlug}/staff`,        icon: Users },
    { title: 'Settings',     href: `/branch/${branchSlug}/settings`,     icon: Settings },
  ];

  const roleSlugs = user?.roles?.map(r => r.slug.toLowerCase()) || [];
  const isGroupAdmin = !!user?.hotel_group_id && !user?.hotel_id;
  const isSuperAdmin = !!user?.is_super_admin;
  const isGM = roleSlugs.includes('general-manager') || roleSlugs.includes('hotelowner') || roleSlugs.includes('manager') || isGroupAdmin || isSuperAdmin;
  const isReceptionist = roleSlugs.includes('receptionist');
  const isKitchen = roleSlugs.includes('kitchen-manager') || roleSlugs.includes('chef');
  const isPOS = roleSlugs.includes('waiter') || roleSlugs.includes('steward') || roleSlugs.includes('bartender');
  const isHousekeeping = roleSlugs.includes('housekeeping');

  const filteredNavItems = navItems.filter(item => {
    if (isGM) return true;
    
    if (isReceptionist) {
      return ['Reception'].includes(item.title);
    }
    
    if (isKitchen) {
      return ['Home', 'POS / Menus', 'Settings'].includes(item.title);
    }
    
    if (isPOS) {
      return ['Home', 'POS / Menus', 'Payments'].includes(item.title);
    }

    if (isHousekeeping) {
      return ['Home', 'Rooms', 'Settings'].includes(item.title);
    }

    return ['Home'].includes(item.title);
  });

  return (
    <div className="flex flex-col h-full py-4">
      <div className={cn(
        "py-4 flex flex-col mb-4",
        isSidebarCollapsed ? "px-2" : "px-6"
      )}>
        {isGroupAdmin && (
          <Link 
            href="/organization" 
            className={cn(
                "flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-primary hover:text-primary/80 mb-4 group transition-colors",
                isSidebarCollapsed && "justify-center"
            )}
            onClick={onItemClick}
          >
            <ChevronLeft className="h-3 w-3 transition-transform group-hover:-translate-x-0.5" />
            {!isSidebarCollapsed && "Back to Group View"}
          </Link>
        )}
        <div className={cn(
            "flex items-center transition-all duration-300",
            isSidebarCollapsed && "justify-center"
        )}>
          <div className="bg-gradient-to-br from-emerald-500 to-teal-600 p-2 rounded-xl shadow-lg shadow-emerald-500/20 flex-shrink-0">
            <Hotel className="h-4 w-4 text-white" />
          </div>
          {!isSidebarCollapsed && (
            <div className="ml-2.5 overflow-hidden whitespace-nowrap">
              <span className="font-bold text-base tracking-tight block leading-none">
                Property View
              </span>
              <span className="text-[10px] text-emerald-600 font-medium uppercase tracking-widest">
                Branch: {branchSlug}
              </span>
            </div>
          )}
        </div>
      </div>

      <div className="flex-1 px-3 space-y-0.5 overflow-y-auto">
        {filteredNavItems.map((item) => {
          const isActive = pathname === item.href || (item.href.length > 10 && pathname.startsWith(item.href));
          return (
            <Link
              key={item.href}
              href={item.href}
              onClick={onItemClick}
              className={cn(
                'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-all duration-150',
                isActive
                  ? 'bg-emerald-600 text-white shadow-sm shadow-emerald-500/30'
                  : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground',
                isSidebarCollapsed && "justify-center px-2"
              )}
            >
              <item.icon className="h-4 w-4 flex-shrink-0" />
              {!isSidebarCollapsed && <span>{item.title}</span>}
            </Link>
          );
        })}
    </div>

    {user?.license && (
      <div className={cn(
        "mx-3 mt-4 p-3 rounded-xl border transition-all duration-300",
        isSidebarCollapsed ? "flex justify-center px-1" : "px-3 shadow-[0_0_15px_rgba(37,99,235,0.05)]",
        (user.license.days_remaining ?? 0) > 30 ? "bg-emerald-500/5 border-emerald-500/20" :
        (user.license.days_remaining ?? 0) > 7 ? "bg-amber-500/5 border-amber-500/20" :
        "bg-red-500/5 border-red-500/20 shadow-[0_0_15px_rgba(239,68,68,0.1)] animate-pulse-subtle"
      )}>
        {isSidebarCollapsed ? (
          <div className={cn(
            "w-2 h-2 rounded-full",
            (user.license.days_remaining ?? 0) > 30 ? "bg-emerald-500" :
            (user.license.days_remaining ?? 0) > 7 ? "bg-amber-500" : "bg-red-500"
          )} />
        ) : (
          <div className="space-y-1.5">
            <div className="flex items-center justify-between">
              <span className="text-[10px] font-bold uppercase tracking-wider text-muted-foreground/70">License Status</span>
              <span className={cn(
                "text-[10px] font-bold uppercase",
                (user.license.days_remaining ?? 0) > 30 ? "text-emerald-500" :
                (user.license.days_remaining ?? 0) > 7 ? "text-amber-500 font-extrabold" : "text-red-500 font-black animate-pulse"
              )}>
                {(user.license.days_remaining ?? 0) <= 0 ? 'Expired' : `${user.license.days_remaining} Days`}
              </span>
            </div>
            <div className="w-full bg-black/20 h-1 rounded-full overflow-hidden">
              <div 
                className={cn(
                  "h-full transition-all duration-1000 ease-out",
                  (user.license.days_remaining ?? 0) > 30 ? "bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.3)]" :
                  (user.license.days_remaining ?? 0) > 7 ? "bg-amber-500 shadow-[0_0_8px_rgba(245,158,11,0.3)]" : "bg-red-500 shadow-[0_0_10px_rgba(239,68,68,0.5)]"
                )}
                style={{ width: `${Math.max(5, Math.min(100, ((user.license.days_remaining ?? 0) / 365) * 100))}%` }}
              />
            </div>
          </div>
        )}
      </div>
    )}

    {user && (
        <div className={cn(
          "mx-3 mt-4 p-3 rounded-xl bg-muted/30 border border-border/30 transition-all duration-300",
          isSidebarCollapsed ? "px-1 flex justify-center" : "px-3"
        )}>
          {isSidebarCollapsed ? (
            <div className="h-6 w-6 rounded-full bg-emerald-500/20 flex items-center justify-center text-[10px] font-bold text-emerald-600">
              {user.name.charAt(0)}
            </div>
          ) : (
            <>
              <p className="text-xs font-semibold text-foreground truncate">{user.name}</p>
              <p className="text-[10px] text-muted-foreground truncate">{user.email}</p>
              <p className="text-[10px] text-emerald-600/70 font-medium uppercase tracking-widest mt-0.5">
                {user.roles?.[0]?.name || 'Staff'}
              </p>
            </>
          )}
        </div>
      )}
    </div>
  );
}
