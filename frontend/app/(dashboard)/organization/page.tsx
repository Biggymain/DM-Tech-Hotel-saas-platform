'use client';

import React, { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { EmptyState } from '@/components/ui/empty-state';
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogTrigger,
} from '@/components/ui/dialog';
import {
  Building2, PlusIcon, MapPin, Users, BedDouble,
  Globe, CheckCircle, XCircle, Loader2, BarChart3,
} from 'lucide-react';
import { useAuth } from '@/context/AuthProvider';
import api from '@/lib/api';
import { toast } from 'sonner';

interface Branch {
  id: number;
  name: string;
  email?: string;
  address?: string;
  is_active: boolean;
  rooms_count?: number;
  users_count?: number;
  domain?: string;
  slug?: string;
  group?: {
    id: number;
    name: string;
  };
}

interface BranchFormData {
  name: string;
  email: string;
  phone: string;
  address: string;
}

export default function OrganizationPage() {
  const { user } = useAuth();
  const queryClient = useQueryClient();
  const [open, setOpen] = useState(false);
  const [form, setForm] = useState<BranchFormData>({ name: '', email: '', phone: '', address: '' });

  // Only GROUP_ADMIN and SUPER_ADMIN can access this page
  const canAccess = user?.is_super_admin || !!user?.hotel_group_id;

  const { data: overview } = useQuery({
    queryKey: ['org-overview'],
    queryFn: async () => (await api.get('/api/v1/organization/overview')).data,
    enabled: canAccess,
  });

  const { data: branches = [], isLoading } = useQuery<Branch[]>({
    queryKey: ['org-branches'],
    queryFn: async () => {
      const { data } = await api.get('/api/v1/organization/branches');
      return data.data ?? [];
    },
    enabled: canAccess,
  });

  const createBranch = useMutation({
    mutationFn: (data: BranchFormData) => api.post('/api/v1/organization/branches', data),
    onSuccess: () => {
      toast.success('Branch created successfully!');
      queryClient.invalidateQueries({ queryKey: ['org-branches'] });
      setOpen(false);
      setForm({ name: '', email: '', phone: '', address: '' });
    },
    onError: (err: any) => {
      toast.error(err?.response?.data?.message ?? 'Failed to create branch.');
    },
  });

  if (!canAccess) {
    return (
      <div className="flex flex-col items-center justify-center h-full py-24 text-center text-muted-foreground">
        <XCircle className="h-12 w-12 mb-4 text-destructive/50" />
        <p className="font-semibold text-lg">Access Restricted</p>
        <p className="text-sm">Only Group Admins and Super Admins can view the Organization tab.</p>
      </div>
    );
  }

  return (
    <div className="space-y-8 max-w-7xl">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
        <div>
          <h2 className="text-3xl font-bold tracking-tight">Organization Management</h2>
          <p className="text-muted-foreground">
            {overview?.group?.name ?? 'Your Hotel Group'} · Manage all hotel branches from one place.
          </p>
        </div>
        <Dialog open={open} onOpenChange={setOpen}>
          <DialogTrigger
            nativeButton={true}
            render={
              <Button className="gap-2 shadow-lg shadow-primary/20">
                <PlusIcon className="h-4 w-4" />
                Add New Branch
              </Button>
            }
          />
          <DialogContent className="sm:max-w-md">
            <DialogHeader>
              <DialogTitle>Create a New Hotel Branch</DialogTitle>
            </DialogHeader>
            <div className="space-y-4 py-4">
              {(['name', 'email', 'phone', 'address'] as const).map((field) => (
                <div key={field} className="space-y-1.5">
                  <label className="text-sm font-medium capitalize">{field}</label>
                  <Input
                    placeholder={field === 'name' ? 'Royal Spring - Lagos' : field === 'email' ? 'lagos@hotel.com' : ''}
                    value={form[field]}
                    onChange={(e) => setForm((f) => ({ ...f, [field]: e.target.value }))}
                  />
                </div>
              ))}
            </div>
            <DialogFooter>
              <Button variant="outline" onClick={() => setOpen(false)}>Cancel</Button>
              <Button
                disabled={!form.name || createBranch.isPending}
                onClick={() => createBranch.mutate(form)}
              >
                {createBranch.isPending ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : null}
                Create Branch
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      </div>

      {/* Overview Cards */}
      <div className="grid gap-4 sm:grid-cols-3">
        <Card className="bg-gradient-to-br from-primary/5 to-primary/10 border-primary/20">
          <CardHeader className="pb-2 flex flex-row items-center justify-between">
            <CardTitle className="text-sm font-medium">Total Branches</CardTitle>
            <Building2 className="h-4 w-4 text-primary" />
          </CardHeader>
          <CardContent>
            <div className="text-3xl font-bold">{overview?.branch_count ?? branches.length}</div>
            <p className="text-xs text-muted-foreground">Active hotel properties</p>
          </CardContent>
        </Card>
        <Card className="bg-gradient-to-br from-emerald-500/5 to-emerald-500/10 border-emerald-500/20">
          <CardHeader className="pb-2 flex flex-row items-center justify-between">
            <CardTitle className="text-sm font-medium">Group Status</CardTitle>
            <CheckCircle className="h-4 w-4 text-emerald-500" />
          </CardHeader>
          <CardContent>
            <div className="text-3xl font-bold text-emerald-600">Active</div>
            <p className="text-xs text-muted-foreground">All systems operational</p>
          </CardContent>
        </Card>
        <Card className="bg-gradient-to-br from-violet-500/5 to-violet-500/10 border-violet-500/20">
          <CardHeader className="pb-2 flex flex-row items-center justify-between">
            <CardTitle className="text-sm font-medium">Group Currency</CardTitle>
            <BarChart3 className="h-4 w-4 text-violet-500" />
          </CardHeader>
          <CardContent>
            <div className="text-3xl font-bold text-violet-600">{overview?.group?.currency ?? 'USD'}</div>
            <p className="text-xs text-muted-foreground">Tax rate: {overview?.group?.tax_rate ?? 0}%</p>
          </CardContent>
        </Card>
      </div>

      {/* Branch Table */}
      <Card>
        <CardHeader>
          <CardTitle>Hotel Branches</CardTitle>
          <CardDescription>All properties within your organization. Click a branch to manage it.</CardDescription>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="flex justify-center py-12">
              <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
            </div>
          ) : branches.length === 0 ? (
            <EmptyState
              icon={Building2}
              title="No Branches Yet"
              description="Create your first hotel branch to start managing properties under this organization."
              actionLabel="Add First Branch"
              onAction={() => setOpen(true)}
            />
          ) : (
            <div className="space-y-8">
              {Object.entries(
                branches.reduce((acc, branch) => {
                  const groupName = branch.group?.name || 'Independent Branches';
                  if (!acc[groupName]) acc[groupName] = [];
                  acc[groupName].push(branch);
                  return acc;
                }, {} as Record<string, Branch[]>)
              ).map(([groupName, groupBranches]) => (
                <div key={groupName} className="space-y-3">
                  <div className="flex items-center gap-2 px-1">
                    <Badge variant="secondary" className="rounded-md px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider bg-primary/10 text-primary border-primary/20">
                      {groupName}
                    </Badge>
                    <div className="h-px flex-1 bg-border/60" />
                  </div>
                  
                  <div className="rounded-xl border overflow-hidden bg-card/50 backdrop-blur-sm shadow-sm">
                    <table className="w-full text-sm">
                      <thead className="bg-muted/30">
                        <tr>
                          <th className="py-3 px-4 text-left font-medium text-muted-foreground">Branch Name</th>
                          <th className="py-3 px-4 text-left font-medium text-muted-foreground hidden md:table-cell">Location</th>
                          <th className="py-3 px-4 text-left font-medium text-muted-foreground hidden lg:table-cell">Rooms</th>
                          <th className="py-3 px-4 text-left font-medium text-muted-foreground hidden lg:table-cell">Staff</th>
                          <th className="py-3 px-4 text-left font-medium text-muted-foreground">Status</th>
                          <th className="py-3 px-4 text-right font-medium text-muted-foreground">Actions</th>
                        </tr>
                      </thead>
                      <tbody>
                        {groupBranches.map((branch) => (
                          <tr key={branch.id} className="border-t hover:bg-muted/20 transition-colors group">
                            <td className="py-3 px-4">
                              <div className="flex items-center gap-3">
                                <div className="h-8 w-8 rounded-lg bg-primary/10 flex items-center justify-center group-hover:bg-primary/20 transition-colors">
                                  <Building2 className="h-4 w-4 text-primary" />
                                </div>
                                <div>
                                  <div className="font-semibold">{branch.name}</div>
                                  <div className="text-[10px] text-muted-foreground font-mono uppercase tracking-tighter opacity-70">{branch.domain}</div>
                                </div>
                              </div>
                            </td>
                            <td className="py-3 px-4 hidden md:table-cell">
                              <div className="flex items-center gap-1.5 text-muted-foreground">
                                <MapPin className="h-3.5 w-3.5 opacity-50" />
                                <span className="text-xs">{branch.address ?? '—'}</span>
                              </div>
                            </td>
                            <td className="py-3 px-4 hidden lg:table-cell">
                              <div className="flex items-center gap-1.5">
                                <BedDouble className="h-3.5 w-3.5 text-muted-foreground opacity-50" />
                                <span>{branch.rooms_count ?? '—'}</span>
                              </div>
                            </td>
                            <td className="py-3 px-4 hidden lg:table-cell">
                              <div className="flex items-center gap-1.5">
                                <Users className="h-3.5 w-3.5 text-muted-foreground opacity-50" />
                                <span>{branch.users_count ?? '—'}</span>
                              </div>
                            </td>
                            <td className="py-3 px-4">
                              <Badge variant={branch.is_active ? 'outline' : 'destructive'} className={branch.is_active ? 'border-emerald-500/30 text-emerald-600 bg-emerald-50/50' : ''}>
                                {branch.is_active ? 'Active' : 'Inactive'}
                              </Badge>
                            </td>
                            <td className="py-3 px-4 text-right">
                              <Button 
                                variant="ghost" 
                                size="sm" 
                                className="gap-1.5 hover:bg-primary hover:text-primary-foreground group/btn"
                                onClick={async () => {
                                  try {
                                    localStorage.setItem('hotel_context', branch.id.toString());
                                    toast.success(`Switched to ${branch.name}`);
                                    
                                    // Verify or Create manager
                                    const { data } = await api.post(`/api/v1/organization/branches/${branch.id}/onboard`);
                                    const slug = data.branch_slug || branch.slug || branch.id.toString();
                                    
                                    if (data.temporary_password) {
                                      toast.info(`Manager Onboarded: ${data.manager_email}`, {
                                        description: `Temp Password: ${data.temporary_password}`,
                                        duration: 10000,
                                      });
                                    }
                                    
                                    window.location.href = `/branch/${slug}/dashboard`;
                                  } catch (error) {
                                    toast.error('Failed to switch context.');
                                    window.location.href = `/branch/${branch.slug || branch.id}/dashboard`;
                                  }
                                }}
                              >
                                <Globe className="h-3.5 w-3.5 group-hover/btn:animate-pulse" />
                                Manage
                              </Button>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </div>
              ))}
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}
