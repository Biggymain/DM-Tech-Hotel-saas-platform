'use client';

import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Building2, Loader2, Mail, ArrowLeft, CheckCircle2 } from 'lucide-react';
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

const forgotPasswordSchema = z.object({
  email: z.string().email('Please enter a valid email address'),
});

type ForgotPasswordValues = z.infer<typeof forgotPasswordSchema>;

export default function ForgotPasswordPage() {
  const [loading, setLoading] = useState(false);
  const [submitted, setSubmitted] = useState(false);

  const form = useForm<ForgotPasswordValues>({
    resolver: zodResolver(forgotPasswordSchema),
    defaultValues: {
      email: '',
    },
  });

  const onSubmit = async (data: ForgotPasswordValues) => {
    setLoading(true);

    try {
      await axios.post(`${process.env.NEXT_PUBLIC_API_URL || '/api'}/v1/auth/forgot-password`, data);
      setSubmitted(true);
      toast.success('Reset Link Sent', {
        description: 'If your email exists, check your inbox for instructions.',
      });
    } catch (error: any) {
      toast.error('Submission Failed', {
        description: error.response?.data?.message || 'Something went wrong. Please try again.',
      });
    } finally {
      setLoading(false);
    }
  };

  if (submitted) {
    return (
      <div className="flex min-h-screen items-center justify-center p-4 bg-muted/40 animate-in zoom-in-95 duration-500">
        <div className="w-full max-w-md">
          <Card className="border-border/50 shadow-2xl bg-card/80 backdrop-blur-md text-center">
            <CardHeader className="pt-10 pb-6">
              <div className="mx-auto bg-primary/10 p-4 rounded-full w-20 h-20 flex items-center justify-center mb-4">
                <CheckCircle2 className="w-10 h-10 text-primary animate-bounce-short" />
              </div>
              <CardTitle className="text-2xl font-bold">Check Your Email</CardTitle>
              <CardDescription className="text-base pt-2">
                We've sent a password reset link to <br /><span className="font-semibold text-foreground">{form.getValues('email')}</span>
              </CardDescription>
            </CardHeader>
            <CardContent className="pb-8">
              <p className="text-sm text-muted-foreground">
                Didn't receive the email? Check your spam folder or try again in a few minutes.
              </p>
            </CardContent>
            <CardFooter className="pb-10 pt-0">
              <Link href="/login" className="w-full">
                <Button variant="outline" className="w-full h-11">
                  <ArrowLeft className="mr-2 h-4 w-4" />
                  Back to Sign In
                </Button>
              </Link>
            </CardFooter>
          </Card>
        </div>
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
            <CardTitle className="text-3xl font-bold tracking-tight">Forgot Password?</CardTitle>
            <CardDescription className="text-base text-muted-foreground/80">
              No problem! It happens. Enter your email and we'll send you a reset link.
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
                        <div className="relative group">
                          <Mail className="absolute left-3 top-3 h-4 w-4 text-muted-foreground group-focus-within:text-primary transition-colors" />
                          <Input
                            {...field}
                            type="email"
                            placeholder="admin@hotel.com"
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
                      Sending Link...
                    </>
                  ) : (
                    'Send Reset Link'
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
