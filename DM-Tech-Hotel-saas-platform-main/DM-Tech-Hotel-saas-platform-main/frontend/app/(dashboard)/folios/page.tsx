'use client';

import React from 'react';
import FolioLedger from '@/components/pms/FolioLedger';

export default function FoliosPage() {
  // Mock data for initial preview
  const mockItems = [
    {
      id: '1',
      description: 'Room Charge - Standard Room (101)',
      amount: 45000,
      is_charge: true,
      source: 'ROOM' as const,
      status: 'PAID' as const,
      created_at: new Date(Date.now() - 86400000).toISOString()
    },
    {
      id: '2',
      description: 'Room Service - Breakfast',
      amount: 5500,
      is_charge: true,
      source: 'ROOM' as const,
      status: 'PAID' as const,
      created_at: new Date(Date.now() - 43200000).toISOString()
    },
    {
      id: '3',
      description: 'Advance Deposit',
      amount: 50000,
      is_charge: false,
      source: 'PAYMENT' as const,
      status: 'PAID' as const,
      created_at: new Date(Date.now() - 172800000).toISOString()
    }
  ];

  return (
    <div className="p-8 max-w-7xl mx-auto">
      <div className="mb-8">
        <h1 className="text-3xl font-bold tracking-tight">Folio Management</h1>
        <p className="text-muted-foreground mt-2">Manage guest financial ledgers and post charges/payments.</p>
      </div>
      
      <FolioLedger 
        reservationNumber="RES-2026-001" 
        guestName="Michael Scott" 
        initialItems={mockItems}
      />
    </div>
  );
}
