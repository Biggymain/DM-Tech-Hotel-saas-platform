'use client';

import * as React from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { PlusIcon, SparklesIcon, Loader2 } from 'lucide-react';
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { toast } from 'sonner';
import api from '@/lib/api';

export default function PricingPage() {
  const queryClient = useQueryClient();
  const [open, setOpen] = React.useState(false);
  const [rateForm, setRateForm] = React.useState({ name: '', pricing_strategy: 'fixed', base_price_modifier: 0 });

  const { data: ratePlans, isLoading } = useQuery({
    queryKey: ['rate-plans'],
    queryFn: async () => {
      try {
        const { data } = await api.get('/api/v1/pricing/rate-plans');
        return data ?? [];
      } catch {
        return [];
      }
    },
  });

  const createRatePlan = useMutation({
    mutationFn: (data: any) => api.post('/api/v1/pricing/rate-plans', data),
    onSuccess: () => {
      toast.success('Rate plan created successfully!');
      queryClient.invalidateQueries({ queryKey: ['rate-plans'] });
      setOpen(false);
      setRateForm({ name: '', pricing_strategy: 'fixed', base_price_modifier: 0 });
    },
    onError: (err: any) => {
      toast.error(err?.response?.data?.message ?? 'Failed to create rate plan.');
    },
  });

  return (
    <div className="space-y-6">
      <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h2 className="text-3xl font-bold tracking-tight">Yield & Pricing</h2>
          <p className="text-muted-foreground">Configure dynamic rates, seasonal adjustments, and occupancy-based rules.</p>
        </div>
        <div className="flex gap-2">
          <Button variant="outline">
            <SparklesIcon className="mr-2 h-4 w-4" />
            Dynamic Rules
          </Button>
          <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger
              nativeButton={true}
              render={
                <Button>
                  <PlusIcon className="mr-2 h-4 w-4" />
                  New Rate Plan
                </Button>
              }
            />
            <DialogContent className="sm:max-w-md">
              <DialogHeader>
                <DialogTitle>Create New Rate Plan</DialogTitle>
              </DialogHeader>
              <div className="space-y-4 py-4">
                <div className="space-y-1.5">
                  <label className="text-sm font-medium">Plan Name</label>
                  <Input
                    placeholder="e.g. Standard Daily, Weekend Special"
                    value={rateForm.name}
                    onChange={(e) => setRateForm(f => ({ ...f, name: e.target.value }))}
                  />
                </div>
                <div className="space-y-1.5">
                  <label className="text-sm font-medium">Pricing Strategy</label>
                  <Select
                    onValueChange={(val) => setRateForm(f => ({ ...f, pricing_strategy: val as string }))}
                    value={rateForm.pricing_strategy}
                  >
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="fixed">Fixed Rate</SelectItem>
                      <SelectItem value="seasonal">Seasonal</SelectItem>
                      <SelectItem value="occupancy">Occupancy Based</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div className="space-y-1.5">
                  <label className="text-sm font-medium">Base Price Modifier (%)</label>
                  <Input
                    type="number"
                    value={rateForm.base_price_modifier}
                    onChange={(e) => setRateForm(f => ({ ...f, base_price_modifier: parseFloat(e.target.value) }))}
                  />
                  <p className="text-[10px] text-muted-foreground italic">Applied on top of room type base price.</p>
                </div>
              </div>
              <DialogFooter>
                <Button variant="outline" onClick={() => setOpen(false)}>Cancel</Button>
                <Button
                  disabled={!rateForm.name || createRatePlan.isPending}
                  onClick={() => createRatePlan.mutate(rateForm)}
                >
                  {createRatePlan.isPending ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : null}
                  Create Plan
                </Button>
              </DialogFooter>
            </DialogContent>
          </Dialog>
        </div>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>Configured Rate Plans</CardTitle>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="flex justify-center p-8"><Loader2 className="animate-spin text-muted-foreground" /></div>
          ) : (
            <div className="rounded-md border">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Rate Plan Name</TableHead>
                    <TableHead>Base Price</TableHead>
                    <TableHead>Dynamic Engine</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead className="text-right">Actions</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {ratePlans?.map((plan: any) => (
                    <TableRow key={plan.id}>
                      <TableCell className="font-bold">{plan.name}</TableCell>
                      <TableCell>{plan.currency} {plan.base_price}</TableCell>
                      <TableCell>
                        {plan.is_dynamic ? (
                          <Badge variant="default" className="bg-blue-500 hover:bg-blue-600">Enabled</Badge>
                        ) : (
                          <Badge variant="secondary">Disabled</Badge>
                        )}
                      </TableCell>
                      <TableCell>
                        <Badge variant={plan.status === 'active' ? 'outline' : 'secondary'}>
                          {plan.status.toUpperCase()}
                        </Badge>
                      </TableCell>
                      <TableCell className="text-right">
                        <Button variant="ghost" size="sm">Edit</Button>
                        <Button variant="ghost" size="sm" className="text-destructive">Archive</Button>
                      </TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
