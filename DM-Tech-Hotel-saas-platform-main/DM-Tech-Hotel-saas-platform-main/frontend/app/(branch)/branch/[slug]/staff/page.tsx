'use client';

import * as React from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { useParams, useSearchParams } from 'next/navigation';
import { 
  Card, CardContent, CardDescription, CardHeader, CardTitle 
} from '@/components/ui/card';
import { 
  Table, TableBody, TableCell, TableHead, TableHeader, TableRow 
} from '@/components/ui/table';
import { 
  Badge 
} from '@/components/ui/badge';
import { 
  Users, UserPlus, Clock, Shield, Utensils, Search, Briefcase, Loader2
} from 'lucide-react';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import api from '@/lib/api';
import { cn } from '@/lib/utils';
import { toast } from 'sonner';
import {
  Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogTrigger,
} from '@/components/ui/dialog';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Label } from '@/components/ui/label';
import { Key } from 'lucide-react';

interface StaffUser {
  id: number;
  name: string;
  email: string;
  role: string | null;
  is_on_duty: boolean;
  last_duty_toggle_at: string | null;
  outlet?: { id: number; name: string } | null;
  roles: { name: string; slug: string }[];
}

function StaffContent() {
  const params = useParams();
  const branchSlug = params.slug as string;
  const queryClient = useQueryClient();
  const [searchTerm, setSearchTerm] = React.useState('');
  const [open, setOpen] = React.useState(false);
  const searchParams = useSearchParams();
  const outletIdParam = searchParams.get('outlet_id');

  const [form, setForm] = React.useState({
    name: '', email: '', role_id: '', outlet_id: outletIdParam || '', password: 'password123'
  });

  // Update form when outletIdParam changes
  React.useEffect(() => {
    if (outletIdParam) {
      setForm(prev => ({ ...prev, outlet_id: outletIdParam }));
    }
  }, [outletIdParam]);

  const { data: roles = [] } = useQuery({
    queryKey: ['available-roles'],
    queryFn: () => api.get('/api/v1/roles').then(res => res.data),
  });

  const { data: outlets = [] } = useQuery({
    queryKey: ['available-outlets'],
    queryFn: () => api.get('/api/v1/outlets').then(res => res.data),
  });

  const onboardingMutation = useMutation({
    mutationFn: (data: any) => api.post('/api/v1/users', data),
    onSuccess: () => {
      toast.success('Staff member onboarded!');
      queryClient.invalidateQueries({ queryKey: ['branch-staff'] });
      setOpen(false);
      setForm({ name: '', email: '', role_id: '', outlet_id: '', password: 'password123' });
    },
    onError: (err: any) => {
      toast.error(err?.response?.data?.message ?? 'Failed to onboard staff.');
    }
  });


  const { data: staff, isLoading } = useQuery<{ data: StaffUser[] }>({
    queryKey: ['branch-staff', branchSlug],
    queryFn: () => api.get(`/api/v1/users`).then(res => res.data),
  });

  const filteredStaff = staff?.data?.filter(member => {
    const matchesSearch = member.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
      member.email.toLowerCase().includes(searchTerm.toLowerCase()) ||
      member.roles.some(r => r.name.toLowerCase().includes(searchTerm.toLowerCase()));
    
    const matchesOutlet = outletIdParam ? member.outlet?.id?.toString() === outletIdParam : true;

    return matchesSearch && matchesOutlet;
  }) || [];

  return (
    <div className="space-y-6 max-w-7xl mx-auto">
      <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h2 className="text-3xl font-bold tracking-tight text-foreground flex items-center gap-2">
            <Users className="h-8 w-8 text-primary" />
            Staff Management
          </h2>
          <p className="text-muted-foreground mt-1">
            Manage roles, duty status, and outlet assignments for this branch.
          </p>
        </div>
        <Dialog open={open} onOpenChange={setOpen}>
          <DialogTrigger render={
            <Button className="gap-2 shadow-lg shadow-primary/20">
              <UserPlus className="h-4 w-4" />
              Add Staff Member
            </Button>
          } />
          <DialogContent className="sm:max-w-[500px]">
            <DialogHeader>
              <DialogTitle>Onboard New Staff Member</DialogTitle>
              <CardDescription>Create credentials and assign a role to your new employee.</CardDescription>
            </DialogHeader>
            <div className="grid gap-4 py-4">
              <div className="grid grid-cols-4 items-center gap-4">
                <Label className="text-right">Name</Label>
                <Input 
                  className="col-span-3"
                  value={form.name}
                  onChange={e => setForm({...form, name: e.target.value})}
                  placeholder="John Doe"
                />
              </div>
              <div className="grid grid-cols-4 items-center gap-4">
                <Label className="text-right">Email</Label>
                <Input 
                  className="col-span-3"
                  value={form.email}
                  onChange={e => setForm({...form, email: e.target.value})}
                  placeholder="john@example.com"
                />
              </div>
              <div className="grid grid-cols-4 items-center gap-4">
                <Label className="text-right">Password</Label>
                <div className="col-span-3 relative">
                   <Input 
                    value={form.password}
                    onChange={e => setForm({...form, password: e.target.value})}
                   />
                   <Key className="absolute right-3 top-2.5 h-4 w-4 text-muted-foreground opacity-50" />
                </div>
              </div>
              <div className="grid grid-cols-4 items-center gap-4">
                <Label className="text-right whitespace-nowrap">Assigned Role</Label>
                <div className="col-span-3">
                  <Select onValueChange={val => setForm({...form, role_id: val ?? ""})} value={form.role_id || ""}>
                    <SelectTrigger>
                      <SelectValue placeholder="Select a role" />
                    </SelectTrigger>
                    <SelectContent>
                      {roles.map((role: any) => (
                        <SelectItem key={role.id} value={String(role.id)}>{role.name}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
              </div>
              <div className="grid grid-cols-4 items-center gap-4">
                <Label className="text-right whitespace-nowrap">Target Outlet</Label>
                <div className="col-span-3">
                  <Select onValueChange={val => setForm({...form, outlet_id: val ?? ""})} value={form.outlet_id || ""}>
                    <SelectTrigger>
                      <SelectValue placeholder="General / All" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="none">General / All</SelectItem>
                      {outlets.map((outlet: any) => (
                        <SelectItem key={outlet.id} value={String(outlet.id)}>{outlet.name}</SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
              </div>
            </div>
            <DialogFooter>
              <Button 
                className="w-full sm:w-auto"
                onClick={() => onboardingMutation.mutate({...form, outlet_id: (form.outlet_id === 'none' || !form.outlet_id) ? null : form.outlet_id})}
                disabled={!form.name || !form.email || !form.role_id || onboardingMutation.isPending}
              >
                {onboardingMutation.isPending && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                Complete Onboarding
              </Button>
            </DialogFooter>
          </DialogContent>
        </Dialog>
      </div>

      <div className="grid gap-6 md:grid-cols-3">
        <StatCard 
          title="Total Staff" 
          value={staff?.data?.length || 0} 
          icon={Users} 
          footer="Active employees" 
        />
        <StatCard 
          title="Currently on Duty" 
          value={staff?.data?.filter(s => s.is_on_duty).length || 0} 
          icon={Clock} 
          footer="Active on shift"
          color="text-emerald-500"
        />
        <StatCard 
          title="Outlets Represented" 
          value={new Set(staff?.data?.map(s => s.outlet?.name).filter(Boolean)).size} 
          icon={Utensils} 
          footer="Functional sections" 
        />
      </div>

      <Card className="shadow-xl bg-card/50 backdrop-blur-sm border-primary/5">
        <CardHeader>
          <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div>
              <CardTitle>Employee Directory</CardTitle>
              <CardDescription>Real-time shift status and role assignments.</CardDescription>
            </div>
            <div className="relative w-full md:w-72">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-muted-foreground" />
              <Input 
                placeholder="Find staff..." 
                className="pl-9 bg-muted/50 focus-visible:ring-primary/20"
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
              />
            </div>
          </div>
        </CardHeader>
        <CardContent>
          <div className="rounded-xl border border-primary/5 overflow-hidden">
            <Table>
              <TableHeader className="bg-muted/50">
                <TableRow>
                  <TableHead className="font-bold">Staff Member</TableHead>
                  <TableHead className="font-bold">Primary Role</TableHead>
                  <TableHead className="font-bold">Outlet / Section</TableHead>
                  <TableHead className="font-bold">Duty Status</TableHead>
                  <TableHead className="text-right font-bold">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {isLoading ? (
                  <TableRow>
                    <TableCell colSpan={5} className="h-24 text-center">
                      <div className="flex items-center justify-center gap-2 text-muted-foreground animate-pulse">
                        <Users className="h-4 w-4 animate-bounce" />
                        Loading staff records...
                      </div>
                    </TableCell>
                  </TableRow>
                ) : filteredStaff.length === 0 ? (
                  <TableRow>
                    <TableCell colSpan={5} className="h-24 text-center text-muted-foreground">
                      No staff members found matching your search.
                    </TableCell>
                  </TableRow>
                ) : (
                  filteredStaff.map((member) => (
                    <TableRow key={member.id} className="group hover:bg-muted/30 transition-colors">
                      <TableCell>
                        <div className="flex items-center gap-3">
                          <div className="h-10 w-10 rounded-full bg-primary/10 flex items-center justify-center font-bold text-primary group-hover:scale-110 transition-transform">
                            {member.name.charAt(0)}
                          </div>
                          <div>
                            <div className="font-semibold text-foreground">{member.name}</div>
                            <div className="text-[10px] text-muted-foreground">{member.email}</div>
                          </div>
                        </div>
                      </TableCell>
                      <TableCell>
                        <div className="flex flex-wrap gap-1">
                          {member.roles.map(role => (
                            <Badge key={role.slug} variant="outline" className="text-[10px] font-bold uppercase tracking-wider border-primary/20 bg-primary/5 text-primary">
                              {role.name}
                            </Badge>
                          ))}
                        </div>
                      </TableCell>
                      <TableCell>
                        <div className="flex items-center gap-2 text-sm">
                          <Briefcase className="h-3.5 w-3.5 text-muted-foreground" />
                          {member.outlet?.name || <span className="text-muted-foreground italic">General / All</span>}
                        </div>
                      </TableCell>
                      <TableCell>
                        <Badge 
                          variant={member.is_on_duty ? "default" : "secondary"}
                          className={cn(
                            "gap-1.5 text-[10px] font-black uppercase tracking-widest",
                            member.is_on_duty ? "bg-emerald-500 hover:bg-emerald-600" : "bg-muted text-muted-foreground"
                          )}
                        >
                          <div className={cn("h-1 w-1 rounded-full", member.is_on_duty ? "bg-white animate-pulse" : "bg-muted-foreground")} />
                          {member.is_on_duty ? "On Duty" : "Off Duty"}
                        </Badge>
                        {member.last_duty_toggle_at && (
                          <div className="text-[9px] text-muted-foreground mt-1 flex items-center gap-1">
                            <Clock className="h-2.5 w-2.5" />
                            {new Date(member.last_duty_toggle_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
                          </div>
                        )}
                      </TableCell>
                      <TableCell className="text-right">
                        <Button 
                          variant="ghost" 
                          size="sm" 
                          className="text-xs font-bold text-primary hover:bg-primary/5"
                          onClick={() => toast.info(`Managing ${member.name}...`)}
                        >
                          Manage
                        </Button>
                      </TableCell>
                    </TableRow>
                  ))
                )}
              </TableBody>
            </Table>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}

export default function BranchStaffPage() {
  return (
    <React.Suspense fallback={<div className="flex justify-center py-20"><Loader2 className="animate-spin h-8 w-8 text-primary" /></div>}>
      <StaffContent />
    </React.Suspense>
  );
}

function StatCard({ title, value, icon: Icon, footer, color = 'text-foreground' }: any) {
  return (
    <Card className="shadow-lg border-primary/5 bg-card/50">
      <CardContent className="p-6">
        <div className="flex items-center justify-between mb-2">
          <p className="text-sm font-medium text-muted-foreground uppercase tracking-wider">{title}</p>
          <div className="h-8 w-8 rounded-lg bg-primary/5 flex items-center justify-center">
            <Icon className="h-4 w-4 text-primary" />
          </div>
        </div>
        <div className={cn("text-3xl font-black tracking-tighter", color)}>{value}</div>
        <p className="text-[10px] text-muted-foreground font-medium mt-1 uppercase tracking-widest">{footer}</p>
      </CardContent>
    </Card>
  );
}
