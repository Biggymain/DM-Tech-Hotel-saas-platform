import * as React from 'react';
import Link from 'next/link';
import { cn } from '@/lib/utils';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Card, CardContent, CardDescription, CardHeader, CardTitle, CardFooter } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Loader2, PlusIcon, SaveIcon, ShieldCheck, CreditCard, ExternalLink } from 'lucide-react';
import api from '@/lib/api';
import { toast } from 'sonner';

const GATEWAY_OPTIONS = [
  { id: 'monnify', name: 'Monnify', url: 'https://monnify.com' },
  { id: 'paystack', name: 'Paystack', url: 'https://paystack.com' },
  { id: 'stripe', name: 'Stripe', url: 'https://stripe.com' },
  { id: 'paypal', name: 'PayPal', url: 'https://paypal.com' },
];

export default function PaymentGatewaysPage() {
  const queryClient = useQueryClient();
  const [selectedGateway, setSelectedGateway] = React.useState('monnify');
  const [loading, setLoading] = React.useState(false);
  const [form, setForm] = React.useState({
    api_key: '',
    api_secret: '',
    payment_mode: 'test',
    is_active: true
  });

  const { data: gateways = [], isLoading } = useQuery({
    queryKey: ['configured-gateways'],
    queryFn: async () => {
      const { data } = await api.get('/api/v1/payments/gateways');
      return data;
    }
  });

  React.useEffect(() => {
    const current = gateways.find((g: any) => g.gateway_name === selectedGateway);
    if (current) {
      setForm({
        api_key: (current.api_key as string) || '',
        api_secret: (current.api_secret as string) || '',
        payment_mode: (current.payment_mode as string) || 'test',
        is_active: !!current.is_active
      });
    } else {
      setForm({ api_key: '', api_secret: '', payment_mode: 'test', is_active: true });
    }
  }, [selectedGateway, gateways]);

  const saveMutation = useMutation({
    mutationFn: (data: any) => api.post('/api/v1/payments/gateways', { ...data, gateway_name: selectedGateway }),
    onSuccess: () => {
      toast.success('Gateway Configured', { description: `${selectedGateway.toUpperCase()} credentials have been saved securely.` });
      queryClient.invalidateQueries({ queryKey: ['configured-gateways'] });
    },
    onError: (err: any) => {
      toast.error('Configuration Failed', { description: err.response?.data?.message || 'Unauthorized access.' });
    }
  });

  const testConnection = async () => {
    setLoading(true);
    try {
      const { data } = await api.post('/api/v1/admin/payment-gateways/test', { gateway: selectedGateway });
      if (data.success) {
        toast.success('Connection Successful', { description: data.message });
      } else {
        toast.error('Connection Failed', { description: data.message });
      }
    } catch (err: any) {
      toast.error('Connection Failed', { description: err.response?.data?.message || 'Service unreachable.' });
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="space-y-6 max-w-5xl">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h2 className="text-3xl font-bold tracking-tight">Payment Gateways</h2>
          <p className="text-muted-foreground">Configure secure integrations for guest portal and POS payments.</p>
        </div>
        <div className="flex items-center gap-2 px-3 py-1.5 bg-emerald-500/10 text-emerald-600 rounded-full text-xs font-bold uppercase tracking-widest border border-emerald-500/20">
          <ShieldCheck className="h-3.5 w-3.5" />
          PCI-DSS Compliant Storage
        </div>
      </div>

      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* Left: Provider Selection */}
        <div className="space-y-4">
          <Card>
            <CardHeader>
              <CardTitle className="text-lg">Select Provider</CardTitle>
            </CardHeader>
            <CardContent className="p-2">
              <div className="flex flex-col gap-1">
                {GATEWAY_OPTIONS.map((gateway) => {
                  const isConfigured = gateways.some((g: any) => g.gateway_name === gateway.id && g.is_active);
                  return (
                    <button
                      key={gateway.id}
                      onClick={() => setSelectedGateway(gateway.id)}
                      className={cn(
                        "flex items-center justify-between px-4 py-3 rounded-xl text-left transition-all",
                        selectedGateway === gateway.id 
                          ? "bg-primary text-primary-foreground shadow-lg shadow-primary/20" 
                          : "hover:bg-muted text-muted-foreground"
                      )}
                    >
                      <div className="flex items-center gap-3">
                        <CreditCard className="h-4 w-4" />
                        <span className="font-medium">{gateway.name}</span>
                      </div>
                      {isConfigured && (
                        <Badge variant="outline" className={cn("ml-2 border-emerald-500/50 text-emerald-500", selectedGateway === gateway.id && "text-white border-white/50")}>
                          Active
                        </Badge>
                      )}
                    </button>
                  );
                })}
              </div>
            </CardContent>
          </Card>
          
          <Card className="bg-muted/30">
            <CardContent className="pt-6">
              <div className="flex items-start gap-3">
                <ShieldCheck className="h-5 w-5 text-primary mt-0.5" />
                <div className="space-y-1">
                  <p className="text-xs font-semibold uppercase tracking-wider opacity-60">Security Info</p>
                  <p className="text-[11px] leading-relaxed text-muted-foreground">
                    All API keys and secrets are encrypted at rest using AES-256-GCM. We never store raw payment data on our servers.
                  </p>
                </div>
              </div>
            </CardContent>
          </Card>
        </div>

        {/* Right: Configuration Form */}
        <div className="lg:col-span-2">
          <Card className="shadow-sm">
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-7">
              <div>
                <CardTitle className="text-xl">
                  {GATEWAY_OPTIONS.find(g => g.id === selectedGateway)?.name} Configuration
                </CardTitle>
                <CardDescription>Enter your API credentials to enable online payments.</CardDescription>
              </div>
              <a 
                href={GATEWAY_OPTIONS.find(g => g.id === selectedGateway)?.url} 
                target="_blank" 
                rel="noreferrer"
                className="text-xs text-primary flex items-center gap-1 hover:underline underline-offset-4"
              >
                Go to Dashboard <ExternalLink className="h-3 w-3" />
              </a>
            </CardHeader>
            <CardContent className="space-y-6">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="space-y-2">
                  <Label>Public / API Key</Label>
                  <Input 
                    placeholder="pk_test_..." 
                    value={form.api_key}
                    onChange={e => setForm({...form, api_key: e.target.value})}
                  />
                  <p className="text-[10px] text-muted-foreground">Your primary identification key.</p>
                </div>
                <div className="space-y-2">
                  <Label>Secret Key</Label>
                  <Input 
                    type="password" 
                    placeholder="sk_test_..." 
                    value={form.api_secret}
                    onChange={e => setForm({...form, api_secret: e.target.value})}
                  />
                  <p className="text-[10px] text-muted-foreground">Keep this key strictly confidential.</p>
                </div>
              </div>

              <div className="grid grid-cols-1 sm:grid-cols-2 gap-6 pt-4 border-t">
                <div className="space-y-3">
                  <Label>Payment Mode</Label>
                  <Select 
                    value={form.payment_mode} 
                    onValueChange={v => setForm({...form, payment_mode: v})}
                  >
                    <SelectTrigger className="w-full">
                      <SelectValue placeholder="Select mode" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="test">Test Mode (Sanbox)</SelectItem>
                      <SelectItem value="live">Live Mode (Production)</SelectItem>
                    </SelectContent>
                  </Select>
                  <p className="text-[10px] text-muted-foreground leading-relaxed">
                    Test mode allows you to simulate transactions without real money. Use Live mode ONLY for production.
                  </p>
                </div>

                <div className="flex flex-col justify-end gap-3 pb-1">
                   <div className="flex items-center justify-between p-3 rounded-lg border bg-muted/20">
                     <span className="text-sm font-medium">Gateway Active</span>
                     <button
                        onClick={() => setForm({...form, is_active: !form.is_active})}
                        className={cn(
                          "w-10 h-5 rounded-full p-1 transition-colors relative duration-200 focus:outline-none",
                          form.is_active ? "bg-primary" : "bg-muted-foreground/30"
                        )}
                      >
                        <div className={cn(
                          "w-3 h-3 bg-white rounded-full p-0 shadow-sm transition-transform duration-200",
                          form.is_active ? "translate-x-5" : "translate-x-0"
                        )} />
                      </button>
                   </div>
                </div>
              </div>
            </CardContent>
            <CardFooter className="justify-between border-t mt-4 pt-6 bg-muted/10">
              <Button 
                variant="outline" 
                onClick={testConnection} 
                disabled={loading || !form.api_key}
              >
                {loading ? <Loader2 className="mr-2 h-3 w-3 animate-spin" /> : <ShieldCheck className="mr-2 h-4 w-4" />}
                Test Connection
              </Button>
              <Button 
                onClick={() => saveMutation.mutate(form)}
                disabled={saveMutation.isPending || !form.api_key}
                className="px-8 shadow-lg shadow-primary/20"
              >
                {saveMutation.isPending ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <SaveIcon className="mr-2 h-4 w-4" />}
                Save Config
              </Button>
            </CardFooter>
          </Card>
        </div>
      </div>
    </div>
  );
}
