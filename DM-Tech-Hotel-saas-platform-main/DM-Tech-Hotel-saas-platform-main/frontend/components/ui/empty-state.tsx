'use client';

import React from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { LucideIcon } from 'lucide-react';

interface EmptyStateProps {
  icon: LucideIcon;
  title: string;
  description: string;
  actionLabel?: string;
  onAction?: () => void;
  actionHref?: string;
  className?: string;
}

/**
 * A re-usable "empty state" component.
 * Shows when a list/table has no data and always presents
 * the user with a clear call-to-action button so functionality
 * is never hidden behind empty results.
 */
export function EmptyState({
  icon: Icon,
  title,
  description,
  actionLabel,
  onAction,
  actionHref,
  className,
}: EmptyStateProps) {
  return (
    <div
      className={cn(
        'flex flex-col items-center justify-center py-16 px-4 text-center',
        'border border-dashed border-muted/40 rounded-xl bg-muted/5',
        'animate-in fade-in duration-300',
        className,
      )}
    >
      <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-primary/10 ring-8 ring-primary/5">
        <Icon className="h-8 w-8 text-primary" />
      </div>

      <h3 className="mb-1 text-lg font-semibold tracking-tight">{title}</h3>
      <p className="mb-6 max-w-xs text-sm text-muted-foreground">{description}</p>

      {actionLabel && (
        <Button
          onClick={onAction}
          asChild={!!actionHref}
          size="sm"
          className="gap-2 shadow-lg shadow-primary/20 hover:shadow-primary/30 transition-all"
        >
          {actionHref ? (
            <a href={actionHref}>{actionLabel}</a>
          ) : (
            <span>{actionLabel}</span>
          )}
        </Button>
      )}
    </div>
  );
}
