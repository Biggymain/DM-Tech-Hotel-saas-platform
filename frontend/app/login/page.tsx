'use client';

import { useState } from 'react';
import { useAuth } from '@/context/AuthProvider';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
  Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle,
} from '@/components/ui/card';
import { Building2, Eye, EyeOff, Loader2, Lock, Mail } from 'lucide-react';
import { toast } from 'sonner';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import * as z from 'zod';
import {
  Form, FormControl, FormField, FormItem, FormLabel, FormMessage,
} from '@/components/ui/form';
import Link from 'next/link';

const loginSchema = z.object({
  email:    z.string().email('Please enter a valid email address'),
  password: z.string().min(8, 'Password must be at least 8 characters'),
});

type LoginFormValues = z.infer<typeof loginSchema>;

export default function LoginPage() {
  const { login } = useAuth();
  const [loading, setLoading] = useState(false);
  const [showPassword, setShowPassword] = useState(false);

  const form = useForm<LoginFormValues>({
    resolver: zodResolver(loginSchema),
    defaultValues: { email: '', password: '' },
  });

  const onSubmit = async (data: LoginFormValues) => {
    setLoading(true);
    try {
      await login(data);
    } catch (error: any) {
      toast.error('Authentication Failed', {
        description: error.response?.data?.message || 'Invalid credentials. Please try again.',
      });
      setLoading(false);
    }
  };

  return (
    <div className="flex min-h-screen bg-[#050810] items-center justify-center p-4">
      {/* Background effects */}
      <div className="fixed top-24 left-1/3 w-80 h-80 rounded-full bg-primary/8 blur-3xl -z-10" />
      <div className="fixed bottom-24 right-1/3 w-64 h-64 rounded-full bg-violet-600/8 blur-3xl -z-10" />

      <div className="w-full max-w-md">
        {/* Logo */}
        <div className="flex justify-center mb-8">
          <div className="bg-gradient-to-br from-primary to-violet-600 p-4 rounded-2xl shadow-2xl shadow-primary/30 ring-8 ring-primary/10 hover:scale-105 transition-transform duration-300">
            <Building2 className="w-10 h-10 text-white" />
          </div>
        </div>

        <Card className="border-white/10 shadow-2xl bg-white/[0.03] backdrop-blur-xl text-white">
          <CardHeader className="space-y-2 text-center">
            <CardTitle className="text-3xl font-bold tracking-tight text-white">Admin Portal</CardTitle>
            <CardDescription className="text-white/40">
              Enter your credentials to manage hotel operations
            </CardDescription>
          </CardHeader>

          <Form {...form}>
            <form onSubmit={form.handleSubmit(onSubmit)}>
              <CardContent className="space-y-4 pt-4">
                {/* Email */}
                <FormField control={form.control} name="email" render={({ field }) => (
                  <FormItem>
                    <FormLabel className="text-white/70">Email Address</FormLabel>
                    <FormControl>
                      <div className="relative group">
                        <Mail className="absolute left-3 top-3 h-4 w-4 text-white/30 group-focus-within:text-primary transition-colors" />
                        <Input
                          {...field} type="email" placeholder="admin@hotel.com"
                          className="pl-10 h-11 bg-white/5 border-white/10 text-white placeholder:text-white/20 focus:border-primary pr-4"
                          disabled={loading}
                        />
                      </div>
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )} />

                {/* Password with show/hide toggle */}
                <FormField control={form.control} name="password" render={({ field }) => (
                  <FormItem>
                    <div className="flex items-center justify-between">
                      <FormLabel className="text-white/70">Password</FormLabel>
                      <Link href="/forgot-password" className="text-xs text-primary hover:text-primary/80 underline underline-offset-4">
                        Forgot password?
                      </Link>
                    </div>
                    <FormControl>
                      <div className="relative group">
                        <Lock className="absolute left-3 top-3 h-4 w-4 text-white/30 group-focus-within:text-primary transition-colors" />
                        <Input
                          {...field} type={showPassword ? 'text' : 'password'} placeholder="••••••••"
                          className="pl-10 pr-11 h-11 bg-white/5 border-white/10 text-white placeholder:text-white/20 focus:border-primary"
                          disabled={loading}
                        />
                        <button
                          type="button"
                          onClick={() => setShowPassword(v => !v)}
                          className="absolute right-3 top-3 text-white/30 hover:text-white/70 transition-colors"
                          tabIndex={-1}
                          aria-label={showPassword ? 'Hide password' : 'Show password'}
                        >
                          {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                        </button>
                      </div>
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )} />
              </CardContent>

              <CardFooter className="flex flex-col gap-4 pb-8">
                <Button
                  type="submit"
                  className="w-full h-11 font-bold bg-gradient-to-r from-primary to-violet-600 hover:from-primary/90 hover:to-violet-500 shadow-xl shadow-primary/20 transition-all"
                  disabled={loading}
                >
                  {loading ? (
                    <><Loader2 className="mr-2 h-5 w-5 animate-spin" /> Authenticating…</>
                  ) : 'Sign In'}
                </Button>
                <p className="text-center text-sm text-white/30">
                  Don't have an account?{' '}
                  <Link href="/register" className="text-primary font-medium hover:text-primary/80 underline underline-offset-4">
                    Start your organization
                  </Link>
                </p>
              </CardFooter>
            </form>
          </Form>
        </Card>

        <p className="mt-6 text-center text-xs text-white/20">
          Secure Multi-Tenant SaaS Platform © 2026
        </p>
      </div>
    </div>
  );
}
