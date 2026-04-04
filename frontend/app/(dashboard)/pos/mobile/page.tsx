'use client';

import React, { useEffect, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { useAuth } from '@/context/AuthProvider';
import api from '@/lib/api';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  ShoppingCart, Plus, Minus, Flame, Loader2, BellRing, CheckCircle2,
  RefreshCw, X, Timer
} from 'lucide-react';
import { toast } from 'sonner';

interface MenuItem {
  id: number;
  name: string;
  price: number;
  category: string;
  station_name: string;
}
interface CartItem extends MenuItem { qty: number; notes: string; }

declare global {
  interface Window { Echo: any; }
}

interface POSMobilePageProps {
  activeWaitressId?: number | null;
  isTerminalMode?: boolean;
}

export default function POSMobilePage({ activeWaitressId, isTerminalMode = false }: POSMobilePageProps) {
  const { user } = useAuth();
  const qc = useQueryClient();

  // Use either the logged-in user or the shifted terminal identity
  const effectiveUserId = activeWaitressId || user?.id;

  const [cart, setCart]         = useState<CartItem[]>([]);
  const [tableNo, setTableNo]   = useState('');
  const [activeCategory, setActiveCategory] = useState('all');
  const [draftOrderId, setDraftOrderId] = useState<number | null>(null);
  const [cartOpen, setCartOpen] = useState(false);
  const [activeTab, setActiveTab] = useState<'menu' | 'status'>('menu');

  // Incoming "order ready" notifications via WebSocket
  const [readyNotifs, setReadyNotifs] = useState<{ orderId: number; tableNo: string }[]>([]);

  // ── Active Orders fetch (for Status tab) ──────────────────────────────────
  const { data: myOrders = [], refetch: refetchOrders } = useQuery({
    queryKey: ['pos-active-orders', effectiveUserId, isTerminalMode],
    queryFn: async () => {
      const url = isTerminalMode && !activeWaitressId 
        ? `/api/v1/orders?outlet_id=${user?.outlet_id}` // Manager Master View
        : `/api/v1/orders?waiter_id=${effectiveUserId}`;
      const { data } = await api.get(url);
      return data.data ?? [];
    },
    enabled: !!effectiveUserId,
    refetchInterval: 15000,
  });

  // ── Waiter's personal notification channel ─────────────────────────────────
  useEffect(() => {
    if (typeof window === 'undefined' || !window.Echo || !effectiveUserId || !user?.hotel_id) return;

    const ch = window.Echo.private(`hotel.${user.hotel_id}.waiter.${effectiveUserId}`)
      .listen('.order.status.updated', (e: any) => {
        if (e.status === 'ready') {
          const id = e.order_id;
          const table = e.table_number ?? `Room ${e.room_id ?? '—'}`;
          setReadyNotifs((n) => [...n, { orderId: id, tableNo: table }]);
          toast.success(`🍽️ Order #${id} is READY — pick up for ${table}!`, { duration: 10000 });
          new Audio('/sounds/pickup-ready.mp3')?.play().catch(() => {});
          refetchOrders();
        }
      });

    return () => ch.stopListening('.order.status.updated');
  }, [effectiveUserId, user?.hotel_id]);

  // ── Menu fetch ─────────────────────────────────────────────────────────────
  const { data: menuItems = [], isLoading } = useQuery<MenuItem[]>({
    queryKey: ['pos-menu', user?.outlet_id],
    queryFn: async () => {
      const { data } = await api.get(
        `/api/v1/menus/items${user?.outlet_id ? `?outlet_id=${user.outlet_id}` : ''}`,
      );
      return data.data ?? [];
    },
    refetchInterval: 60_000,
  });

  const categories = ['all', ...Array.from(new Set(menuItems.map((m) => m.category)))];
  const filtered = activeCategory === 'all' ? menuItems : menuItems.filter((m) => m.category === activeCategory);
  const total     = cart.reduce((s, i) => s + i.price * i.qty, 0);
  const cartQty   = cart.reduce((s, i) => s + i.qty, 0);

  const addToCart = (item: MenuItem) =>
    setCart((c) => {
      const ex = c.find((i) => i.id === item.id);
      return ex
        ? c.map((i) => (i.id === item.id ? { ...i, qty: i.qty + 1 } : i))
        : [...c, { ...item, qty: 1, notes: '' }];
    });

  const removeFromCart = (item: MenuItem) =>
    setCart((c) => {
      const ex = c.find((i) => i.id === item.id);
      return !ex || ex.qty <= 1 ? c.filter((i) => i.id !== item.id) : c.map((i) => (i.id === item.id ? { ...i, qty: i.qty - 1 } : i));
    });

  // ── Create draft order ─────────────────────────────────────────────────────
  const createDraft = useMutation({
    mutationFn: () =>
      api.post('/api/v1/orders', {
        outlet_id: user?.outlet_id,
        waiter_id: effectiveUserId, 
        table_number: tableNo,
        items: cart.map((i) => ({ menu_item_id: i.id, quantity: i.qty, unit_price: i.price, notes: i.notes })),
      }),
    onSuccess: ({ data }) => {
      setDraftOrderId(data.data.id);
      toast.info('Draft created — press 🔥 Fire Order to send to kitchen');
    },
    onError: () => toast.error('Failed to create order.'),
  });

  // ── Fire order (draft → pending) ───────────────────────────────────────────
  const fireOrder = useMutation({
    mutationFn: (orderId: number) => api.post(`/api/v1/orders/${orderId}/fire`),
    onSuccess: () => {
      toast.success('🔥 Order fired to kitchen!');
      setCart([]);
      setTableNo('');
      setDraftOrderId(null);
      setCartOpen(false);
      refetchOrders();
    },
    onError: () => toast.error('Failed to fire order.'),
  });

  const handleFireOrder = async () => {
    if (cart.length === 0) return;
    if (!tableNo) { toast.warning('Enter a table or room number first'); return; }

    if (draftOrderId) {
      fireOrder.mutate(draftOrderId);
    } else {
      const { data } = await api.post('/api/v1/orders', {
        outlet_id: user?.outlet_id,
        waiter_id: effectiveUserId,
        table_number: tableNo,
        items: cart.map((i) => ({ menu_item_id: i.id, quantity: i.qty, unit_price: i.price, notes: i.notes })),
      });
      fireOrder.mutate(data.data.id);
    }
  };

  return (
    <div className="min-h-screen bg-background pb-44 max-w-lg mx-auto">
      {/* ── Header ─── */}
      <div className="sticky top-0 z-30 border-b bg-card px-4 py-3 flex items-center justify-between shadow-sm">
        <div>
          <h1 className="font-bold text-base leading-none">Mobile POS</h1>
          <p className="text-xs text-muted-foreground">{user?.name} · Outlet #{user?.outlet_id ?? '—'}</p>
        </div>
        <div className="flex items-center gap-2">
          {readyNotifs.length > 0 && (
            <button
              onClick={() => setReadyNotifs([])}
              className="relative flex items-center gap-1.5 bg-emerald-500/15 border border-emerald-500/30 text-emerald-600 text-xs font-bold px-3 py-1.5 rounded-full animate-pulse"
            >
              <BellRing className="h-3.5 w-3.5" />
              {readyNotifs.length}
            </button>
          )}
          <input
            value={tableNo}
            onChange={(e) => setTableNo(e.target.value)}
            placeholder="Table #"
            className="w-20 border rounded-lg px-2 py-1.5 text-sm bg-background text-center"
          />
          {cartQty > 0 && (
            <button onClick={() => setCartOpen(true)} className="relative">
              <ShoppingCart className="h-5 w-5" />
              <span className="absolute -top-1.5 -right-1.5 h-4 w-4 rounded-full bg-primary text-[10px] font-bold text-primary-foreground flex items-center justify-center">
                {cartQty}
              </span>
            </button>
          )}
        </div>
      </div>

      {/* ── View Toggle ─── */}
      <div className="flex p-4 gap-2">
        <Button 
          variant={activeTab === 'menu' ? 'secondary' : 'ghost'} 
          className="flex-1 rounded-xl h-10 font-bold"
          onClick={() => setActiveTab('menu')}
        >
          Menu Grid
        </Button>
        <Button 
          variant={activeTab === 'status' ? 'secondary' : 'ghost'} 
          className="flex-1 rounded-xl h-10 font-bold gap-2"
          onClick={() => setActiveTab('status')}
        >
          Shift Pulse
          {readyNotifs.length > 0 && <span className="w-2 h-2 rounded-full bg-emerald-500 animate-ping" />}
        </Button>
      </div>

      {/* ── Main Content ─── */}
      {activeTab === 'menu' ? (
        <>
          <div className="flex gap-2 px-4 py-3 overflow-x-auto scrollbar-hide border-b bg-card/50">
            {categories.map((cat) => (
              <button
                key={cat}
                onClick={() => setActiveCategory(cat)}
                className={`px-3 py-1.5 rounded-full text-xs font-semibold whitespace-nowrap transition-all ${
                  activeCategory === cat ? 'bg-primary text-primary-foreground shadow-sm' : 'bg-muted text-muted-foreground hover:bg-accent'
                }`}
              >
                {cat.charAt(0).toUpperCase() + cat.slice(1)}
              </button>
            ))}
          </div>

          {isLoading ? (
            <div className="flex justify-center py-16"><Loader2 className="animate-spin h-8 w-8 text-muted-foreground" /></div>
          ) : (
            <div className="grid grid-cols-2 gap-3 p-4">
              {filtered.map((item) => {
                const inCart = cart.find((c) => c.id === item.id);
                return (
                  <div key={item.id} className={`rounded-2xl border p-3 space-y-2 transition-all active:scale-95 ${inCart ? 'border-primary/50 bg-primary/5' : 'bg-card'}`}>
                    <div className="flex items-start justify-between gap-1">
                      <div>
                        <p className="text-sm font-semibold leading-tight line-clamp-2">{item.name}</p>
                        <p className="text-[10px] text-muted-foreground mt-0.5">🍳 {item.station_name ?? 'main'}</p>
                      </div>
                      <p className="text-xs font-bold text-primary whitespace-nowrap">₦{item.price.toLocaleString()}</p>
                    </div>
                    <div className="flex items-center justify-end">
                      {inCart ? (
                        <div className="flex items-center gap-2">
                          <button onClick={() => removeFromCart(item)} className="w-7 h-7 rounded-full bg-muted flex items-center justify-center"><Minus className="h-3 w-3" /></button>
                          <span className="text-sm font-bold w-5 text-center">{inCart.qty}</span>
                          <button onClick={() => addToCart(item)} className="w-7 h-7 rounded-full bg-primary text-primary-foreground flex items-center justify-center"><Plus className="h-3 w-3" /></button>
                        </div>
                      ) : (
                        <button onClick={() => addToCart(item)} className="flex items-center gap-1 text-xs font-semibold px-3 py-1.5 rounded-full bg-primary text-primary-foreground">
                          <Plus className="h-3 w-3" /> Add
                        </button>
                      )}
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </>
      ) : (
        <div className="p-4 space-y-4 animate-in fade-in slide-in-from-right-4">
          {myOrders.length === 0 ? (
            <div className="flex flex-col items-center justify-center py-24 text-slate-500 opacity-20">
               <CheckCircle2 size={64} className="mb-4" />
               <p className="font-black tracking-widest text-xs uppercase">All Current Orders Fulfilled</p>
            </div>
          ) : (
            myOrders.map((order: any) => {
              const elapsed = order.elapsed_mins || 0;
              const isLate = elapsed > 20;
              return (
                <div key={order.id} className={`p-4 rounded-2xl border transition-all ${
                  order.status === 'ready' ? 'bg-emerald-500/10 border-emerald-500/30' : 'bg-card border-border'
                }`}>
                  <div className="flex justify-between items-start mb-3">
                    <div>
                      <h4 className="font-black text-sm uppercase">Table {order.table_number || '—'}</h4>
                      <p className="text-[10px] text-slate-500 font-bold uppercase tracking-widest">ORD-#{order.id}</p>
                    </div>
                    <div className={`flex items-center gap-1.5 px-3 py-1 rounded-full ${
                      order.status === 'ready' ? 'bg-emerald-500 text-white' : isLate ? 'bg-rose-500 text-white animate-pulse' : 'bg-slate-100 text-slate-900'
                    }`}>
                      <Timer size={14} />
                      <span className="text-xs font-black">{elapsed}m</span>
                    </div>
                  </div>
                  <div className="flex items-center justify-between">
                    <Badge variant="outline" className={`font-bold capitalize ${order.status === 'ready' ? 'text-emerald-500 border-emerald-500' : ''}`}>
                      {order.status}
                    </Badge>
                    <span className="text-xs font-bold text-slate-400">₦{order.total_amount.toLocaleString()}</span>
                  </div>
                </div>
              );
            })
          )}
        </div>
      )}

      {/* ── Cart Drawer ─── */}
      {cart.length > 0 && (
        <div className={`fixed bottom-0 left-0 right-0 z-40 max-w-lg mx-auto bg-card border-t shadow-2xl transition-all ${cartOpen ? 'rounded-t-2xl' : ''}`}>
          {cartOpen && (
            <div className="p-4 border-b flex items-center justify-between">
              <span className="font-bold text-sm">Your Order</span>
              <button onClick={() => setCartOpen(false)}><X className="h-4 w-4 text-muted-foreground" /></button>
            </div>
          )}
          {cartOpen && (
            <div className="px-4 py-2 max-h-40 overflow-y-auto space-y-1.5">
              {cart.map((item) => (
                <div key={item.id} className="flex items-center justify-between text-sm">
                  <span>{item.name} ×{item.qty}</span>
                  <span className="font-semibold">₦{(item.price * item.qty).toLocaleString()}</span>
                </div>
              ))}
            </div>
          )}
          <div className="p-4 space-y-2">
            <div className="flex items-center justify-between text-sm font-bold">
              <span>Total ({cartQty} items)</span>
              <span className="text-primary">₦{total.toLocaleString()}</span>
            </div>
            <Button
              className="w-full h-12 font-extrabold text-base gap-2 bg-gradient-to-r from-orange-500 to-red-600 hover:from-orange-600 hover:to-red-700 shadow-xl shadow-orange-500/30 border-0"
              disabled={!tableNo || fireOrder.isPending || createDraft.isPending}
              onClick={handleFireOrder}
            >
              {(fireOrder.isPending || createDraft.isPending)
                ? <><Loader2 className="h-5 w-5 animate-spin" /> Firing to Kitchen…</>
                : <><Flame className="h-5 w-5" /> 🔥 Fire Order</>}
            </Button>
            {!tableNo && <p className="text-center text-xs text-muted-foreground">⚠️ Set table number first</p>}
          </div>
        </div>
      )}
    </div>
  );
}
