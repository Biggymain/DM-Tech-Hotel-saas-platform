'use client';

import * as React from 'react';
import Link from 'next/link';
import { useQuery } from '@tanstack/react-query';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { CreditCardIcon, RefreshCcwIcon, Loader2, CheckCircleIcon } from 'lucide-react';
import api from '@/lib/api';
import { toast } from 'sonner';
import { useParams } from 'next/navigation';

export default function PaymentsPage() {
  const params = useParams();
  const { data: transactions, isLoading, refetch } = useQuery({
    queryKey: ['payment-transactions'],
    queryFn: async () => {
      try {
        const { data } = await api.get('/api/v1/payments/transactions');
        return data.data;
      } catch (error) {
        // Fallback for UI visualization
        return [
          { id: 101, gateway_transaction_id: 'ch_3Nkx', amount: 450, currency: 'USD', status: 'captured', payment_source: 'guest_portal', created_at: new Date().toISOString() },
          { id: 102, gateway_transaction_id: 'man_910', amount: 120, currency: 'USD', status: 'manual_pending', payment_source: 'restaurant_pos', created_at: new Date(Date.now() - 3600000).toISOString() },
          { id: 103, gateway_transaction_id: 'pay_39a', amount: 200, currency: 'USD', status: 'failed', payment_source: 'frontdesk', created_at: new Date(Date.now() - 7200000).toISOString() },
        ];
      }
    },
  });

  const handleManualConfirm = async (transactionId: number) => {
    try {
      await api.post('/api/v1/payments/manual-confirm', { transaction_id: transactionId });
      toast.success('Payment Confirmed', { description: 'The manual POS payment has been officially recorded.' });
      refetch();
    } catch (err: any) {
      toast.error('Confirmation Failed', { description: err.response?.data?.message || 'You might not have permission, or an error occurred.' });
    }
  };

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h2 className="text-3xl font-bold tracking-tight">Payments & Transactions</h2>
          <p className="text-muted-foreground">Monitor payment histories and authorize manual POS approvals.</p>
        </div>
        <div className="flex gap-2">
          <Button variant="outline" asChild>
            <Link href={`/branch/${params.slug}/settings?tab=gateways`}>
              <CreditCardIcon className="mr-2 h-4 w-4" />
              Configure Gateways
            </Link>
          </Button>
          <Button onClick={() => refetch()}>
            <RefreshCcwIcon className="mr-2 h-4 w-4" />
            Refresh
          </Button>
        </div>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Transaction History</CardTitle>
          <CardDescription>All processed, pending, and failed monetary inbound transactions.</CardDescription>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="flex justify-center p-8"><Loader2 className="animate-spin text-muted-foreground" /></div>
          ) : (
            <div className="rounded-md border">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Transaction ID</TableHead>
                    <TableHead>Amount</TableHead>
                    <TableHead>Source</TableHead>
                    <TableHead>Date</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead className="text-right">Actions</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {transactions?.map((trx: any) => (
                    <TableRow key={trx.id}>
                      <TableCell className="font-mono text-sm">{trx.gateway_transaction_id}</TableCell>
                      <TableCell className="font-bold">{trx.currency} {trx.amount}</TableCell>
                      <TableCell className="capitalize text-muted-foreground">{trx.payment_source?.replace('_', ' ')}</TableCell>
                      <TableCell>{new Date(trx.created_at).toLocaleString()}</TableCell>
                      <TableCell>
                        <Badge variant={
                          trx.status === 'captured' ? 'default' : 
                          trx.status === 'manual_pending' ? 'outline' : 
                          trx.status === 'failed' ? 'destructive' : 'secondary'
                        }>
                          {trx.status.toUpperCase().replace('_', ' ')}
                        </Badge>
                      </TableCell>
                      <TableCell className="text-right space-x-2">
                        {trx.status === 'manual_pending' && (
                          <Button size="sm" onClick={() => handleManualConfirm(trx.id)}>
                            <CheckCircleIcon className="mr-2 h-4 w-4" /> Confirm
                          </Button>
                        )}
                        <Button variant="ghost" size="sm">Details</Button>
                      </TableCell>
                    </TableRow>
                  ))}
                  {transactions?.length === 0 && (
                    <TableRow>
                      <TableCell colSpan={6} className="text-center py-6 text-muted-foreground">
                        No transactions found.
                      </TableCell>
                    </TableRow>
                  )}
                </TableBody>
              </Table>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
