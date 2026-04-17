'use client';

import React, { Suspense } from 'react';
import { useSearchParams } from 'next/navigation';
import { POSMobileView } from '@/components/pos/POSMobileView';

function POSMobilePageContent() {
  const searchParams = useSearchParams();
  const waiterId = searchParams.get('waiterId');
  const isTerminal = searchParams.get('terminal') === 'true';
  
  return (
    <POSMobileView 
      activeWaitressId={waiterId ? parseInt(waiterId) : undefined} 
      isTerminalMode={isTerminal} 
    />
  );
}

export default function POSMobilePage() {
  return (
    <Suspense fallback={<div>Loading...</div>}>
      <POSMobilePageContent />
    </Suspense>
  );
}
