'use client';

import { useState, useEffect } from 'react';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { 
  CheckCircle2, 
  CreditCard, 
  History, 
  Zap, 
  AlertCircle, 
  Calendar,
  Building2,
  Users,
  LayoutDashboard,
  Check
} from 'lucide-react';
import { toast } from 'sonner';
import axios from 'axios';
import { Skeleton } from '@/components/ui/skeleton';

export default function SubscriptionPage() {
  const [subscription, setSubscription] = useState<any>(null);
  const [plans, setPlans] = useState<any[]>([]);
  const [invoices, setInvoices] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [processing, setProcessing] = useState(false);

  useEffect(() => {
    fetchData();
  }, []);

  const fetchData = async () => {
    try {
      const apiBase = process.env.NEXT_PUBLIC_API_URL || '/api';
      const [subRes, plansRes, invoicesRes] = await Promise.all([
        axios.get(`${apiBase}/v1/admin/subscription/current`),
        axios.get(`${apiBase}/v1/admin/subscription/plans`),
        axios.get(`${apiBase}/v1/admin/subscription/invoices`)
      ]);
      setSubscription(subRes.data.subscription);
      setPlans(plansRes.data);
      setInvoices(invoicesRes.data);
    } catch (error) {
      toast.error('Failed to load subscription data');
    } finally {
      setLoading(false);
    }
  };

  const handleUpgrade = async (planId: number) => {
    setProcessing(true);
    try {
      const apiBase = process.env.NEXT_PUBLIC_API_URL || '/api';
      await axios.post(`${apiBase}/v1/admin/subscription/checkout`, {
        plan_id: planId,
        gateway: 'stripe'
      });
      toast.success('Subscription updated successfully!');
      fetchData();
    } catch (error) {
      toast.error('Upgrade failed. Please try again.');
    } finally {
      setProcessing(false);
    }
  };

  if (loading) {
    return (
      <div className="p-8 space-y-6">
        <Skeleton className="h-12 w-1/4" />
        <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
          <Skeleton className="h-48" />
          <Skeleton className="h-48" />
          <Skeleton className="h-48" />
        </div>
      </div>
    );
  }

  const currentPlanId = subscription?.plan_id;

  return (
    <div className="p-8 max-w-7xl mx-auto space-y-10 animate-in fade-in duration-500">
      <div className="flex justify-between items-end">
        <div>
          <h1 className="text-4xl font-bold tracking-tight">Subscription & Billing</h1>
          <p className="text-muted-foreground text-lg mt-2">Manage your hotel's SaaS plan and view billing history.</p>
        </div>
        <Badge variant={subscription?.status === 'active' ? 'default' : 'destructive'} className="h-8 px-4 text-sm font-semibold uppercase tracking-wider">
          {subscription?.status || 'No Subscription'}
        </Badge>
      </div>

      {/* Current Plan Overview */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <Card className="lg:col-span-2 shadow-xl border-primary/10 bg-gradient-to-br from-card to-muted/20">
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <Zap className="w-5 h-5 text-primary" />
              Current Plan: {subscription?.plan?.name || 'None'}
            </CardTitle>
            <CardDescription>Your account is currently on the {subscription?.plan?.name || 'free trial'} tier.</CardDescription>
          </CardHeader>
          <CardContent className="grid grid-cols-1 sm:grid-cols-3 gap-6">
            <div className="p-4 rounded-xl bg-background shadow-sm border space-y-1">
              <p className="text-xs text-muted-foreground font-medium uppercase tracking-tighter">Billing Cycle</p>
              <p className="text-xl font-bold capitalize">{subscription?.plan?.billing_cycle || 'Monthly'}</p>
            </div>
            <div className="p-4 rounded-xl bg-background shadow-sm border space-y-1">
              <p className="text-xs text-muted-foreground font-medium uppercase tracking-tighter">Next Invoice</p>
              <p className="text-xl font-bold">{subscription?.current_period_end ? new Date(subscription.current_period_end).toLocaleDateString() : 'N/A'}</p>
            </div>
            <div className="p-4 rounded-xl bg-background shadow-sm border space-y-1">
              <p className="text-xs text-muted-foreground font-medium uppercase tracking-tighter">Gateway</p>
              <p className="text-xl font-bold uppercase">{subscription?.payment_gateway || 'None'}</p>
            </div>
          </CardContent>
          <CardFooter className="border-t pt-6 bg-muted/5">
             <div className="grid grid-cols-2 gap-4 w-full">
                <div className="flex items-center gap-3">
                   <div className="p-2 bg-primary/10 rounded-lg"><Building2 className="w-4 h-4 text-primary" /></div>
                   <span className="text-sm font-medium">{subscription?.plan?.max_rooms || 0} Rooms Limit</span>
                </div>
                <div className="flex items-center gap-3">
                   <div className="p-2 bg-primary/10 rounded-lg"><Users className="w-4 h-4 text-primary" /></div>
                   <span className="text-sm font-medium">{subscription?.plan?.max_staff || 0} Staff Accounts</span>
                </div>
             </div>
          </CardFooter>
        </Card>

        {/* Payment Method Card */}
        <Card className="shadow-xl border-dashed">
          <CardHeader>
            <CardTitle className="text-lg">Payment Method</CardTitle>
          </CardHeader>
          <CardContent className="flex flex-col items-center justify-center py-6 space-y-4">
             <div className="bg-muted p-6 rounded-2xl">
                <CreditCard className="w-12 h-12 text-muted-foreground" />
             </div>
             <p className="text-sm text-center text-muted-foreground">No payment method attached. Upgrade a plan to add one.</p>
             <Button variant="outline" className="w-full">Update Payment Method</Button>
          </CardContent>
        </Card>
      </div>

      {/* Plans Selection */}
      <div className="space-y-6">
        <h2 className="text-2xl font-bold tracking-tight text-center">Available Plans</h2>
        <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
          {plans.map((plan) => (
            <Card key={plan.id} className={`flex flex-col relative overflow-hidden transition-all duration-300 hover:shadow-2xl ${plan.id === currentPlanId ? 'ring-2 ring-primary border-primary shadow-primary/10' : ''}`}>
              {plan.id === currentPlanId && (
                <div className="absolute top-0 right-0 bg-primary text-primary-foreground px-4 py-1 text-xs font-bold rounded-bl-lg">
                  CURRENT
                </div>
              )}
              <CardHeader className="text-center pb-2">
                <CardTitle className="text-2xl">{plan.name}</CardTitle>
                <CardDescription className="text-4xl font-black text-foreground mt-4">
                  ${plan.price}<span className="text-sm font-normal text-muted-foreground">/{plan.billing_cycle === 'monthly' ? 'mo' : 'yr'}</span>
                </CardDescription>
              </CardHeader>
              <CardContent className="flex-grow pt-6">
                <ul className="space-y-4">
                  {plan.features.map((feature: string, idx: number) => (
                    <li key={idx} className="flex items-start gap-3 text-sm">
                      <Check className="w-4 h-4 text-green-500 mt-0.5" />
                      {feature}
                    </li>
                  ))}
                </ul>
              </CardContent>
              <CardFooter className="pt-6">
                <Button 
                  className="w-full h-11 text-base font-bold transition-all" 
                  variant={plan.id === currentPlanId ? 'secondary' : 'default'}
                  disabled={plan.id === currentPlanId || processing}
                  onClick={() => handleUpgrade(plan.id)}
                >
                  {plan.id === currentPlanId ? 'Active Plan' : (plan.price < (subscription?.plan?.price || 0) ? 'Downgrade' : 'Upgrade Now')}
                </Button>
              </CardFooter>
            </Card>
          ))}
        </div>
      </div>

      {/* Billing History */}
      <div className="space-y-6">
        <div className="flex items-center gap-3">
          <History className="w-6 h-6 text-primary" />
          <h2 className="text-2xl font-bold tracking-tight">Billing History</h2>
        </div>
        <Card className="shadow-lg overflow-hidden border-none bg-card/50 backdrop-blur-sm">
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm border-collapse">
              <thead>
                <tr className="bg-muted/50 border-b">
                  <th className="px-6 py-4 font-bold text-muted-foreground uppercase tracking-wider">Date</th>
                  <th className="px-6 py-4 font-bold text-muted-foreground uppercase tracking-wider">Reference</th>
                  <th className="px-6 py-4 font-bold text-muted-foreground uppercase tracking-wider">Amount</th>
                  <th className="px-6 py-4 font-bold text-muted-foreground uppercase tracking-wider">Gateway</th>
                  <th className="px-6 py-4 font-bold text-muted-foreground uppercase tracking-wider text-right">Status</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-muted/30">
                {invoices.length === 0 ? (
                  <tr>
                    <td colSpan={5} className="px-6 py-10 text-center text-muted-foreground italic">No invoices found.</td>
                  </tr>
                ) : (
                  invoices.map((invoice) => (
                    <tr key={invoice.id} className="hover:bg-muted/20 transition-colors">
                      <td className="px-6 py-4 font-medium">{new Date(invoice.created_at).toLocaleDateString()}</td>
                      <td className="px-6 py-4 text-xs font-mono opacity-60 uppercase">{invoice.payment_reference}</td>
                      <td className="px-6 py-4 font-bold">${invoice.amount}</td>
                      <td className="px-6 py-4 uppercase font-semibold text-xs">{invoice.payment_gateway}</td>
                      <td className="px-6 py-4 text-right">
                        <Badge variant="outline" className="bg-green-500/10 text-green-500 border-green-500/20">
                          {invoice.status}
                        </Badge>
                      </td>
                    </tr>
                  ))
                )}
              </tbody>
            </table>
          </div>
        </Card>
      </div>
    </div>
  );
}
