'use client';

import * as React from 'react';
import { SidebarContent } from './sidebar-content';
import { useUI } from '@/context/UIContext';
import { cn } from '@/lib/utils';

export function BranchSidebar() {
  const { isSidebarCollapsed } = useUI();
  const [isHovered, setIsHovered] = React.useState(false);

  const isActuallyCollapsed = isSidebarCollapsed && !isHovered;

  return (
    <nav 
      onMouseEnter={() => setIsHovered(true)}
      onMouseLeave={() => setIsHovered(false)}
      className={cn(
        "flex flex-col border-r bg-card min-h-screen hidden md:flex transition-all duration-300 ease-in-out z-40 relative shadow-xl border-border/50",
        isActuallyCollapsed ? "w-20" : "w-64"
      )}
    >
      <SidebarContent forcedCollapsed={isActuallyCollapsed} />
    </nav>
  );
}
