'use client';

import React from 'react';
import { Card, CardContent, CardFooter, CardHeader } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { CheckCircle2, Clock, Printer, CreditCard, Landmark } from 'lucide-react';
import { cn } from '@/lib/utils';
import { format } from 'date-fns';

interface SmartReceiptProps {
  order: any; // Order with outlet, hotel, and group details
  payment?: any; // Payment info if available (e.g. virtual account)
}

export const SmartReceipt: React.FC<SmartReceiptProps> = ({ order, payment }) => {
  const [timeLeft, setTimeLeft] = React.useState(1800); // 30 minutes in seconds

  React.useEffect(() => {
    if (payment?.gateway === 'monnify' || payment?.gateway === 'paystack' || payment?.virtual_account_number) {
      const timer = setInterval(() => {
        setTimeLeft(prev => (prev > 0 ? prev - 1 : 0));
      }, 1000);
      return () => clearInterval(timer);
    }
  }, [payment]);

  const formatTime = (seconds: number) => {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}:${secs.toString().padStart(2, '0')}`;
  };

  // Heirarchical Finance Logic: Outlet > Branch > Group Central
  const finance = {
    bank: order.outlet?.bank_name || order.hotel?.bank_name || order.group?.bank_name || 'N/A',
    accountNumber: order.outlet?.account_number || order.hotel?.account_number || order.group?.account_number || 'N/A',
    accountName: order.outlet?.account_name || order.hotel?.account_name || order.group?.account_name || 'N/A',
  };

  const isGateway = !!payment?.virtual_account_number;

  const handlePrint = () => {
    window.print();
  };

  return (
    <div className="max-w-md mx-auto p-4 space-y-6 print:p-0 print:m-0 print:max-w-none">
      <style jsx global>{`
        @media print {
          body * { visibility: hidden; }
          .receipt-container, .receipt-container * { visibility: visible; }
          .receipt-container { 
            position: absolute; 
            left: 0; 
            top: 0; 
            width: 80mm !important; 
            padding: 5mm !important;
            font-family: 'Courier New', Courier, monospace;
            background: white !important;
            border: none !important;
            box-shadow: none !important;
          }
          .no-print { display: none !important; }
        }
      `}</style>

      <Card className="receipt-container border-none shadow-none bg-muted/20 print:bg-white">
        <CardHeader className="text-center pb-2">
          <div className="space-y-1">
            <h1 className="text-xl font-black uppercase tracking-tighter">{order.hotel?.name}</h1>
            <p className="text-xs text-muted-foreground">{order.hotel?.address}</p>
            <Badge variant="outline" className="mt-2 font-bold uppercase tracking-widest bg-primary text-primary-foreground print:bg-white print:text-black print:border-black">
              {order.outlet?.name}
            </Badge>
          </div>
        </CardHeader>

        <CardContent className="space-y-6 pt-4 text-sm">
          {/* Operational Context */}
          <div className="grid grid-cols-2 gap-y-1 text-xs border-y py-3 border-dashed">
            <span className="text-muted-foreground">Order ID:</span>
            <span className="text-right font-mono">#{order.order_number}</span>
            <span className="text-muted-foreground">Table/Room:</span>
            <span className="text-right font-bold">{order.table_number || order.room?.number || 'N/A'}</span>
            <span className="text-muted-foreground">Waitress:</span>
            <span className="text-right">{order.waiter?.name || 'N/A'}</span>
            <span className="text-muted-foreground">Date:</span>
            <span className="text-right">{format(new Date(order.created_at), 'dd MMM yyyy HH:mm')}</span>
          </div>

          {/* Payment Section */}
          <div className="bg-white p-4 rounded-xl shadow-sm border space-y-4 print:border-none print:shadow-none print:p-0">
            {isGateway ? (
              <div className="space-y-3">
                <div className="flex items-center justify-between">
                   <div className="flex items-center gap-2 text-primary font-bold print:text-black">
                     <CreditCard className="h-4 w-4" />
                     PAY TO:
                   </div>
                   <Badge variant="destructive" className="animate-pulse gap-1 print:hidden">
                     <Clock className="h-3 w-3" />
                     {formatTime(timeLeft)}
                   </Badge>
                </div>
                <div className="text-2xl font-black tracking-widest text-center py-2 border-2 border-black rounded-lg">
                  {payment.virtual_account_number}
                </div>
                <p className="text-[10px] text-center text-muted-foreground uppercase font-bold">
                  CONFIRM NAME: <span className="text-foreground font-black">{payment.virtual_account_name || order.hotel?.name}</span>
                </p>
                {timeLeft === 0 && (
                  <p className="text-[10px] text-center text-destructive font-black uppercase">EXPIRED - PLEASE REGENERATE</p>
                )}
              </div>
            ) : (
              <div className="space-y-3">
                <div className="flex items-center gap-2 text-primary font-bold print:text-black">
                   <Landmark className="h-4 w-4" />
                   TRANSFER TO:
                </div>
                <div className="space-y-1">
                  <p className="text-xs uppercase text-muted-foreground font-bold">{finance.bank}</p>
                  <p className="text-3xl font-black tracking-widest border-b-2 border-black pb-1">
                    {finance.accountNumber}
                  </p>
                </div>
                <div className="pt-1">
                  <p className="text-[10px] text-muted-foreground uppercase font-black tracking-widest">
                    VERIFICATION NAME:
                  </p>
                  <p className="text-base font-black uppercase">{finance.accountName}</p>
                </div>
              </div>
            )}
          </div>

          {/* Itemized (Simplified) */}
          <div className="space-y-2">
            {order.items?.map((item: any) => (
              <div key={item.id} className="flex justify-between text-xs">
                <span>{item.quantity}x {item.menu_item?.name}</span>
                <span>{order.currency || '₦'}{item.subtotal ? item.subtotal.toLocaleString(undefined, { minimumFractionDigits: 2 }) : '0.00'}</span>
              </div>
            ))}
            <div className="border-t pt-2 border-dashed flex justify-between font-black text-lg">
              <span>TOTAL</span>
              <span>{order.currency || '₦'}{order.total_amount ? order.total_amount.toLocaleString(undefined, { minimumFractionDigits: 2 }) : '0.00'}</span>
            </div>
          </div>
        </CardContent>

        <CardFooter className="flex-col gap-4 text-center pb-8 pt-4">
           <div className="space-y-1 italic text-muted-foreground text-[10px] print:text-black">
             <p>"Service with a smile. We hope to see you again soon!"</p>
             <p className="font-bold">Thank you for choosing {order.hotel?.name}!</p>
           </div>
           
           <div className="pt-4 border-t border-dashed w-full flex items-center justify-center opacity-30 print:opacity-100">
             <span className="text-[8px] uppercase tracking-[0.2em] font-bold">Powered by DM-Tech OS</span>
           </div>
           
           <Button onClick={handlePrint} className="w-full no-print gap-2 bg-primary hover:bg-primary/90">
             <Printer className="h-4 w-4" />
             Print Receipt (80mm)
           </Button>
        </CardFooter>
      </Card>
    </div>
  );
};
