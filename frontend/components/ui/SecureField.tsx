'use client';

import * as React from 'react';
import { ShieldCheck, Lock, EyeOff } from 'lucide-react';
import { cn } from '@/lib/utils';
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from "@/components/ui/tooltip";

interface SecureFieldProps {
  value: string | null | undefined;
  label?: string;
  className?: string;
  fallback?: string;
}

/**
 * Handles "Zero-Knowledge" field display.
 * If the value is a hex blob or encrypted string, it displays a premium 
 * "Securely Encrypted" placeholder until decrypted by a valid passphrase.
 */
export function SecureField({ value, label, className, fallback = 'N/A' }: SecureFieldProps) {
  // Simple heuristic: if it's long and looks like hex, it's probably encrypted
  const isEncrypted = React.useMemo(() => {
    if (!value) return false;
    // Check if it's a hex string longer than 32 chars (common for hashes/ciphertexts)
    const hexRegex = /^[0-9a-fA-F]{32,}$/;
    // Also check for common "encrypted" markers if any
    return hexRegex.test(value) || value.startsWith('base64:') || value.length > 128;
  }, [value]);

  if (!value) return <span className="text-muted-foreground italic text-xs">{fallback}</span>;

  if (isEncrypted) {
    return (
      <TooltipProvider>
        <Tooltip>
          <TooltipTrigger className={cn(
            "inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md bg-blue-500/10 border border-blue-500/20 text-blue-500 cursor-help transition-all hover:bg-blue-500/20",
            className
          )}>
            <Lock className="w-3 h-3" />
            <span className="text-[10px] font-bold uppercase tracking-widest">Securely Encrypted</span>
          </TooltipTrigger>
          <TooltipContent className="bg-black border-white/10 text-[10px] text-gray-400 max-w-[200px]">
             This field is protected by Zero-Knowledge Binary PGP. Decryption requires a valid platform passphrase.
          </TooltipContent>
        </Tooltip>
      </TooltipProvider>
    );
  }

  return (
    <div className={cn("flex items-center gap-2 group", className)}>
      <span className="text-sm font-medium">{value}</span>
      <ShieldCheck className="w-3.5 h-3.5 text-emerald-500 opacity-0 group-hover:opacity-100 transition-opacity" />
    </div>
  );
}
