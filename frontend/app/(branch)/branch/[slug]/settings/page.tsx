'use client';

import * as React from 'react';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { toast } from 'sonner';
import { Loader2, SaveIcon, CreditCard, Palette, Globe, ShieldCheck } from 'lucide-react';
import api from '@/lib/api';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle, DialogTrigger, DialogFooter } from '@/components/ui/dialog';
import { cn } from '@/lib/utils';
import { Badge } from '@/components/ui/badge';
import { Plus, X, Landmark, Printer, Mail, UserPlus } from 'lucide-react';

export default function BranchSettingsPage() {
  const [activeTab, setActiveTab] = React.useState('info');
  const [loading, setLoading] = React.useState(false);
  const [fetching, setFetching] = React.useState(true);
  const [config, setConfig] = React.useState({
    name: '',
    primary_color: '#0f172a',
    currency: 'USD',
    bank_name: '',
    account_number: '',
    account_name: '',
    pos_terminal_id: '',
    stakeholder_emails: [] as string[],
  });
  const [newEmail, setNewEmail] = React.useState('');

  React.useEffect(() => {
    const fetchSettings = async () => {
      try {
        const { data } = await api.get('/api/v1/organization/overview');
        if (data.group || data.hotel) {
          const source = data.hotel || data.group;
          setConfig({
            name: source.name || '',
            primary_color: source.primary_color || '#0f172a',
            currency: source.currency || 'USD',
            bank_name: data.hotel?.bank_name || '',
            account_number: data.hotel?.account_number || '',
            account_name: data.hotel?.account_name || '',
            pos_terminal_id: data.hotel?.pos_terminal_id || '',
            stakeholder_emails: data.hotel?.stakeholder_emails || [],
          });
        }
      } catch (err) {
        toast.error('Failed to load settings');
      } finally {
        setFetching(false);
      }
    };
    fetchSettings();
  }, []);

  const handleSave = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    try {
      await api.put('/api/v1/organization/settings', config);
      toast.success('Settings Updated successfully.');
    } catch (err: any) {
      toast.error('Update Failed', { 
        description: err.response?.data?.message || 'Check your permissions.' 
      });
    } finally {
      setLoading(false);
    }
  };

  if (fetching) {
    return (
      <div className="flex h-96 items-center justify-center">
        <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-3xl font-bold tracking-tight">Branch Settings</h2>
        <p className="text-muted-foreground">Configure localized branding, payments, and branch-specific rules.</p>
      </div>

      <Tabs value={activeTab} onValueChange={setActiveTab} className="space-y-6">
        <TabsList className="grid grid-cols-2 md:grid-cols-4 lg:w-[600px]">
          <TabsTrigger value="info" className="gap-2">
            <Palette className="h-4 w-4" />
            Branding
          </TabsTrigger>
          <TabsTrigger value="gateways" className="gap-2">
            <CreditCard className="h-4 w-4" />
            Gateways
          </TabsTrigger>
          <TabsTrigger value="finance" className="gap-2">
            <Landmark className="h-4 w-4" />
            Finance
          </TabsTrigger>
          <TabsTrigger value="localization" className="gap-2">
            <Globe className="h-4 w-4" />
            Localization
          </TabsTrigger>
          <TabsTrigger value="security" className="gap-2">
            <ShieldCheck className="h-4 w-4" />
            Access
          </TabsTrigger>
        </TabsList>

        <TabsContent value="info">
          <form onSubmit={handleSave}>
            <div className="grid gap-6">
              <Card>
                <CardHeader>
                  <CardTitle>Identity</CardTitle>
                  <CardDescription>Branch name and localized brand color.</CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div className="space-y-2">
                    <Label htmlFor="name">Branch Display Name</Label>
                    <Input 
                      id="name" 
                      value={config.name} 
                      onChange={(e) => setConfig({...config, name: e.target.value})} 
                    />
                  </div>

                  <div className="space-y-2 flex flex-col">
                    <Label htmlFor="primary_color">Primary Brand Color</Label>
                    <div className="flex items-center gap-3">
                      <Input 
                        id="primary_color" 
                        type="color" 
                        className="w-16 h-10 p-1 cursor-pointer"
                        value={config.primary_color} 
                        onChange={(e) => setConfig({...config, primary_color: e.target.value})} 
                      />
                      <span className="font-mono text-sm uppercase">{config.primary_color}</span>
                    </div>
                  </div>
                </CardContent>
                <CardFooter className="justify-end border-t pt-6">
                  <Button type="submit" disabled={loading}>
                    {loading ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <SaveIcon className="mr-2 h-4 w-4" />}
                    Save Identity
                  </Button>
                </CardFooter>
              </Card>
            </div>
          </form>
        </TabsContent>

        <TabsContent value="gateways">
          <Card>
            <CardHeader>
              <CardTitle>Payment Gateways</CardTitle>
              <CardDescription>Configure payment providers for this specific branch.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-6">
              <GatewayConfigRow 
                name="Stripe" 
                slug="stripe" 
                description="Global payments & credit cards" 
                color="bg-blue-600"
                letter="S"
              />

              <GatewayConfigRow 
                name="Paystack" 
                slug="paystack" 
                description="Localized African payments" 
                color="bg-emerald-600"
                letter="P"
              />

              <GatewayConfigRow 
                name="Monnify" 
                slug="monnify" 
                description="Premium Nigerian bank transfers & cards" 
                color="bg-blue-500"
                letter="M"
              />

              <GatewayConfigRow 
                name="Flutterwave" 
                slug="flutterwave" 
                description="Seamless global & African payments" 
                color="bg-orange-500"
                letter="F"
              />
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="finance">
          <div className="grid gap-6">
            <form onSubmit={handleSave}>
              <Card>
                <CardHeader>
                  <CardTitle>Manual Payment Routing</CardTitle>
                  <CardDescription>Configure the bank account details displayed to guests for manual transfers.</CardDescription>
                </CardHeader>
                <CardContent className="grid md:grid-cols-2 gap-6">
                  <div className="space-y-2">
                    <Label>Bank Name</Label>
                    <Input 
                      value={config.bank_name} 
                      onChange={e => setConfig({...config, bank_name: e.target.value})} 
                      placeholder="e.g. GTBank, Zenith"
                    />
                  </div>
                  <div className="space-y-2">
                    <Label>Account Name (Beneficiary)</Label>
                    <Input 
                      value={config.account_name} 
                      onChange={e => setConfig({...config, account_name: e.target.value})} 
                      placeholder="e.g. DM-Tech Hotel Osogbo"
                    />
                  </div>
                  <div className="space-y-2">
                    <Label>Account Number</Label>
                    <Input 
                      value={config.account_number} 
                      onChange={e => setConfig({...config, account_number: e.target.value})} 
                      placeholder="0123456789"
                    />
                  </div>
                  <div className="space-y-2">
                    <Label>POS Terminal ID</Label>
                    <Input 
                      value={config.pos_terminal_id} 
                      onChange={e => setConfig({...config, pos_terminal_id: e.target.value})} 
                      placeholder="2034AB12"
                    />
                  </div>
                </CardContent>
                <CardHeader className="border-t">
                  <CardTitle>Audit Recipients</CardTitle>
                  <CardDescription>Emails that will receive the Stakeholder Monthly Audit Report.</CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                  <div className="flex gap-2">
                    <Input 
                      value={newEmail} 
                      onChange={e => setNewEmail(e.target.value)} 
                      placeholder="stakeholder@email.com" 
                      type="email"
                    />
                    <Button type="button" variant="secondary" onClick={() => {
                        if (newEmail && !config.stakeholder_emails.includes(newEmail)) {
                            setConfig({...config, stakeholder_emails: [...config.stakeholder_emails, newEmail]});
                            setNewEmail('');
                        }
                    }}>
                      <UserPlus className="h-4 w-4 mr-2" />
                      Add
                    </Button>
                  </div>
                  <div className="flex flex-wrap gap-2">
                    {config.stakeholder_emails.map(email => (
                      <Badge key={email} variant="secondary" className="pl-3 pr-1 py-1 gap-1">
                        {email}
                        <Button 
                          type="button"
                          variant="ghost" 
                          size="icon" 
                          className="h-4 w-4 hover:bg-destructive hover:text-white rounded-full"
                          onClick={() => setConfig({...config, stakeholder_emails: config.stakeholder_emails.filter(e => e !== email)})}
                        >
                          <X className="h-3 w-3" />
                        </Button>
                      </Badge>
                    ))}
                    {config.stakeholder_emails.length === 0 && <p className="text-xs text-muted-foreground italic">No recipients added. Monthly audit reports will not be sent.</p>}
                  </div>
                </CardContent>
                <CardFooter className="justify-end border-t pt-6">
                  <Button type="submit" disabled={loading}>
                    {loading ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <SaveIcon className="mr-2 h-4 w-4" />}
                    Save Finance Settings
                  </Button>
                </CardFooter>
              </Card>
            </form>
          </div>
        </TabsContent>
        <TabsContent value="localization">
          <Card>
            <CardHeader>
              <CardTitle>Regional Settings</CardTitle>
              <CardDescription>Currency and timezone for this branch.</CardDescription>
            </CardHeader>
            <CardContent>
              <div className="space-y-2 max-w-sm">
                <Label>Branch Currency</Label>
                <Select value={config.currency} onValueChange={(val: any) => setConfig({...config, currency: val || 'USD'})}>
                  <SelectTrigger className="w-full">
                    <SelectValue placeholder="Select currency" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="USD">USD ($)</SelectItem>
                    <SelectItem value="EUR">EUR (€)</SelectItem>
                    <SelectItem value="GBP">GBP (£)</SelectItem>
                    <SelectItem value="NGN">NGN (₦)</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            </CardContent>
            <CardFooter className="justify-end border-t pt-6">
               <Button onClick={handleSave} disabled={loading}>Save Localization</Button>
            </CardFooter>
          </Card>
        </TabsContent>

        <TabsContent value="security">
           <Card>
             <CardHeader>
               <CardTitle>Security & Access</CardTitle>
               <CardDescription>Manage who can access this branch's data.</CardDescription>
             </CardHeader>
             <CardContent className="py-12 text-center text-muted-foreground">
                Role management is available in the Staff directory.
             </CardContent>
           </Card>
        </TabsContent>
      </Tabs>
    </div>
  );
}

function GatewayConfigRow({ name, slug, description, color, letter }: any) {
  const [open, setOpen] = React.useState(false);
  const [loading, setLoading] = React.useState(false);
  const [form, setForm] = React.useState({
    api_key: '',
    api_secret: '',
    webhook_secret: '',
    contract_code: '',
    payment_mode: 'test'
  });

  const fetchConfig = async () => {
    try {
      const { data } = await api.get(`/api/v1/payments/gateways`);
      const existing = data.find((g: any) => g.gateway_name === slug);
      if (existing) {
        setForm({
          api_key: existing.api_key || '',
          api_secret: existing.api_secret || '',
          webhook_secret: existing.webhook_secret || '',
          contract_code: existing.contract_code || '',
          payment_mode: existing.payment_mode || 'test'
        });
      }
    } catch (err) {}
  };

  React.useEffect(() => {
    if (open) fetchConfig();
  }, [open]);

  const handleSave = async () => {
    setLoading(true);
    try {
      await api.post(`/api/v1/payments/gateways`, {
        gateway_name: slug,
        ...form
      });
      toast.success(`${name} updated successfully`);
      setOpen(false);
    } catch (err: any) {
      toast.error(`Failed to update ${name}`, {
        description: err.response?.data?.message
      });
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="flex items-center justify-between p-4 border rounded-xl bg-muted/30">
      <div className="flex items-center gap-4">
        <div className={cn("h-10 w-10 rounded-full flex items-center justify-center text-white font-bold", color)}>
          {letter}
        </div>
        <div>
          <div className="font-semibold">{name}</div>
          <div className="text-xs text-muted-foreground">{description}</div>
        </div>
      </div>

      <Dialog open={open} onOpenChange={setOpen}>
        <DialogTrigger>
          <Button variant="outline" size="sm">Configure</Button>
        </DialogTrigger>
        <DialogContent className="sm:max-w-[425px]">
          <DialogHeader>
            <DialogTitle>Configure {name}</DialogTitle>
            <CardDescription>Enter your {name} credentials. These will be encrypted at rest.</CardDescription>
          </DialogHeader>
          <div className="grid gap-4 py-4">
            <div className="space-y-2">
              <Label>Public API Key / Client ID</Label>
              <Input 
                value={form.api_key} 
                onChange={e => setForm({...form, api_key: e.target.value})}
                placeholder="pk_test_..."
              />
            </div>
            <div className="space-y-2">
              <Label>Secret Key</Label>
              <Input 
                type="password"
                value={form.api_secret} 
                onChange={e => setForm({...form, api_secret: e.target.value})}
                placeholder="sk_test_..."
              />
            </div>
            {slug === 'monnify' && (
              <div className="space-y-2">
                <Label>Contract Code</Label>
                <Input 
                  value={form.contract_code} 
                  onChange={e => setForm({...form, contract_code: e.target.value})}
                />
              </div>
            )}
            <div className="space-y-2">
              <Label>Webhook Secret</Label>
              <Input 
                value={form.webhook_secret} 
                onChange={e => setForm({...form, webhook_secret: e.target.value})}
              />
            </div>
            <div className="space-y-2">
              <Label>Environment Mode</Label>
              <Select value={form.payment_mode || "test"} onValueChange={(v: string | null) => setForm({...form, payment_mode: v || ''})}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="test">Sandbox / Test Mode</SelectItem>
                  <SelectItem value="live">Production / Live Mode</SelectItem>
                </SelectContent>
              </Select>
            </div>
          </div>
          <DialogFooter>
            <Button onClick={handleSave} disabled={loading}>
              {loading && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
              Save Configuration
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
