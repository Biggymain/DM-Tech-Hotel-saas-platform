'use client';

import * as React from 'react';
import { Bell, Search, Moon, Sun, Menu, Power, Clock } from 'lucide-react';
import { useTheme } from 'next-themes';
import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuGroup,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Sheet, SheetContent, SheetTrigger, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { useAuth } from '@/context/AuthProvider';
import { useUI } from '@/context/UIContext';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { CommandPalette } from '@/components/command-palette';
import { SidebarContent } from './sidebar-content';
import { Badge } from '@/components/ui/badge';
import { toast } from 'sonner';

interface HeaderProps {
  showSidebarToggle?: boolean;
}

export function Header({ showSidebarToggle = true }: HeaderProps) {
  const { setTheme, theme } = useTheme();
  const { toggleSidebar } = useUI();
  const authContext = useAuth();
  const user = authContext?.user;
  const logout = authContext?.logout || (() => {});
  const [commandPaletteOpen, setCommandPaletteOpen] = React.useState(false);
  const [mobileMenuOpen, setMobileMenuOpen] = React.useState(false);

  return (
    <>
      <header className="flex flex-shrink-0 items-center justify-between px-6 py-3 border-b bg-card">
        <div className="flex items-center gap-4">
          {showSidebarToggle && (
            <>
              <Sheet open={mobileMenuOpen} onOpenChange={setMobileMenuOpen}>
                <SheetTrigger render={
                  <Button variant="ghost" size="icon" className="md:hidden">
                    <Menu className="h-5 w-5" />
                  </Button>
                } />
                <SheetContent side="left" className="p-0 w-64">
                    <SheetHeader className="sr-only">
                        <SheetTitle>Navigation Menu</SheetTitle>
                    </SheetHeader>
                    <SidebarContent onItemClick={() => setMobileMenuOpen(false)} />
                </SheetContent>
              </Sheet>
              <Button 
                variant="ghost" 
                size="icon" 
                className="hidden md:flex" 
                onClick={toggleSidebar}
              >
                <Menu className="h-5 w-5" />
              </Button>
            </>
          )}
          {showSidebarToggle && (
            <button 
              onClick={() => setCommandPaletteOpen(true)}
              className="hidden sm:flex items-center px-3 py-1.5 rounded-md bg-muted text-muted-foreground text-sm hover:bg-muted/80 transition-colors"
            >
              <Search className="h-4 w-4 mr-2" />
              <span className="opacity-70 mr-4">Search...</span>
              <kbd className="mx-1 flex h-5 items-center gap-1 rounded border bg-background px-1.5 font-mono text-[10px] font-medium opacity-100">
                <span className="text-xs">⌘</span>K
              </kbd>
            </button>
          )}
        </div>
        <div className="flex items-center gap-3">
          {user && (
            <div className="flex items-center gap-3 pr-4 border-r">
              <Badge 
                variant={user.is_on_duty ? "default" : "secondary"}
                className={cn(
                  "gap-1.5 px-3 py-1 text-[10px] font-bold uppercase tracking-widest",
                  user.is_on_duty ? "bg-emerald-500 hover:bg-emerald-600" : "bg-muted text-muted-foreground"
                )}
              >
                <div className={cn("h-1.5 w-1.5 rounded-full", user.is_on_duty ? "bg-white animate-pulse" : "bg-muted-foreground")} />
                {user.is_on_duty ? "On Duty" : "Off Duty"}
              </Badge>
              
              <Button 
                variant="ghost" 
                size="sm" 
                className={cn(
                  "h-8 gap-2 rounded-full px-4 text-xs font-semibold transition-all",
                  user.is_on_duty 
                    ? "text-red-500 hover:bg-red-50 text-red-600 hover:text-red-700 dark:hover:bg-red-950/30" 
                    : "text-emerald-500 hover:bg-emerald-50 text-emerald-600 hover:text-emerald-700 dark:hover:bg-emerald-950/30"
                )}
                onClick={async () => {
                  await authContext.toggleDuty();
                  toast.success(user.is_on_duty ? "Shift ended" : "Shift started");
                }}
              >
                <Power className="h-3.5 w-3.5" />
                {user.is_on_duty ? "Clock Out" : "Clock In"}
              </Button>
            </div>
          )}

          {typeof window !== 'undefined' && localStorage.getItem('hotel_context') && (
            <Button 
              variant="outline" 
              size="sm" 
              className="hidden lg:flex border-primary/30 text-primary hover:bg-primary/5"
              onClick={() => {
                localStorage.removeItem('hotel_context');
                window.location.href = '/organization';
              }}
            >
              Switch to Group View
            </Button>
          )}
          <Button
            variant="ghost"
            size="icon"
            onClick={() => setTheme(theme === 'light' ? 'dark' : 'light')}
          >
            <Sun className="h-5 w-5 rotate-0 scale-100 transition-all dark:-rotate-90 dark:scale-0" />
            <Moon className="absolute h-5 w-5 rotate-90 scale-0 transition-all dark:rotate-0 dark:scale-100" />
            <span className="sr-only">Toggle theme</span>
          </Button>

          <Button variant="ghost" size="icon" className="relative">
            <Bell className="h-5 w-5" />
            <span className="absolute top-1 right-2 w-2 h-2 bg-red-500 rounded-full"></span>
          </Button>

          <DropdownMenu>
            <DropdownMenuTrigger
              render={
                <button type="button" className="rounded-full ring-offset-2 focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:outline-none">
                  <Avatar className="h-8 w-8 hover:opacity-80 transition-opacity cursor-pointer relative rounded-full">
                    <AvatarFallback>{user?.name?.charAt(0) || 'U'}</AvatarFallback>
                  </Avatar>
                </button>
              }
            />
            <DropdownMenuContent className="w-56" align="end">
              <DropdownMenuGroup>
                <DropdownMenuLabel className="font-normal">
                  <div className="flex flex-col space-y-1">
                    <p className="text-sm font-medium leading-none">{user?.name}</p>
                    <p className="text-xs leading-none text-muted-foreground">
                      {user?.email}
                    </p>
                  </div>
                </DropdownMenuLabel>
              </DropdownMenuGroup>
              <DropdownMenuSeparator />
              <DropdownMenuItem onClick={logout}>
                Log out
              </DropdownMenuItem>
            </DropdownMenuContent>
          </DropdownMenu>
        </div>
      </header>
      <CommandPalette open={commandPaletteOpen} onOpenChange={setCommandPaletteOpen} />
    </>
  );
}
