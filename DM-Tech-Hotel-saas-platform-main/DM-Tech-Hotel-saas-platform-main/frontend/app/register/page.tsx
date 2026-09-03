'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
  Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle,
} from '@/components/ui/card';
import { Building2, Eye, EyeOff, Loader2, Lock, Mail, User, Hotel } from 'lucide-react';
import { toast } from 'sonner';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import * as z from 'zod';
import {
  Form, FormControl, FormField, FormItem, FormLabel, FormMessage,
} from '@/components/ui/form';
import Link from 'next/link';
import axios from 'axios';
import api from '@/lib/api';
import { useAuth } from '@/context/AuthProvider';

// ── Strong password validation ────────────────────────────────────────────────
// Requires: 8+ chars, uppercase, lowercase, number, and a special symbol
const strongPasswordSchema = z
  .string()
  .min(8, 'At least 8 characters required')
  .regex(/[A-Z]/, 'Must include an uppercase letter (A-Z)')
  .regex(/[a-z]/, 'Must include a lowercase letter (a-z)')
  .regex(/[0-9]/, 'Must include a number (0-9)')
  .regex(/[^A-Za-z0-9]/, 'Must include a symbol (e.g. @, #, !, $)');

const registerSchema = z.object({
  group_name: z.string().min(2, 'Organization name must be at least 2 characters'),
  hotel_name: z.string().min(2, 'Hotel name must be at least 2 characters'),
  owner_name: z.string().min(2, 'Your name must be at least 2 characters'),
  email:      z.string().email('Enter a valid email address'),
  password:   strongPasswordSchema,
  password_confirmation: z.string(),
}).refine((d) => d.password === d.password_confirmation, {
  message: "Passwords don't match",
  path: ['password_confirmation'],
});

type RegisterForm = z.infer<typeof registerSchema>;

export default function RegisterPage() {
  const [loading, setLoading]         = useState(false);
  const [showPassword, setShowPassword] = useState(false);
  const [showConfirm, setShowConfirm]  = useState(false);
  const router  = useRouter();
  const { login } = useAuth() as any;

  const form = useForm<RegisterForm>({
    resolver: zodResolver(registerSchema),
    defaultValues: {
      group_name: '', hotel_name: '', owner_name: '', email: '', password: '', password_confirmation: '',
    },
  });

  const onSubmit = async (data: RegisterForm) => {
    setLoading(true);
    try {
      const res = await api.post(
        '/api/v1/auth/register-group',
        data,
      );
      if (res.data.token && login) {
        await login({ token: res.data.token, user: res.data.user });
      }
      toast.success('Organization created!', {
        description: `Welcome to ${data.group_name}. Set up your first branch now.`,
      });
      router.push('/group');
    } catch (err: any) {
      const errorMsg = err?.response?.data?.message ?? 'Something went wrong.';
      const validationErrors = err?.response?.data?.errors;

      if (validationErrors) {
        // Map backend validation errors to react-hook-form fields
        Object.keys(validationErrors).forEach((key) => {
          form.setError(key as any, {
            type: 'manual',
            message: validationErrors[key][0],
          });
        });

        const firstErrorKey = Object.keys(validationErrors)[0];
        const description = validationErrors[firstErrorKey][0];
        toast.error('Registration failed', { description });
      } else {
        toast.error('Registration failed', { description: errorMsg });
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="flex min-h-screen bg-[#050810] items-center justify-center p-4 py-12">
      <div className="fixed top-20 left-1/3 w-96 h-96 rounded-full bg-primary/8 blur-3xl -z-10" />
      <div className="fixed bottom-20 right-1/3 w-80 h-80 rounded-full bg-violet-600/8 blur-3xl -z-10" />

      <div className="w-full max-w-xl">
        {/* Logo */}
        <div className="flex justify-center mb-8">
          <div className="bg-gradient-to-br from-primary to-violet-600 p-4 rounded-2xl shadow-2xl shadow-primary/30 ring-8 ring-primary/10 hover:scale-105 transition-transform duration-300">
            <Building2 className="w-12 h-12 text-white" />
          </div>
        </div>

        <Card className="border-white/10 shadow-2xl bg-white/[0.03] backdrop-blur-xl text-white">
          <CardHeader className="space-y-2 text-center">
            <CardTitle className="text-3xl font-bold tracking-tight text-white">
              Start Your Organization
            </CardTitle>
            <CardDescription className="text-white/40">
              Create your hotel group and first property in under 2 minutes.
            </CardDescription>
          </CardHeader>

          <Form {...form}>
            <form onSubmit={form.handleSubmit(onSubmit)}>
              <CardContent className="space-y-5 pt-2">
                {/* Organization name */}
                <FormField control={form.control} name="group_name" render={({ field }) => (
                  <FormItem>
                    <FormLabel className="text-white/70">Organization / Group Name</FormLabel>
                    <FormControl>
                      <div className="relative">
                        <Building2 className="absolute left-3 top-3 h-4 w-4 text-white/30" />
                        <Input {...field} placeholder="DM Tech Hotels Group"
                          className="pl-10 h-11 bg-white/5 border-white/10 text-white placeholder:text-white/20 focus:border-primary"
                          disabled={loading} />
                      </div>
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )} />

                {/* First hotel name */}
                <FormField control={form.control} name="hotel_name" render={({ field }) => (
                  <FormItem>
                    <FormLabel className="text-white/70">First Hotel / Branch Name</FormLabel>
                    <FormControl>
                      <div className="relative">
                        <Hotel className="absolute left-3 top-3 h-4 w-4 text-white/30" />
                        <Input {...field} placeholder="Royal Spring Hotel – Lagos"
                          className="pl-10 h-11 bg-white/5 border-white/10 text-white placeholder:text-white/20 focus:border-primary"
                          disabled={loading} />
                      </div>
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )} />

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <FormField control={form.control} name="owner_name" render={({ field }) => (
                    <FormItem>
                      <FormLabel className="text-white/70">Your Full Name</FormLabel>
                      <FormControl>
                        <div className="relative">
                          <User className="absolute left-3 top-3 h-4 w-4 text-white/30" />
                          <Input {...field} placeholder="John Olawale"
                            className="pl-10 h-11 bg-white/5 border-white/10 text-white placeholder:text-white/20 focus:border-primary"
                            disabled={loading} />
                        </div>
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )} />
                  <FormField control={form.control} name="email" render={({ field }) => (
                    <FormItem>
                      <FormLabel className="text-white/70">Email Address</FormLabel>
                      <FormControl>
                        <div className="relative">
                          <Mail className="absolute left-3 top-3 h-4 w-4 text-white/30" />
                          <Input {...field} type="email" placeholder="ceo@myhotels.com"
                            className="pl-10 h-11 bg-white/5 border-white/10 text-white placeholder:text-white/20 focus:border-primary"
                            disabled={loading} />
                        </div>
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )} />
                </div>

                {/* Password strength hint */}
                <p className="text-[11px] text-white/25 -mt-1">
                  Password must be 8+ characters with uppercase, lowercase, number, and a symbol.
                </p>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  {/* Password with show/hide */}
                  <FormField control={form.control} name="password" render={({ field }) => (
                    <FormItem>
                      <FormLabel className="text-white/70">Password</FormLabel>
                      <FormControl>
                        <div className="relative group">
                          <Lock className="absolute left-3 top-3 h-4 w-4 text-white/30 group-focus-within:text-primary transition-colors" />
                          <Input {...field} type={showPassword ? 'text' : 'password'} placeholder="••••••••"
                            className="pl-10 pr-11 h-11 bg-white/5 border-white/10 text-white placeholder:text-white/20 focus:border-primary"
                            disabled={loading} />
                          <button type="button" tabIndex={-1}
                            onClick={() => setShowPassword(v => !v)}
                            className="absolute right-3 top-3 text-white/30 hover:text-white/70 transition-colors"
                            aria-label={showPassword ? 'Hide password' : 'Show password'}>
                            {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                          </button>
                        </div>
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )} />

                  {/* Confirm password with show/hide */}
                  <FormField control={form.control} name="password_confirmation" render={({ field }) => (
                    <FormItem>
                      <FormLabel className="text-white/70">Confirm Password</FormLabel>
                      <FormControl>
                        <div className="relative group">
                          <Lock className="absolute left-3 top-3 h-4 w-4 text-white/30 group-focus-within:text-primary transition-colors" />
                          <Input {...field} type={showConfirm ? 'text' : 'password'} placeholder="••••••••"
                            className="pl-10 pr-11 h-11 bg-white/5 border-white/10 text-white placeholder:text-white/20 focus:border-primary"
                            disabled={loading} />
                          <button type="button" tabIndex={-1}
                            onClick={() => setShowConfirm(v => !v)}
                            className="absolute right-3 top-3 text-white/30 hover:text-white/70 transition-colors"
                            aria-label={showConfirm ? 'Hide password' : 'Show password'}>
                            {showConfirm ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                          </button>
                        </div>
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )} />
                </div>
              </CardContent>

              <CardFooter className="flex flex-col gap-4 pb-8">
                <Button
                  type="submit"
                  className="w-full h-12 font-bold bg-gradient-to-r from-primary to-violet-600 hover:from-primary/90 hover:to-violet-500 shadow-xl shadow-primary/20 transition-all"
                  disabled={loading}
                >
                  {loading
                    ? <><Loader2 className="mr-2 h-5 w-5 animate-spin" /> Creating Organization…</>
                    : 'Start Your Organization →'}
                </Button>
                <p className="text-center text-sm text-white/30">
                  Already registered?{' '}
                  <Link href="/login" className="text-primary hover:text-primary/80 font-medium underline underline-offset-4">
                    Sign in here
                  </Link>
                </p>
              </CardFooter>
            </form>
          </Form>
        </Card>
      </div>
    </div>
  );
}
