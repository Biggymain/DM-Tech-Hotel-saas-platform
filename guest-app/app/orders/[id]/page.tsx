'use client';

import * as React from 'react';
import { useParams, useRouter } from 'next/navigation';
import { motion } from 'framer-motion';
import { 
  ArrowLeft, 
  ReceiptText, 
  MapPin, 
  ChefHat, 
  RefreshCcw,
  Loader2
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import OrderTracker from '@/components/order-tracker';
import { toast } from 'sonner';
import api from '@/lib/api';

export default function OrderTrackingPage() {
  const { id } = useParams();
  const router = useRouter();
  const [order, setOrder] = React.useState<any>(null);
  const [isLoading, setIsLoading] = React.useState(true);
  const [isRefreshing, setIsRefreshing] = React.useState(false);

  const fetchOrder = async (silent = false) => {
    if (!silent) setIsLoading(true);
    else setIsRefreshing(true);
    
    try {
      const { data } = await api.get(`/api/v1/guest/orders/${id}`);
      setOrder(data);
    } catch (error) {
      toast.error('Failed to load order details');
    } finally {
      setIsLoading(false);
      setIsRefreshing(false);
    }
  };

  React.useEffect(() => {
    fetchOrder();
    
    // Polling every 15 seconds for status updates
    const interval = setInterval(() => fetchOrder(true), 15000);
    return () => clearInterval(interval);
  }, [id]);

  if (isLoading) {
    return (
      <div className="flex flex-col items-center justify-center min-h-[50vh] space-y-4">
        <Loader2 className="animate-spin text-indigo-500" size={32} />
        <p className="text-slate-400">Loading order tracker...</p>
      </div>
    );
  }

  if (!order) {
    return (
      <div className="text-center space-y-4 py-12">
        <p className="text-slate-400">Order not found.</p>
        <Button onClick={() => router.push('/')} variant="outline" className="border-white/10 text-white">
          Back to Home
        </Button>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <header className="flex items-center justify-between">
        <div className="flex items-center space-x-4">
          <Button 
            variant="ghost" 
            size="icon" 
            onClick={() => router.back()}
            className="rounded-full bg-white/5 border border-white/10"
          >
            <ArrowLeft size={18} />
          </Button>
          <div>
            <h1 className="text-xl font-bold text-white">Order Details</h1>
            <p className="text-xs text-slate-400">#{order.order_number}</p>
          </div>
        </div>
        <Button 
          variant="ghost" 
          size="icon" 
          onClick={() => fetchOrder(true)}
          className={cn("rounded-full bg-white/5 border border-white/10", isRefreshing && "animate-spin")}
        >
          <RefreshCcw size={16} />
        </Button>
      </header>

      <Card className="bg-white/5 border-white/10 backdrop-blur-xl">
        <CardContent className="p-6">
          <OrderTracker status={order.status} updatedAt={order.updated_at} />
        </CardContent>
      </Card>

      <div className="grid gap-4">
        <Card className="bg-white/5 border-white/10">
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium flex items-center">
              <ReceiptText className="mr-2 text-indigo-400" size={16} />
              Summary
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            {order.items?.map((item: any) => (
              <div key={item.id} className="flex justify-between text-sm">
                <span className="text-slate-300">{item.quantity}x {item.menu_item?.name || 'Item'}</span>
                <span className="text-white font-medium">${parseFloat(item.price).toFixed(2)}</span>
              </div>
            ))}
            <div className="pt-2 border-t border-white/5 flex justify-between font-bold text-white">
              <span>Total Amount</span>
              <span className="text-indigo-400">${parseFloat(order.total_amount).toFixed(2)}</span>
            </div>
          </CardContent>
        </Card>

        <Card className="bg-white/5 border-white/10">
          <CardHeader className="pb-2">
            <CardTitle className="text-sm font-medium flex items-center">
              <MapPin className="mr-2 text-indigo-400" size={16} />
              Delivery Details
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-1">
            <p className="text-sm text-slate-300">Room {order.room_id}</p>
            <p className="text-xs text-slate-500">Scheduled for Immediate Delivery</p>
          </CardContent>
        </Card>
      </div>

      <div className="pt-4">
        <Button className="w-full h-12 bg-white/5 border border-white/10 hover:bg-white/10 text-white" variant="ghost">
          Need help with this order?
        </Button>
      </div>
    </div>
  );
}

function cn(...classes: any[]) {
  return classes.filter(Boolean).join(' ');
}
