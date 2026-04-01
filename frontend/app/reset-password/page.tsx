'use client';

import { useState, useEffect, Suspense } from 'react';
import { useSearchParams, useRouter } from 'next/navigation';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Building2, Loader2, Lock, ArrowLeft, KeyRound } from 'lucide-react';
import { toast } from 'sonner';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import * as z from 'zod';
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from "@/components/ui/form";
import Link from 'next/link';
import axios from 'axios';

const resetPasswordSchema = z.object({
  email: z.string().email('Please enter a valid email address'),
  password: z.string().min(8, 'Password must be at least 8 characters'),
  password_confirmation: z.string().min(8, 'Confirmation required'),
  token: z.string().min(1, 'Token is required'),
}).refine((data) => data.password === data.password_confirmation, {
  message: "Passwords don't match",
  path: ["password_confirmation"],
});

type ResetPasswordValues = z.infer<typeof resetPasswordSchema>;

function ResetPasswordContent() {
  const [loading, setLoading] = useState(false);
  const searchParams = useSearchParams();
  const router = useRouter();

  const form = useForm<ResetPasswordValues>({
    resolver: zodResolver(resetPasswordSchema),
    defaultValues: {
      email: searchParams?.get('email') || '',
      token: searchParams?.get('token') || '',
      password: '',
      password_confirmation: '',
    },
  });

  const onSubmit = async (data: ResetPasswordValues) => {
    setLoading(true);

    try {
      await axios.post(`${process.env.NEXT_PUBLIC_API_URL || '/api'}/v1/auth/reset-password`, data);
      toast.success('Password Reset Successful', {
        description: 'You can now sign in with your new password.',
      });
      setTimeout(() => router.push('/login'), 2000);
    } catch (error: any) {
      toast.error('Reset Failed', {
        description: error.response?.data?.message || 'Invalid token or session expired.',
      });
    } finally {
      setLoading(false);
    }
  };

  if (!searchParams?.get('token')) {
    return (
      <div className="flex min-h-screen items-center justify-center p-4 bg-muted/40">
        <Card className="w-full max-w-md border-destructive/20 shadow-2xl">
          <CardHeader className="text-center">
             <div className="mx-auto bg-destructive/10 p-4 rounded-full w-16 h-16 flex items-center justify-center mb-4">
                <KeyRound className="w-8 h-8 text-destructive" />
              </div>
            <CardTitle className="text-destructive">Invalid Request</CardTitle>
            <CardDescription>
              A valid reset token is required to access this page. Please request a new link if needed.
            </CardDescription>
          </CardHeader>
          <CardFooter>
            <Link href="/forgot-password" title="Forgot Password" className="w-full">
              <Button className="w-full">Request New Link</Button>
            </Link>
          </CardFooter>
        </Card>
      </div>
    );
  }

  return (
    <div className="flex min-h-screen items-center justify-center p-4 bg-muted/40 animate-in fade-in duration-500">
      <div className="w-full max-w-md">
        <div className="flex justify-center mb-8">
          <div className="bg-primary p-4 rounded-2xl text-primary-foreground shadow-2xl ring-4 ring-primary/20">
            <Building2 className="w-10 h-10" />
          </div>
        </div>
        
        <Card className="border-border/50 shadow-2xl bg-card/80 backdrop-blur-md">
          <CardHeader className="space-y-2 text-center">
            <CardTitle className="text-3xl font-bold tracking-tight">Reset Password</CardTitle>
            <CardDescription className="text-base text-muted-foreground/80">
              Create a strong new password for your account.
            </CardDescription>
          </CardHeader>
          <Form {...form}>
            <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
              <CardContent className="space-y-4 pt-4">
                 <FormField
                  control={form.control}
                  name="email"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>Email Address</FormLabel>
                      <FormControl>
                          <Input
                            {...field}
                            type="email"
                            readOnly
                            className="h-11 bg-muted/50 border-border/50 shadow-sm opacity-80 cursor-not-allowed"
                          />
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
                <FormField
                  control={form.control}
                  name="password"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>New Password</FormLabel>
                      <FormControl>
                        <div className="relative group">
                          <Lock className="absolute left-3 top-3 h-4 w-4 text-muted-foreground group-focus-within:text-primary transition-colors" />
                          <Input
                            {...field}
                            type="password"
                            placeholder="••••••••"
                            className="pl-10 h-11 bg-background/50 border-border/50 focus:border-primary transition-all shadow-sm"
                            disabled={loading}
                          />
                        </div>
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
                <FormField
                  control={form.control}
                  name="password_confirmation"
                  render={({ field }) => (
                    <FormItem>
                      <FormLabel>Confirm New Password</FormLabel>
                      <FormControl>
                        <div className="relative group">
                          <Lock className="absolute left-3 top-3 h-4 w-4 text-muted-foreground group-focus-within:text-primary transition-colors" />
                          <Input
                            {...field}
                            type="password"
                            placeholder="••••••••"
                            className="pl-10 h-11 bg-background/50 border-border/50 focus:border-primary transition-all shadow-sm"
                            disabled={loading}
                          />
                        </div>
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )}
                />
              </CardContent>
              <CardFooter className="flex flex-col gap-4 pb-8">
                <Button type="submit" className="w-full h-11 text-base font-semibold shadow-lg shadow-primary/20 hover:shadow-primary/30 transition-all transition-duration-300" disabled={loading}>
                  {loading ? (
                    <>
                      <Loader2 className="mr-2 h-5 w-5 animate-spin" />
                      Saving Password...
                    </>
                  ) : (
                    'Reset Password'
                  )}
                </Button>
                <Link href="/login" className="flex items-center justify-center text-sm font-medium text-muted-foreground hover:text-primary transition-colors">
                  <ArrowLeft className="mr-2 h-4 w-4" />
                  Back to Sign In
                </Link>
              </CardFooter>
            </form>
          </Form>
        </Card>
      </div>
    </div>
  );
}

export default function ResetPasswordPage() {
  return (
    <Suspense fallback={<div className="flex min-h-screen items-center justify-center"><Loader2 className="animate-spin" /></div>}>
      <ResetPasswordContent />
    </Suspense>
  );
}
