'use client';

import * as React from 'react';
import { useRouter, useParams, usePathname } from 'next/navigation';
import { useAuth } from '@/context/AuthProvider';
import {
  CalendarIcon,
  CreditCardIcon,
  HomeIcon,
  LinkIcon,
  SettingsIcon,
  TagIcon,
  UserIcon,
} from 'lucide-react';
import {
  CommandDialog,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
  CommandSeparator,
} from '@/components/ui/command';

export function CommandPalette({
  open,
  onOpenChange,
}: {
  open: boolean;
  onOpenChange: (open: boolean) => void;
}) {
  const router = useRouter();
  const params = useParams();
  const pathname = usePathname();
  const { user } = useAuth();
  const branchSlug = params?.slug as string;
  const isBranchView = pathname?.startsWith('/branch/') || pathname?.startsWith('/reception/');

  const roleSlugs = user?.roles?.map(r => r.slug.toLowerCase()) || [];
  const isReceptionist = roleSlugs.includes('receptionist');

  const getPath = (path: string) => {
    if (isReceptionist && user?.hotel_slug) {
        return `/reception/${user.hotel_slug}${path}`;
    }
    if (isBranchView && branchSlug) {
      return `/branch/${branchSlug}${path}`;
    }
    return path;
  };

  React.useEffect(() => {
    const down = (e: KeyboardEvent) => {
      if (e.key === 'k' && (e.metaKey || e.ctrlKey)) {
        e.preventDefault();
        onOpenChange(true);
      }
    };
    document.addEventListener('keydown', down);
    return () => document.removeEventListener('keydown', down);
  }, [onOpenChange]);

  const runCommand = React.useCallback(
    (command: () => void) => {
      onOpenChange(false);
      command();
    },
    [onOpenChange]
  );

  return (
    <CommandDialog open={open} onOpenChange={onOpenChange}>
      <CommandInput placeholder="Type a command or search..." />
      <CommandList>
        <CommandEmpty>No results found.</CommandEmpty>
        <CommandGroup heading="Quick Actions">
          <CommandItem onSelect={() => runCommand(() => router.push(getPath('/reservations')))}>
            <CalendarIcon className="mr-2 h-4 w-4" />
            <span>Create Reservation</span>
          </CommandItem>
          <CommandItem onSelect={() => runCommand(() => router.push(getPath('/guest-portal')))}>
            <UserIcon className="mr-2 h-4 w-4" />
            <span>Search Guest</span>
          </CommandItem>
        </CommandGroup>
        
        {!isReceptionist && (
          <>
            <CommandSeparator />
            <CommandGroup heading="Navigation">
              <CommandItem onSelect={() => runCommand(() => router.push(getPath('/dashboard')))}>
                <HomeIcon className="mr-2 h-4 w-4" />
                <span>Dashboard</span>
              </CommandItem>
              <CommandItem onSelect={() => runCommand(() => router.push(getPath('/rooms')))}>
                <HomeIcon className="mr-2 h-4 w-4" />
                <span>Rooms</span>
              </CommandItem>
              {isBranchView && (
                <CommandItem onSelect={() => runCommand(() => router.push(getPath('/outlets')))}>
                  <HomeIcon className="mr-2 h-4 w-4" />
                  <span>Outlets</span>
                </CommandItem>
              )}
              <CommandItem onSelect={() => runCommand(() => router.push(getPath('/pricing')))}>
                <TagIcon className="mr-2 h-4 w-4" />
                <span>Pricing</span>
              </CommandItem>
              <CommandItem onSelect={() => runCommand(() => router.push(getPath('/payments')))}>
                <CreditCardIcon className="mr-2 h-4 w-4" />
                <span>Transactions</span>
              </CommandItem>
              {!isBranchView && (
                <CommandItem onSelect={() => runCommand(() => router.push('/ota-channels'))}>
                  <LinkIcon className="mr-2 h-4 w-4" />
                  <span>OTA Channels</span>
                </CommandItem>
              )}
            </CommandGroup>
            <CommandSeparator />
            <CommandGroup heading="Settings">
              <CommandItem onSelect={() => runCommand(() => router.push(getPath('/settings')))}>
                <SettingsIcon className="mr-2 h-4 w-4" />
                <span>Branding Configuration</span>
              </CommandItem>
            </CommandGroup>
          </>
        )}
      </CommandList>
    </CommandDialog>
  );
}
