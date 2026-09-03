'use client';

import React, { useState, useMemo } from 'react';
import { Card, CardHeader, CardTitle, CardContent } from "@/components/ui/card";
import { Table, TableHeader, TableRow, TableHead, TableBody, TableCell } from "@/components/ui/table";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { 
  Plus, 
  Receipt, 
  Utensils, 
  Bed, 
  Waves, 
  AlertCircle, 
  CheckCircle2,
  Download,
  Printer
} from "lucide-react";
import { cn } from "@/lib/utils";

interface FolioItem {
  id: string;
  description: string;
  amount: number;
  is_charge: boolean;
  source: 'ROOM' | 'POS' | 'LAUNDRY' | 'PAYMENT';
  status: 'PENDING' | 'PAID';
  created_at: string;
}

interface FolioLedgerProps {
  reservationNumber: string;
  guestName: string;
  initialItems?: FolioItem[];
}

const FolioLedger: React.FC<FolioLedgerProps> = ({ 
  reservationNumber, 
  guestName, 
  initialItems = [] 
}) => {
  const [items, setItems] = useState<FolioItem[]>(initialItems);
  const [isPosting, setIsPosting] = useState(false);

  const stats = useMemo(() => {
    const charges = items.filter(i => i.is_charge).reduce((acc, curr) => acc + curr.amount, 0);
    const payments = items.filter(i => !i.is_charge).reduce((acc, curr) => acc + curr.amount, 0);
    return {
      totalCharges: charges,
      totalPayments: payments,
      balance: charges - payments
    };
  }, [items]);

  const getSourceIcon = (source: string) => {
    switch (source) {
      case 'ROOM': return <Bed className="w-4 h-4 text-blue-400" />;
      case 'POS': return <Utensils className="w-4 h-4 text-orange-400" />;
      case 'LAUNDRY': return <Waves className="w-4 h-4 text-cyan-400" />;
      case 'PAYMENT': return <Receipt className="w-4 h-4 text-green-400" />;
      default: return <Receipt className="w-4 h-4 text-gray-400" />;
    }
  };

  const postMockCharge = () => {
    const newItem: FolioItem = {
      id: Math.random().toString(36).substr(2, 9),
      description: "POS: Restaurant Order #442",
      amount: 4500,
      is_charge: true,
      source: 'POS',
      status: 'PAID',
      created_at: new Date().toISOString()
    };
    setItems([...items, newItem]);
  };

  return (
    <div className="space-y-6">
      <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
        <Card className="bg-background/40 backdrop-blur-xl border-muted/20">
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium text-muted-foreground uppercase tracking-wider">Total Charges</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-orange-400">₦{stats.totalCharges.toLocaleString()}</div>
          </CardContent>
        </Card>
        <Card className="bg-background/40 backdrop-blur-xl border-muted/20">
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium text-muted-foreground uppercase tracking-wider">Total Payments</CardTitle>
          </CardHeader>
          <CardContent>
            <div className="text-2xl font-bold text-green-400">₦{stats.totalPayments.toLocaleString()}</div>
          </CardContent>
        </Card>
        <Card className="bg-background/40 backdrop-blur-xl border-muted/20 ring-1 ring-primary/20">
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium text-muted-foreground uppercase tracking-wider">Balance Due</CardTitle>
          </CardHeader>
          <CardContent>
            <div className={cn(
              "text-2xl font-bold",
              stats.balance > 0 ? "text-red-400" : "text-green-400"
            )}>
              ₦{stats.balance.toLocaleString()}
            </div>
          </CardContent>
        </Card>
      </div>

      <Card className="bg-background/40 backdrop-blur-xl border-muted/20">
        <CardHeader className="flex flex-row items-center justify-between">
          <div>
            <CardTitle className="text-xl">Financial Ledger</CardTitle>
            <p className="text-sm text-muted-foreground">Guest: {guestName} | Res: #{reservationNumber}</p>
          </div>
          <div className="flex gap-2">
            <Button variant="outline" size="sm" className="gap-2">
              <Printer className="w-4 h-4" /> Print Statement
            </Button>
            <Button size="sm" className="gap-2" onClick={postMockCharge}>
              <Plus className="w-4 h-4" /> Post Charge
            </Button>
          </div>
        </CardHeader>
        <CardContent>
          <Table>
            <TableHeader>
              <TableRow className="border-muted/20">
                <TableHead>Date</TableHead>
                <TableHead>Description</TableHead>
                <TableHead>Source</TableHead>
                <TableHead>Status</TableHead>
                <TableHead className="text-right">Charge</TableHead>
                <TableHead className="text-right">Payment</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {items.length === 0 ? (
                <TableRow>
                  <TableCell colSpan={6} className="text-center py-10 text-muted-foreground">
                    No transactions found for this folio.
                  </TableCell>
                </TableRow>
              ) : (
                items.map((item) => (
                  <TableRow key={item.id} className="border-muted/20 hover:bg-muted/5 transition-colors">
                    <TableCell className="text-xs font-mono">
                      {new Date(item.created_at).toLocaleDateString()}
                    </TableCell>
                    <TableCell className="font-medium text-sm">
                      {item.description}
                    </TableCell>
                    <TableCell>
                      <div className="flex items-center gap-2 text-xs">
                        {getSourceIcon(item.source)}
                        <span className="capitalize">{item.source.toLowerCase()}</span>
                      </div>
                    </TableCell>
                    <TableCell>
                      <Badge variant={item.status === 'PAID' ? 'default' : 'secondary'} className="text-[10px] px-2 py-0">
                        {item.status}
                      </Badge>
                    </TableCell>
                    <TableCell className="text-right text-sm">
                      {item.is_charge ? `₦${item.amount.toLocaleString()}` : '-'}
                    </TableCell>
                    <TableCell className="text-right text-sm text-green-400">
                      {!item.is_charge ? `₦${item.amount.toLocaleString()}` : '-'}
                    </TableCell>
                  </TableRow>
                ))
              )}
            </TableBody>
          </Table>
        </CardContent>
      </Card>
    </div>
  );
};

export default FolioLedger;
