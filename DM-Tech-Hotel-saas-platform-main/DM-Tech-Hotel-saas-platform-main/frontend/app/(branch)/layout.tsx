// Wrapper layout for branch-specific management (Isolated from Group)
import * as React from 'react';
import { BranchSidebar } from '@/components/layout/branch-sidebar';
import { Header } from '@/components/layout/header';

export default function BranchLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  return (
    <div className="flex min-h-screen bg-background text-foreground">
      <BranchSidebar />
      <div className="flex flex-col flex-1 overflow-hidden">
        <Header />
        <main className="flex-1 overflow-y-auto p-4 md:p-6 lg:p-8">
          {children}
        </main>
      </div>
    </div>
  );
}
