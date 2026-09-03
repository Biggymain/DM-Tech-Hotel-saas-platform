'use client';

import * as React from 'react';
import { Header } from '@/components/layout/header';

export default function ReceptionistLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <div className="flex min-h-screen bg-background text-foreground">
      <div className="flex flex-col flex-1 overflow-hidden">
        <Header showSidebarToggle={false} />
        <main className="flex-1 overflow-y-auto p-4 md:p-6 lg:p-8">
            <div className="max-w-[1600px] mx-auto">
                {children}
            </div>
        </main>
      </div>
    </div>
  );
}
