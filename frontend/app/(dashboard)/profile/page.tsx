'use client';

import * as React from 'react';
import { useSearchParams, useRouter } from 'next/navigation';
import { useAuth } from '@/context/AuthProvider';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ShieldCheck, Loader2, Key, AlertCircle } from 'lucide-react';
import api from '@/lib/api';
import { toast } from 'sonner';

function ProfileContent() {
  const { user, checkAuth } = useAuth();
  const searchParams = useSearchParams();
  const router = useRouter();
  const forceChange = searchParams.get('force_password_change') === 'true';
  
  const [loading, setLoading] = React.useState(false);
  const [form, setForm] = React.useState({
    current_password: '',
    new_password: '',
    new_password_confirmation: '',
  });

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (form.new_password !== form.new_password_confirmation) {
      toast.error('New passwords do not match');
      return;
    }

    setLoading(true);
    try {
      await api.post('/api/v1/profile/change-password', form);
      toast.success('Password updated successfully!');
      
      // Refresh user state to clear must_change_password flag
      await checkAuth();
      
      // Redirect to home or dashboard
      router.push('/');
    } catch (error: any) {
      toast.error(error.response?.data?.message || 'Failed to update password');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="max-w-2xl mx-auto py-10 px-4">
      {forceChange && (
        <div className="mb-6 border-2 border-destructive bg-destructive/10 text-destructive p-4 rounded-xl flex gap-3 items-center animate-pulse">
          <AlertCircle className="h-5 w-5 flex-shrink-0" />
          <div>
            <h4 className="font-bold">Security Action Required</h4>
            <p className="text-sm">This is your first login with a default password. You must change your password to proceed.</p>
          </div>
        </div>
      )}

      <Card className="shadow-2xl border-primary/10">
        <CardHeader className="text-center">
          <div className="mx-auto w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center mb-4">
            <Key className="h-8 w-8 text-primary" />
          </div>
          <CardTitle className="text-2xl">Security Settings</CardTitle>
          <CardDescription>
            Manage your account credentials for {user?.name}
          </CardDescription>
        </CardHeader>
        <CardContent>
          <form onSubmit={handleSubmit} className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="current">Current Password</Label>
              <Input 
                id="current"
                type="password" 
                value={form.current_password}
                onChange={e => setForm({...form, current_password: e.target.value})}
                placeholder="Enter current password"
                required
              />
            </div>
            
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label htmlFor="new">New Password</Label>
                <Input 
                  id="new"
                  type="password" 
                  value={form.new_password}
                  onChange={e => setForm({...form, new_password: e.target.value})}
                  placeholder="At least 8 characters"
                  required
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="confirm">Confirm New Password</Label>
                <Input 
                  id="confirm"
                  type="password" 
                  value={form.new_password_confirmation}
                  onChange={e => setForm({...form, new_password_confirmation: e.target.value})}
                  placeholder="Repeat new password"
                  required
                />
              </div>
            </div>

            <Button type="submit" className="w-full mt-6" disabled={loading}>
              {loading && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
              Update & Secure Account
            </Button>
          </form>
        </CardContent>
      </Card>

      <div className="mt-8 text-center text-sm text-muted-foreground">
        Signed in as <span className="font-semibold text-foreground">{user?.email}</span>
      </div>
    </div>
  );
}

export default function ProfilePage() {
  return (
    <React.Suspense fallback={<div className="flex justify-center py-20"><Loader2 className="animate-spin h-8 w-8 text-primary" /></div>}>
      <ProfileContent />
    </React.Suspense>
  );
}
