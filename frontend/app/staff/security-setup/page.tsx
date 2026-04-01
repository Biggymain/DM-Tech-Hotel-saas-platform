'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { useAuth } from '@/context/AuthProvider';
import api from '@/lib/api';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
  Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle,
} from '@/components/ui/card';
import { ShieldCheck, Eye, EyeOff, Loader2, Lock, KeyRound } from 'lucide-react';
import { toast } from 'sonner';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import * as z from 'zod';
import {
  Form, FormControl, FormField, FormItem, FormLabel, FormMessage, FormDescription
} from '@/components/ui/form';

const setupSchema = z.object({
  password: z.string().min(8, 'Password must be at least 8 characters'),
  password_confirmation: z.string(),
  pin: z.string().length(4, 'PIN must be exactly 4 digits').regex(/^\d+$/, 'PIN must contain only numbers'),
}).refine((data) => data.password === data.password_confirmation, {
  message: "Passwords don't match",
  path: ["password_confirmation"],
});

type SetupFormValues = z.infer<typeof setupSchema>;

export default function SecuritySetupPage() {
  const router = useRouter();
  const { user, checkAuth } = useAuth();
  const [loading, setLoading] = useState(false);
  const [showPassword, setShowPassword] = useState(false);
  const [showConfirmPassword, setShowConfirmPassword] = useState(false);

  const form = useForm<SetupFormValues>({
    resolver: zodResolver(setupSchema),
    defaultValues: { password: '', password_confirmation: '', pin: '' },
  });

  const onSubmit = async (data: SetupFormValues) => {
    setLoading(true);
    try {
      await api.post('/api/v1/auth/staff/setup', {
        password: data.password,
        password_confirmation: data.password_confirmation,
        pin: data.pin,
      });
      
      toast.success('Security Setup Complete', {
        description: 'Your password and PIN have been successfully updated.',
      });
      
      await checkAuth(); // Refresh user state
      
      // Default staff app is the Kitchen Display System or POS depending on their roles.
      // After complete checkAuth, the user's must_change_password flag is false.
      // We can redirect them to standard dashboard/kds routes via the role redirect logic, or explicitly here.
      router.push('/kds'); 
    } catch (error: any) {
      toast.error('Setup Failed', {
        description: error.response?.data?.message || 'An error occurred during security setup.',
      });
      setLoading(false);
    }
  };

  return (
    <div className="flex min-h-screen bg-[#050810] items-center justify-center p-4">
      <div className="fixed top-24 left-1/3 w-80 h-80 rounded-full bg-emerald-600/10 blur-3xl -z-10" />
      <div className="fixed bottom-24 right-1/3 w-64 h-64 rounded-full bg-teal-600/10 blur-3xl -z-10" />

      <div className="w-full max-w-lg relative z-10">
        <div className="flex justify-center mb-8 relative">
          <div className="absolute inset-0 bg-emerald-500/20 blur-2xl rounded-full" />
          <div className="relative bg-gradient-to-br from-emerald-500 to-teal-700 p-5 rounded-3xl shadow-2xl shadow-emerald-500/30 ring-1 ring-emerald-500/50">
            <ShieldCheck className="w-12 h-12 text-white" />
          </div>
        </div>

        <Card className="border-emerald-500/20 shadow-2xl bg-[#0a0f1c]/80 backdrop-blur-2xl text-white">
          <CardHeader className="space-y-2 text-center pb-6 border-b border-white/5">
            <CardTitle className="text-3xl font-bold tracking-tight text-white">Staff Security Setup</CardTitle>
            <CardDescription className="text-emerald-400 font-medium tracking-wide">
              MANDATORY ONBOARDING
            </CardDescription>
            <p className="text-sm text-white/50 px-4 pt-2">
              For your security, please update your temporary password and create a 4-digit POS/KDS PIN to continue.
            </p>
          </CardHeader>

          <Form {...form}>
            <form onSubmit={form.handleSubmit(onSubmit)}>
              <CardContent className="space-y-5 pt-8">
                
                {/* New Password */}
                <FormField control={form.control} name="password" render={({ field }) => (
                  <FormItem>
                    <FormLabel className="text-white/80 font-medium">New Password</FormLabel>
                    <FormControl>
                      <div className="relative group">
                        <Lock className="absolute left-3.5 top-3.5 h-4 w-4 text-emerald-400/50 group-focus-within:text-emerald-400 transition-colors" />
                        <Input
                          {...field} type={showPassword ? 'text' : 'password'} placeholder="••••••••"
                          className="pl-11 pr-11 h-12 bg-black/40 border-white/10 text-white placeholder:text-white/20 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/50 rounded-xl"
                          disabled={loading}
                        />
                        <button
                          type="button"
                          onClick={() => setShowPassword(v => !v)}
                          className="absolute right-3.5 top-3.5 text-white/30 hover:text-white/70 transition-colors"
                          tabIndex={-1}
                        >
                          {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                        </button>
                      </div>
                    </FormControl>
                    <FormMessage className="text-red-400" />
                  </FormItem>
                )} />

                {/* Confirm Password */}
                <FormField control={form.control} name="password_confirmation" render={({ field }) => (
                  <FormItem>
                    <FormLabel className="text-white/80 font-medium">Confirm New Password</FormLabel>
                    <FormControl>
                      <div className="relative group">
                        <Lock className="absolute left-3.5 top-3.5 h-4 w-4 text-emerald-400/50 group-focus-within:text-emerald-400 transition-colors" />
                        <Input
                          {...field} type={showConfirmPassword ? 'text' : 'password'} placeholder="••••••••"
                          className="pl-11 pr-11 h-12 bg-black/40 border-white/10 text-white placeholder:text-white/20 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/50 rounded-xl"
                          disabled={loading}
                        />
                        <button
                          type="button"
                          onClick={() => setShowConfirmPassword(v => !v)}
                          className="absolute right-3.5 top-3.5 text-white/30 hover:text-white/70 transition-colors"
                          tabIndex={-1}
                        >
                          {showConfirmPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                        </button>
                      </div>
                    </FormControl>
                    <FormMessage className="text-red-400" />
                  </FormItem>
                )} />

                {/* Secure PIN */}
                <FormField control={form.control} name="pin" render={({ field }) => (
                  <FormItem>
                    <FormLabel className="text-white/80 font-medium">4-Digit Security PIN</FormLabel>
                    <FormControl>
                      <div className="relative group">
                        <KeyRound className="absolute left-3.5 top-3.5 h-4 w-4 text-emerald-400/50 group-focus-within:text-emerald-400 transition-colors" />
                        <Input
                          {...field} type="password" placeholder="0000" maxLength={4}
                          className="pl-11 h-12 bg-black/40 border-white/10 text-white font-mono text-xl tracking-widest placeholder:text-white/10 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/50 rounded-xl"
                          disabled={loading}
                          onChange={(e) => {
                            const val = e.target.value.replace(/\D/g, '');
                            field.onChange(val);
                          }}
                        />
                      </div>
                    </FormControl>
                    <FormDescription className="text-white/40 text-xs mt-1">
                      This PIN will be used for rapid login to Staff Terminals and KDS systems.
                    </FormDescription>
                    <FormMessage className="text-red-400" />
                  </FormItem>
                )} />

              </CardContent>

              <CardFooter className="flex flex-col gap-4 pb-8 pt-4">
                <Button
                  type="submit"
                  size="lg"
                  className="w-full h-12 font-bold bg-gradient-to-r from-emerald-600 to-teal-500 hover:from-emerald-500 hover:to-teal-400 shadow-[0_0_30px_-5px_#10b981] text-white transition-all duration-300 rounded-xl border border-emerald-400/20"
                  disabled={loading}
                >
                  {loading ? (
                    <><Loader2 className="mr-2 h-5 w-5 animate-spin" /> Securing Account…</>
                  ) : (
                    'Complete Setup & Enter Platform'
                  )}
                </Button>
              </CardFooter>
            </form>
          </Form>
        </Card>
      </div>
    </div>
  );
}
