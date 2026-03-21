'use client';

import React, { useEffect, useRef } from 'react';
import Link from 'next/link';
import {
  Building2, BrainCircuit, Zap, Globe2, ChevronRight, Star,
  BarChart3, BedDouble, Utensils, Shield, ArrowRight, CheckCircle2
} from 'lucide-react';
import { useRouter } from 'next/navigation';
import { useAuth, useRoleRedirect } from '@/context/AuthProvider';
import api from '@/lib/api';

// ─── Feature Data ────────────────────────────────────────────────────────────
const features = [
  {
    icon: Building2,
    title: 'Multi-Property Logic',
    description: 'Manage an entire hotel group from one dashboard. Branch isolation keeps each property\'s data secure while giving Group Admins full visibility.',
    color: 'from-blue-500/20 to-indigo-500/20',
    border: 'border-blue-500/20',
    iconColor: 'text-blue-400',
  },
  {
    icon: Zap,
    title: 'Atomic POS Engine',
    description: 'Every POS charge and inventory deduction happens inside a single database transaction. Stock never mismatches — even under high load.',
    color: 'from-amber-500/20 to-orange-500/20',
    border: 'border-amber-500/20',
    iconColor: 'text-amber-400',
  },
  {
    icon: BrainCircuit,
    title: 'AI Revenue Intelligence',
    description: 'Machine-learning demand scoring, competitor-aware pricing, and event-detection adjust your room rates automatically — so you never leave revenue on the table.',
    color: 'from-violet-500/20 to-purple-500/20',
    border: 'border-violet-500/20',
    iconColor: 'text-violet-400',
  },
  {
    icon: Shield,
    title: 'Enterprise-Grade Security',
    description: 'Every query is scoped by hotel_id. GROUP_ADMINs see only their branches via a strict whereIn guard. No data leakage, ever.',
    color: 'from-emerald-500/20 to-teal-500/20',
    border: 'border-emerald-500/20',
    iconColor: 'text-emerald-400',
  },
  {
    icon: Globe2,
    title: 'OTA Channel Sync',
    description: 'Real-time availability pushed to Booking.com, Airbnb, and more. Rate plan mapping with automatic conflict resolution.',
    color: 'from-cyan-500/20 to-sky-500/20',
    border: 'border-cyan-500/20',
    iconColor: 'text-cyan-400',
  },
  {
    icon: BarChart3,
    title: 'Night Audit Automation',
    description: 'A scheduled 2 AM task posts nightly room charges with double-post protection. Your accounts are always balanced by morning.',
    color: 'from-rose-500/20 to-pink-500/20',
    border: 'border-rose-500/20',
    iconColor: 'text-rose-400',
  },
];

const stats = [
  { value: '500+', label: 'Properties managed' },
  { value: '₦2.4B', label: 'Revenue processed' },
  { value: '99.98%', label: 'Uptime SLA' },
  { value: '< 50ms', label: 'API response time' },
];

// ─── Animated Folio Preview ───────────────────────────────────────────────────
const folioRows = [
  { date: '11 Mar', desc: 'Room Charge – Suite 401', source: 'ROOM', amount: '₦85,000', status: 'PAID' },
  { date: '11 Mar', desc: 'Restaurant – Dinner Service', source: 'POS', amount: '₦12,500', status: 'PAID' },
  { date: '11 Mar', desc: 'Laundry Service', source: 'LAUNDRY', amount: '₦3,200', status: 'PENDING' },
  { date: '12 Mar', desc: 'Room Charge – Suite 401', source: 'ROOM', amount: '₦85,000', status: 'PAID' },
  { date: '12 Mar', desc: 'Spa Treatment', source: 'POS', amount: '₦25,000', status: 'PAID' },
];

const sourceColor: Record<string, string> = {
  ROOM: 'text-blue-400',
  POS: 'text-orange-400',
  LAUNDRY: 'text-cyan-400',
};

export default function LandingPage() {
  const previewRef = useRef<HTMLDivElement>(null);
  const router = useRouter();
  const { user, isLoading } = useAuth();
  const redirectPath = useRoleRedirect();

  useEffect(() => {
    if (typeof window !== 'undefined' && window.location.port === '3001') {
      // Dynamically find the first active group website to redirect guests
      api.get('/api/v1/booking/group-website')
        .then(res => {
          const slug = res.data?.slug;
          window.location.href = slug ? `/group/${slug}` : '/group/';
        })
        .catch(() => {
          window.location.href = '/group/';
        });
    }
  }, []);

  /* 
  useEffect(() => {
    if (!isLoading && user && redirectPath) {
      console.log('Redirecting to dashboard:', redirectPath);
      router.replace(redirectPath);
    }
  }, [user, isLoading, router, redirectPath]);
  */

  useEffect(() => {
    const el = previewRef.current;
    if (!el) return;
    const observer = new IntersectionObserver(
      ([entry]) => {
        if (entry.isIntersecting) {
          el.classList.add('animate-in-slow');
        }
      },
      { threshold: 0.2 },
    );
    observer.observe(el);
    return () => observer.disconnect();
  }, []);

  return (
    <div className="bg-[#050810] text-white min-h-screen overflow-x-hidden">

      {/* ── NAV ─────────────────────────────────────────────────────────── */}
      <nav className="fixed top-0 left-0 right-0 z-50 flex items-center justify-between px-6 md:px-12 py-4 bg-[#050810]/80 backdrop-blur-xl border-b border-white/5">
        <div className="flex items-center gap-2">
          <div className="bg-gradient-to-br from-primary to-violet-600 p-2 rounded-xl">
            <Building2 className="h-5 w-5 text-white" />
          </div>
          <span className="font-bold text-lg tracking-tight">HotelSaaS</span>
        </div>
        <div className="hidden md:flex items-center gap-8 text-sm text-white/60">
          <a href="#features" className="hover:text-white transition-colors">Features</a>
          <a href="#preview" className="hover:text-white transition-colors">Product</a>
          <a href="#pricing" className="hover:text-white transition-colors">Pricing</a>
        </div>
        <div className="flex items-center gap-3">
          <Link href="/login" className="text-sm text-white/70 hover:text-white transition-colors px-4 py-2">
            Sign In
          </Link>
          <Link
            href="/register"
            className="text-sm font-semibold bg-gradient-to-r from-primary to-violet-600 hover:from-primary/90 hover:to-violet-500 px-4 py-2 rounded-xl transition-all shadow-lg shadow-primary/20"
          >
            Start Free
          </Link>
        </div>
      </nav>

      {/* ── HERO ─────────────────────────────────────────────────────────── */}
      <section className="relative pt-40 pb-28 px-6 md:px-12 flex flex-col items-center text-center overflow-hidden">
        {/* Background gradient orbs */}
        <div className="absolute top-20 left-1/4 w-96 h-96 rounded-full bg-primary/10 blur-3xl -z-10" />
        <div className="absolute top-40 right-1/4 w-80 h-80 rounded-full bg-violet-600/10 blur-3xl -z-10" />
        <div className="absolute bottom-0 left-1/2 -translate-x-1/2 w-[600px] h-48 bg-gradient-to-t from-violet-900/20 to-transparent blur-2xl -z-10" />

        {/* Badge */}
        <div className="inline-flex items-center gap-2 px-4 py-1.5 rounded-full border border-primary/30 bg-primary/5 text-primary text-xs font-semibold uppercase tracking-widest mb-8 animate-in fade-in slide-in-from-bottom-3 duration-700">
          <Zap className="h-3 w-3" />
          Enterprise Hotel Management
        </div>

        {/* Headline */}
        <h1 className="text-5xl md:text-7xl font-extrabold tracking-tight max-w-4xl leading-none mb-6 animate-in fade-in slide-in-from-bottom-4 duration-700 delay-100">
          One Platform.{' '}
          <span className="bg-gradient-to-r from-primary via-violet-400 to-cyan-400 bg-clip-text text-transparent">
            Every Property.
          </span>{' '}
          Infinite Control.
        </h1>

        <p className="text-lg md:text-xl text-white/50 max-w-2xl mb-10 leading-relaxed animate-in fade-in slide-in-from-bottom-4 duration-700 delay-200">
          The only hotel SaaS built with AI-powered revenue intelligence, atomic POS transactions,
          and multi-property group management — all in one beautifully unified platform.
        </p>

        <div className="flex flex-col sm:flex-row gap-4 animate-in fade-in slide-in-from-bottom-4 duration-700 delay-300">
          <Link
            href="/register"
            className="group flex items-center gap-2 bg-gradient-to-r from-primary to-violet-600 hover:from-primary/90 hover:to-violet-500 text-white px-8 py-4 rounded-2xl font-bold text-base shadow-2xl shadow-primary/30 transition-all hover:scale-105"
          >
            Start Your Organization
            <ArrowRight className="h-4 w-4 group-hover:translate-x-1 transition-transform" />
          </Link>
          <Link
            href="/login"
            className="flex items-center gap-2 border border-white/10 bg-white/5 hover:bg-white/10 text-white/80 hover:text-white px-8 py-4 rounded-2xl font-semibold text-base transition-all"
          >
            Sign In to Dashboard
            <ChevronRight className="h-4 w-4" />
          </Link>
        </div>

        {/* Social proof */}
        <div className="mt-12 flex flex-wrap items-center justify-center gap-6 text-white/30 text-sm animate-in fade-in duration-700 delay-500">
          {['Royal Spring Hotels', 'Grand Pacific Group', 'Villa Serena', 'Azure Resorts'].map((name) => (
            <span key={name} className="flex items-center gap-1">
              <Star className="h-3 w-3 fill-amber-400 text-amber-400" />
              {name}
            </span>
          ))}
        </div>
      </section>

      {/* ── STATS ─────────────────────────────────────────────────────────── */}
      <section className="border-y border-white/5 bg-white/[0.02] py-12 px-6">
        <div className="max-w-6xl mx-auto grid grid-cols-2 md:grid-cols-4 gap-8">
          {stats.map((s) => (
            <div key={s.label} className="text-center">
              <div className="text-3xl md:text-4xl font-extrabold bg-gradient-to-r from-primary to-violet-400 bg-clip-text text-transparent">
                {s.value}
              </div>
              <div className="text-xs text-white/40 mt-1 uppercase tracking-wider">{s.label}</div>
            </div>
          ))}
        </div>
      </section>

      {/* ── FEATURES ─────────────────────────────────────────────────────── */}
      <section id="features" className="py-28 px-6 md:px-12">
        <div className="max-w-6xl mx-auto">
          <div className="text-center mb-16">
            <h2 className="text-4xl md:text-5xl font-extrabold tracking-tight mb-4">
              Built for{' '}
              <span className="bg-gradient-to-r from-primary to-violet-400 bg-clip-text text-transparent">
                enterprise scale
              </span>
            </h2>
            <p className="text-white/40 max-w-xl mx-auto">
              Every feature was designed with a hotel group's operational complexity in mind.
            </p>
          </div>

          <div className="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            {features.map((f) => (
              <div
                key={f.title}
                className={`relative p-6 rounded-2xl border ${f.border} bg-gradient-to-br ${f.color} backdrop-blur-sm group hover:scale-[1.02] transition-all duration-300`}
              >
                <div className={`inline-flex p-3 rounded-xl bg-white/5 mb-4 ${f.iconColor}`}>
                  <f.icon className="h-6 w-6" />
                </div>
                <h3 className="font-bold text-white mb-2 text-lg">{f.title}</h3>
                <p className="text-white/50 text-sm leading-relaxed">{f.description}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ── DASHBOARD PREVIEW (Scroll-triggered) ─────────────────────────── */}
      <section id="preview" className="py-24 px-6 md:px-12 overflow-hidden">
        <div className="max-w-6xl mx-auto">
          <div className="text-center mb-12">
            <h2 className="text-4xl font-extrabold tracking-tight mb-4">
              The{' '}
              <span className="bg-gradient-to-r from-amber-400 to-orange-400 bg-clip-text text-transparent">
                Folio Ledger
              </span>{' '}
              — in real time
            </h2>
            <p className="text-white/40 max-w-lg mx-auto">
              Every charge, payment, and inventory deduction posted atomically to the guest's live financial ledger.
            </p>
          </div>

          <div
            ref={previewRef}
            className="rounded-2xl border border-white/10 bg-white/[0.03] backdrop-blur-xl overflow-hidden shadow-2xl shadow-black/50 opacity-0 translate-y-8 transition-all duration-1000"
            style={{ opacity: 0, transform: 'translateY(2rem)' }}
          >
            {/* Preview header */}
            <div className="flex items-center justify-between px-6 py-4 border-b border-white/5 bg-white/[0.02]">
              <div className="flex items-center gap-3">
                <div className="flex gap-1.5">
                  <div className="w-3 h-3 rounded-full bg-red-500/60" />
                  <div className="w-3 h-3 rounded-full bg-amber-500/60" />
                  <div className="w-3 h-3 rounded-full bg-emerald-500/60" />
                </div>
                <span className="text-white/30 text-xs font-mono">Folio #RES-2026-441 · Suite 401</span>
              </div>
              <div className="flex gap-4 text-xs text-white/30">
                <span>Charges: <span className="text-orange-400">₦210,700</span></span>
                <span>Payments: <span className="text-emerald-400">₦150,000</span></span>
                <span className="font-bold">Balance: <span className="text-red-400">₦60,700</span></span>
              </div>
            </div>

            {/* Table */}
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-white/5">
                    <th className="py-3 px-6 text-left text-white/30 font-medium text-xs uppercase">Date</th>
                    <th className="py-3 px-6 text-left text-white/30 font-medium text-xs uppercase">Description</th>
                    <th className="py-3 px-6 text-left text-white/30 font-medium text-xs uppercase">Source</th>
                    <th className="py-3 px-6 text-left text-white/30 font-medium text-xs uppercase">Status</th>
                    <th className="py-3 px-6 text-right text-white/30 font-medium text-xs uppercase">Amount</th>
                  </tr>
                </thead>
                <tbody>
                  {folioRows.map((row, i) => (
                    <tr
                      key={i}
                      className="border-b border-white/[0.04] hover:bg-white/[0.02] transition-colors"
                      style={{ animationDelay: `${i * 80}ms` }}
                    >
                      <td className="py-3 px-6 text-white/40 font-mono text-xs">{row.date}</td>
                      <td className="py-3 px-6 text-white/80 font-medium">{row.desc}</td>
                      <td className={`py-3 px-6 text-xs font-semibold ${sourceColor[row.source] ?? 'text-white/40'}`}>
                        {row.source}
                      </td>
                      <td className="py-3 px-6">
                        <span className={`inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold ${
                          row.status === 'PAID'
                            ? 'bg-emerald-500/10 text-emerald-400'
                            : 'bg-amber-500/10 text-amber-400'
                        }`}>
                          {row.status}
                        </span>
                      </td>
                      <td className="py-3 px-6 text-right font-mono font-semibold text-white/70">{row.amount}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </section>

      {/* ── CTA ──────────────────────────────────────────────────────────── */}
      <section className="py-28 px-6 text-center relative overflow-hidden">
        <div className="absolute inset-0 bg-gradient-to-br from-primary/5 via-violet-900/10 to-transparent -z-10" />
        <div className="absolute inset-0 bg-gradient-radial from-primary/10 to-transparent -z-10" />

        <CheckCircle2 className="h-12 w-12 text-primary mx-auto mb-6 opacity-80" />
        <h2 className="text-4xl md:text-6xl font-extrabold tracking-tight mb-6 max-w-3xl mx-auto">
          Ready to run your hotel group like{' '}
          <span className="bg-gradient-to-r from-primary to-violet-400 bg-clip-text text-transparent">enterprise</span>?
        </h2>
        <p className="text-white/40 max-w-lg mx-auto mb-10 text-lg">
          Register your organization. Add branches. Go live in minutes.
          No credit card required.
        </p>

        <Link
          href="/register"
          className="group inline-flex items-center gap-3 bg-gradient-to-r from-primary to-violet-600 hover:from-primary/90 hover:to-violet-500 text-white px-10 py-5 rounded-2xl font-bold text-lg shadow-2xl shadow-primary/30 transition-all hover:scale-105"
        >
          Start Your Organization — It's Free
          <ArrowRight className="h-5 w-5 group-hover:translate-x-1 transition-transform" />
        </Link>

        <p className="mt-6 text-xs text-white/20">
          Used by hotel groups across Nigeria, Ghana, Kenya, and South Africa.
        </p>
      </section>

      {/* ── FOOTER ───────────────────────────────────────────────────────── */}
      <footer className="border-t border-white/5 py-8 px-6 text-center text-white/20 text-xs">
        © 2026 HotelSaaS by DM Tech · Enterprise Hotel Management Platform
      </footer>

      <style jsx global>{`
        .animate-in-slow {
          opacity: 1 !important;
          transform: translateY(0) !important;
        }
      `}</style>
    </div>
  );
}
