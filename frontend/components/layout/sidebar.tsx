'use client';

import * as React from 'react';
import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { cn } from '@/lib/utils';
import { useAuth } from '@/context/AuthProvider';
import {
  LayoutDashboard,
  CalendarDays,
  Hotel,
  Tags,
  Link as LinkIcon,
  CreditCard,
  UserCircle,
  BarChart3,
  Sparkles,
  Settings,
  Building2,
} from 'lucide-react';
import { useUI } from '@/context/UIContext';

const baseNavItems = [
  { title: 'Dashboard',    href: '/dashboard',           icon: LayoutDashboard },
  { title: 'Reservations', href: '/reservations',         icon: CalendarDays },
  { title: 'Rooms',        href: '/rooms',                icon: Hotel },
  { title: 'Pricing',      href: '/pricing',              icon: Tags },
  { title: 'OTA Channels', href: '/ota-channels',         icon: LinkIcon },
  { title: 'Payments',     href: '/payments',             icon: CreditCard },
  { title: 'Guest Portal', href: '/guest-portal',         icon: UserCircle },
  { title: 'Reports',      href: '/reports',              icon: BarChart3 },
  { title: 'Intelligence', href: '/pricing/intelligence', icon: Sparkles },
  { title: 'Website Creator', href: '/organization/website', icon: Sparkles },
  { title: 'Settings',     href: '/settings/branding',    icon: Settings },
];

export function Sidebar() {
  const pathname = usePathname();
  const { user } = useAuth();
  const { isSidebarCollapsed } = useUI();
  const [isHovered, setIsHovered] = React.useState(false);
  const [mounted, setMounted] = React.useState(false);

  React.useEffect(() => {
    setMounted(true);
  }, []);

  // Show Organization tab only to GROUP_ADMIN (hotel_group_id set) or SUPER_ADMIN
  const isGroupAdmin = !!user?.hotel_group_id && !user?.hotel_id;
  const isSuperAdmin = !!user?.is_super_admin;
  const showGroupTools = isSuperAdmin || isGroupAdmin;

  const filteredBaseNavItems = baseNavItems.filter((item) => {
    // Hide Group Management level features from branch-only users
    if (['/organization', '/pricing/intelligence'].includes(item.href)) {
      return showGroupTools;
    }
    // Receptionist/Staff shouldn't see Intelligence or OTA management usually
    const isRestrictedRole = user?.roles?.some(r => ['receptionist', 'housekeeping', 'restaurantstaff', 'kitchenstaff'].includes(r.slug.toLowerCase()));
    if (isRestrictedRole && ['/pricing/intelligence', '/ota-channels', '/reports'].includes(item.href)) {
      return false;
    }
    return true;
  });

  const navItems = showGroupTools
    ? [
        ...filteredBaseNavItems.slice(0, 1),
        { 
          title: isSuperAdmin ? 'Platform' : 'Group Management', 
          href: '/organization', 
          icon: Building2 
        },
        ...filteredBaseNavItems.slice(1),
      ]
    : filteredBaseNavItems;

  const isActuallyCollapsed = isSidebarCollapsed && !isHovered;

  return (
    <nav 
      onMouseEnter={() => setIsHovered(true)}
      onMouseLeave={() => setIsHovered(false)}
      className={cn(
        "flex flex-col border-r bg-card min-h-screen pt-4 pb-12 overflow-y-auto hidden md:flex transition-all duration-300 ease-in-out z-40 relative shadow-xl border-border/50",
        isActuallyCollapsed ? "w-20" : "w-64"
      )}
    >
      <div className={cn(
        "px-6 py-4 flex items-center mb-4 transition-all duration-300",
        isActuallyCollapsed && "px-4 justify-center"
      )}>
        <div className="bg-gradient-to-br from-primary to-violet-600 p-2 rounded-xl shadow-lg shadow-primary/20 flex-shrink-0">
          <Hotel className="h-4 w-4 text-white" />
        </div>
        {!isActuallyCollapsed && (
          <div className="ml-2.5 overflow-hidden whitespace-nowrap">
            <span className="font-bold text-base tracking-tight block leading-none">
              {mounted && localStorage.getItem('hotel_context') ? 'Hotel Dashboard' : 'HotelSaaS'}
            </span>
            <span className="text-[10px] text-primary/70 font-medium uppercase tracking-widest">
              {isSuperAdmin ? 'Super Admin' : isGroupAdmin ? 'Group Admin' : 'Branch Management'}
            </span>
          </div>
        )}
      </div>

      <div className="flex-1 px-3 space-y-0.5">
        {navItems.map((item) => {
          const isActive = pathname === item.href || (item.href !== '/' && pathname.startsWith(item.href));
          const isOrg = item.href === '/organization';
          return (
            <Link
              key={item.href}
              href={item.href}
              className={cn(
                'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-all duration-150',
                isActive
                  ? 'bg-primary text-primary-foreground shadow-sm shadow-primary/30'
                  : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground',
                isOrg && !isActive && 'border border-primary/20 bg-primary/5 text-primary hover:bg-primary/10',
                isActuallyCollapsed && "justify-center px-2"
              )}
            >
              <item.icon className="h-4 w-4 flex-shrink-0" />
              {!isActuallyCollapsed && (
                <>
                  <span className="truncate">{item.title}</span>
                  {isOrg && !isActive && (
                    <span className="ml-auto text-[9px] font-bold uppercase tracking-wider bg-primary/10 text-primary px-1.5 py-0.5 rounded-full">
                      {user?.is_super_admin ? 'Admin' : 'Group'}
                    </span>
                  )}
                </>
              )}
            </Link>
          );
        })}
      </div>

      {/* Bottom user info */}
      {user && (
        <div className={cn(
          "mx-3 mt-4 p-3 rounded-xl bg-muted/30 border border-border/30 transition-all duration-300",
          isActuallyCollapsed ? "px-1 flex justify-center" : "px-3"
        )}>
          {isActuallyCollapsed ? (
            <div className="h-6 w-6 rounded-full bg-primary/20 flex items-center justify-center text-[10px] font-bold text-primary">
              {user.name.charAt(0)}
            </div>
          ) : (
            <>
              <p className="text-xs font-semibold text-foreground truncate">{user.name}</p>
              <p className="text-[10px] text-muted-foreground truncate">{user.email}</p>
              <p className="text-[10px] text-primary/60 font-medium uppercase tracking-widest mt-0.5">
                {user.is_super_admin ? 'Super Admin' : isGroupAdmin ? 'Group Admin' : 'Branch User'}
              </p>
            </>
          )}
        </div>
      )}
    </nav>
  );
}
