'use client';

import * as React from 'react';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { toast } from 'sonner';
import { Loader2, SaveIcon } from 'lucide-react';
import api from '@/lib/api';

export default function BrandingSettingsPage() {
  const [loading, setLoading] = React.useState(false);
  const [fetching, setFetching] = React.useState(true);
  const [config, setConfig] = React.useState({
    name: '',
    primary_color: '#0f172a',
    currency: 'USD',
  });

  React.useEffect(() => {
    const fetchSettings = async () => {
      try {
        const { data } = await api.get('/api/v1/organization/overview');
        if (data.group) {
          setConfig({
            name: data.group.name || '',
            primary_color: data.group.primary_color || '#0f172a',
            currency: data.group.currency || 'USD',
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
      toast.success('Organization Settings Updated', { 
        description: 'The group-wide branding and currency have been applied.' 
      });
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
    <div className="space-y-6 max-w-4xl">
      <div>
        <h2 className="text-3xl font-bold tracking-tight">Organization Settings</h2>
        <p className="text-muted-foreground">Manage group-wide branding, currency, and localization for all branches.</p>
      </div>

      <form onSubmit={handleSave}>
        <div className="grid gap-6">
          <Card>
            <CardHeader>
              <CardTitle>Identity</CardTitle>
              <CardDescription>Hotel group name and primary brand color.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="space-y-2">
                <Label htmlFor="name">Group Name</Label>
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
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Localization</CardTitle>
              <CardDescription>Base currency for all financial reporting and branch defaults.</CardDescription>
            </CardHeader>
            <CardContent>
              <div className="space-y-2 max-w-sm">
                <Label>Group Currency</Label>
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
              <Button type="submit" disabled={loading}>
                {loading ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <SaveIcon className="mr-2 h-4 w-4" />}
                Save Changes
              </Button>
            </CardFooter>
          </Card>
        </div>
      </form>
    </div>
  );
}
