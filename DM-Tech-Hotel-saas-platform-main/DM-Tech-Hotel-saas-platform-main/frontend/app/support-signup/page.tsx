'use client';

import { useState } from 'react';
import { useRouter } from 'next/navigation';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
  Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle,
} from '@/components/ui/card';
import { ShieldAlert, Loader2, Lock, Mail, User } from 'lucide-react';
import { toast } from 'sonner';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import * as z from 'zod';
import {
  Form, FormControl, FormField, FormItem, FormLabel, FormMessage,
} from '@/components/ui/form';
import Link from 'next/link';
import api from '@/lib/api';

const signupSchema = z.object({
  name: z.string().min(2, 'Name must be at least 2 characters'),
  email: z.string().email('Enter a valid email address'),
  password: z.string().min(8, 'Password must be at least 8 characters'),
  password_confirmation: z.string(),
}).refine((d) => d.password === d.password_confirmation, {
  message: "Passwords don't match",
  path: ['password_confirmation'],
});

type SignupFormValues = z.infer<typeof signupSchema>;

export default function SupportSignupPage() {
  const [loading, setLoading] = useState(false);
  const router = useRouter();

  const form = useForm<SignupFormValues>({
    resolver: zodResolver(signupSchema),
    defaultValues: {
      name: '',
      email: '',
      password: '',
      password_confirmation: '',
    },
  });

  const onSubmit = async (data: SignupFormValues) => {
    setLoading(true);
    try {
      await api.post('/api/v1/auth/support-signup', data);
      
      toast.success('Access Request Submitted', {
        description: 'Your account is pending approval by the Super Admin.',
      });
      
      router.push('/login');
    } catch (err: any) {
      toast.error('Submission Failed', {
        description: err.response?.data?.message || 'Something went wrong. Please try again.',
      });
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="flex min-h-screen bg-[#050810] items-center justify-center p-4">
      <div className="fixed top-24 left-1/3 w-80 h-80 rounded-full bg-primary/8 blur-3xl -z-10" />
      
      <div className="w-full max-w-md">
        <div className="flex justify-center mb-8">
          <div className="bg-gradient-to-br from-primary to-orange-500 p-4 rounded-2xl shadow-2xl shadow-primary/30 ring-8 ring-primary/10">
            <ShieldAlert className="w-10 h-10 text-white" />
          </div>
        </div>

        <Card className="border-white/10 shadow-2xl bg-white/[0.03] backdrop-blur-xl text-white">
          <CardHeader className="space-y-1 text-center">
            <CardTitle className="text-3xl font-bold tracking-tight">Support Access</CardTitle>
            <CardDescription className="text-white/40">
              Apply for DM-Tech official support staff credentials.
            </CardDescription>
          </CardHeader>

          <Form {...form}>
            <form onSubmit={form.handleSubmit(onSubmit)}>
              <CardContent className="space-y-4 pt-4">
                <FormField control={form.control} name="name" render={({ field }) => (
                  <FormItem>
                    <FormLabel className="text-white/70">Full Name</FormLabel>
                    <FormControl>
                      <div className="relative">
                        <User className="absolute left-3 top-3 h-4 w-4 text-white/30" />
                        <Input {...field} placeholder="Support Agent Name" 
                          className="pl-10 h-11 bg-white/5 border-white/10 text-white placeholder:text-white/20 focus:border-primary"
                          disabled={loading} />
                      </div>
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )} />

                <FormField control={form.control} name="email" render={({ field }) => (
                  <FormItem>
                    <FormLabel className="text-white/70">Official Email</FormLabel>
                    <FormControl>
                      <div className="relative">
                        <Mail className="absolute left-3 top-3 h-4 w-4 text-white/30" />
                        <Input {...field} type="email" placeholder="agent@dmtech.ng" 
                          className="pl-10 h-11 bg-white/5 border-white/10 text-white placeholder:text-white/20 focus:border-primary"
                          disabled={loading} />
                      </div>
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )} />

                <div className="grid grid-cols-1 gap-4">
                  <FormField control={form.control} name="password" render={({ field }) => (
                    <FormItem>
                      <FormLabel className="text-white/70">Password</FormLabel>
                      <FormControl>
                        <div className="relative">
                          <Lock className="absolute left-3 top-3 h-4 w-4 text-white/30" />
                          <Input {...field} type="password" placeholder="••••••••" 
                            className="pl-10 h-11 bg-white/5 border-white/10 text-white placeholder:text-white/20 focus:border-primary"
                            disabled={loading} />
                        </div>
                      </FormControl>
                      <FormMessage />
                    </FormItem>
                  )} />

                  <FormField control={form.control} name="password_confirmation" render={({ field }) => (
                    <FormItem>
                      <FormLabel className="text-white/70">Confirm Password</FormLabel>
                      <FormControl>
                        <div className="relative">
                          <Lock className="absolute left-3 top-3 h-4 w-4 text-white/30" />
                          <Input {...field} type="password" placeholder="••••••••" 
                            className="pl-10 h-11 bg-white/5 border-white/10 text-white placeholder:text-white/20 focus:border-primary"
                            disabled={loading} />
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
                  className="w-full h-11 font-bold bg-gradient-to-r from-primary to-orange-500 hover:from-primary/90 hover:to-orange-400 shadow-xl shadow-primary/20 transition-all"
                  disabled={loading}
                >
                  {loading ? (
                    <><Loader2 className="mr-2 h-5 w-5 animate-spin" /> Submitting Request…</>
                  ) : 'Request Access →'}
                </Button>
                <p className="text-center text-sm text-white/30">
                  Already requested?{' '}
                  <Link href="/login" className="text-primary font-medium hover:text-primary/80 underline underline-offset-4">
                    Back to login
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
