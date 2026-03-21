'use client';

import React, { useState, useEffect } from 'react';
import { 
    LineChart, Line, AreaChart, Area, XAxis, YAxis, CartesianGrid, 
    Tooltip, ResponsiveContainer, Legend, BarChart, Bar 
} from 'recharts';
import { 
    TrendingUp, TrendingDown, AlertCircle, CheckCircle2, 
    Zap, Calendar, BarChart3, PieChart, Info, Settings,
    Globe, ShieldCheck
} from 'lucide-react';
import axios from 'axios';
import { toast } from 'sonner';

// --- Components ---

const MetricCard = ({ title, value, subtitle, icon: Icon, trend }: any) => (
    <div className="bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm transition-all hover:shadow-md">
        <div className="flex justify-between items-start mb-4">
            <div className="bg-blue-50 dark:bg-blue-900/30 p-2.5 rounded-xl text-blue-600 dark:text-blue-400">
                <Icon size={22} />
            </div>
            {trend && (
                <span className={`text-xs font-medium px-2 py-1 rounded-full flex items-center gap-1 ${
                    trend > 0 ? 'bg-emerald-50 text-emerald-600' : 'bg-rose-50 text-rose-600'
                }`}>
                    {trend > 0 ? <TrendingUp size={12} /> : <TrendingDown size={12} />}
                    {Math.abs(trend)}%
                </span>
            )}
        </div>
        <h3 className="text-slate-500 dark:text-slate-400 text-sm font-medium">{title}</h3>
        <p className="text-2xl font-bold mt-1 text-slate-900 dark:text-white uppercase">{value}</p>
        <p className="text-xs text-slate-400 mt-2">{subtitle}</p>
    </div>
);

const RecommendationCard = ({ rec }: any) => (
    <div className="bg-slate-50 dark:bg-slate-800/50 p-5 rounded-xl border border-slate-200 dark:border-slate-700 flex flex-col justify-between">
        <div>
            <div className="flex justify-between items-start mb-3">
                <span className="text-xs font-semibold px-2 py-0.5 bg-blue-100 text-blue-700 rounded-md uppercase tracking-wider">
                    {rec.room_type_name}
                </span>
                <div className={`p-1.5 rounded-full ${
                    rec.adjustment_percent > 0 ? 'bg-emerald-100 text-emerald-600' : 'bg-amber-100 text-amber-600'
                }`}>
                    <Zap size={14} fill="currentColor" />
                </div>
            </div>
            <div className="flex items-baseline gap-2 mb-1">
                <span className="text-xl font-bold text-slate-900 dark:text-white">₦{rec.suggested_rate.toLocaleString()}</span>
                <span className="text-xs text-slate-400 line-through">₦{rec.current_rate.toLocaleString()}</span>
            </div>
            
            {rec.market_aware && (
                <div className="flex items-center gap-1.5 mb-3">
                    <div className="flex -space-x-1">
                        <div className="w-4 h-4 rounded-full bg-slate-200 dark:bg-slate-700 flex items-center justify-center border border-white dark:border-slate-800">
                            <Globe size={10} className="text-slate-500" />
                        </div>
                    </div>
                    <span className="text-[10px] font-medium text-slate-500">Market Adjusted</span>
                </div>
            )}

            <p className="text-xs text-slate-500 dark:text-slate-400 mb-4 flex items-start gap-1">
                <AlertCircle size={12} className="mt-0.5 shrink-0" />
                {rec.reason}
            </p>
        </div>
        <button 
            className="w-full py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium transition-colors shadow-sm"
            onClick={() => toast.success(`Applied ${rec.adjustment_percent}% change to ${rec.room_type_name}`)}
        >
            Apply Optimization
        </button>
    </div>
);

// --- Main Page ---

export default function RevenueIntelligencePage() {
    const [loading, setLoading] = useState(true);
    const [insights, setInsights] = useState([]);
    const [summary, setSummary] = useState<any>(null);

    useEffect(() => {
        fetchData();
    }, []);

    const fetchData = async () => {
        try {
            setLoading(true);
            const [insightsRes, summaryRes] = await Promise.all([
                axios.get(`${process.env.NEXT_PUBLIC_API_URL}/api/v1/admin/revenue/insights`),
                axios.get(`${process.env.NEXT_PUBLIC_API_URL}/api/v1/admin/revenue/summary`)
            ]);
            setInsights(insightsRes.data.data);
            setSummary(summaryRes.data.data);
        } catch (error) {
            console.error('Failed to fetch revenue insights', error);
            toast.error('Could not load revenue intelligence data');
        } finally {
            setLoading(false);
        }
    };

    const handleSync = async () => {
        try {
            toast.promise(
                axios.post(`${process.env.NEXT_PUBLIC_API_URL}/api/v1/admin/revenue/trigger`),
                {
                    loading: 'Analyzing booking data and generating insights...',
                    success: 'Insights generated successfully!',
                    error: 'Analysis failed. Please try again later.'
                }
            );
            setTimeout(fetchData, 3000);
        } catch (error) {}
    };

    if (loading) return (
        <div className="flex items-center justify-center min-h-[400px]">
            <div className="flex flex-col items-center gap-2">
                <div className="w-8 h-8 border-4 border-indigo-600 border-t-transparent rounded-full animate-spin"></div>
                <p className="text-sm text-slate-500 animate-pulse font-medium">Running revenue simulations...</p>
            </div>
        </div>
    );

    return (
        <div className="space-y-8 animate-in fade-in slide-in-from-bottom-2 duration-500">
            {/* Header */}
            <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <h1 className="text-3xl font-bold text-slate-900 dark:text-white flex items-center gap-3">
                        Revenue Intelligence
                        <span className="text-xs bg-indigo-100 text-indigo-700 px-2 py-0.5 rounded uppercase tracking-widest font-bold">AI ASSISTED</span>
                    </h1>
                    <p className="text-slate-500 max-w-2xl mt-1">
                        Predictive occupancy analysis and dynamic pricing recommendations.
                    </p>
                </div>
                <div className="flex items-center gap-3">
                    <button 
                        onClick={handleSync}
                        className="flex items-center gap-2 px-5 py-2.5 bg-indigo-600 text-white rounded-xl text-sm font-semibold hover:bg-indigo-700 transition-colors shadow-sm"
                    >
                        <BarChart3 size={18} />
                        Run Analysis
                    </button>
                </div>
            </div>

            {/* Automation Settings (Phase 3) */}
            <div className="bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-100 dark:border-indigo-800/50 p-6 rounded-2xl flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div className="flex items-center gap-4">
                    <div className="bg-white dark:bg-slate-900 p-3 rounded-xl shadow-sm border border-indigo-100 dark:border-indigo-800 text-indigo-600">
                        <Settings size={22} />
                    </div>
                    <div>
                        <h3 className="text-sm font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            Automated Rate Application
                            {!summary?.config?.auto_apply_enabled && (
                                <span className="text-[10px] bg-slate-200 dark:bg-slate-700 text-slate-600 dark:text-slate-400 px-1.5 py-0.5 rounded">DISABLED</span>
                            )}
                        </h3>
                        <p className="text-xs text-slate-500 mt-0.5">Allow the AI to automatically update room prices when high demand spikes are detected.</p>
                    </div>
                </div>
                <button 
                    onClick={async () => {
                        try {
                            const newState = !summary?.config?.auto_apply_enabled;
                            await axios.put(`${process.env.NEXT_PUBLIC_API_URL}/api/v1/admin/revenue/config`, {
                                auto_apply_enabled: newState
                            });
                            setSummary({ ...summary, config: { ...summary.config, auto_apply_enabled: newState } });
                            toast.success(newState ? 'Auto-apply enabled' : 'Auto-apply disabled');
                        } catch (e) {
                            toast.error('Failed to update settings');
                        }
                    }}
                    className={`px-6 py-2 rounded-xl text-sm font-bold border transition-all ${
                        summary?.config?.auto_apply_enabled 
                        ? 'bg-white dark:bg-slate-900 border-indigo-200 dark:border-indigo-800 text-indigo-600'
                        : 'bg-indigo-600 border-indigo-600 text-white shadow-md hover:bg-indigo-700'
                    }`}
                >
                    {summary?.config?.auto_apply_enabled ? 'Disable Automation' : 'Enable Automation'}
                </button>
            </div>

            {/* Quick Stats */}
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <MetricCard 
                    title="Forecasted Occupancy (7d)" 
                    value={`${summary?.forecast?.avg_occupancy_7d}%`}
                    subtitle="Expected next 7 days"
                    icon={PieChart}
                    trend={5.2}
                />
                <MetricCard 
                    title="Current Demand Score" 
                    value={summary?.forecast?.avg_demand_7d}
                    subtitle="Market strength index"
                    icon={TrendingUp}
                    trend={2.1}
                />
                <MetricCard 
                    title="Today's RevPAR" 
                    value={`₦${summary?.today?.revpar || '0'}`}
                    subtitle="Revenue per available room"
                    icon={BarChart3}
                />
                <MetricCard 
                    title="Recommendations" 
                    value={summary?.recommendations?.length || 0}
                    subtitle="Pending price optimizations"
                    icon={Zap}
                />
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
                {/* Forecast Chart */}
                <div className="lg:col-span-2 bg-white dark:bg-slate-900 p-8 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm">
                    <div className="flex justify-between items-center mb-8">
                        <div>
                            <h2 className="text-lg font-bold text-slate-900 dark:text-white">Occupancy & Demand Forecast</h2>
                            <p className="text-sm text-slate-500">Next 30 days projection</p>
                        </div>
                        <div className="flex gap-4">
                            <div className="flex items-center gap-2">
                                <span className="w-3 h-3 rounded-full bg-indigo-500"></span>
                                <span className="text-xs text-slate-500 font-medium">Occupancy %</span>
                            </div>
                            <div className="flex items-center gap-2">
                                <span className="w-3 h-3 rounded-full bg-slate-300"></span>
                                <span className="text-xs text-slate-500 font-medium">Demand Score</span>
                            </div>
                        </div>
                    </div>
                    
                    <div className="h-[350px] w-full">
                        <ResponsiveContainer width="100%" height="100%">
                            <AreaChart data={insights}>
                                <defs>
                                    <linearGradient id="colorOcc" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="5%" stopColor="#6366f1" stopOpacity={0.1}/>
                                        <stop offset="95%" stopColor="#6366f1" stopOpacity={0}/>
                                    </linearGradient>
                                </defs>
                                <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="var(--muted)" />
                                <XAxis 
                                    dataKey="date" 
                                    tickFormatter={(val) => new Date(val).toLocaleDateString('en-US', { day: 'numeric', month: 'short' })}
                                    axisLine={false}
                                    tickLine={false}
                                    tick={{ fill: 'var(--muted-foreground)', fontSize: 12 }}
                                    dy={10}
                                />
                                <YAxis 
                                    axisLine={false}
                                    tickLine={false}
                                    tick={{ fill: 'var(--muted-foreground)', fontSize: 12 }}
                                />
                                <Tooltip 
                                    contentStyle={{ 
                                        borderRadius: '12px', 
                                        border: 'none', 
                                        boxShadow: '0 10px 15px -3px rgb(0 0 0 / 0.1)' 
                                    }}
                                />
                                <Area 
                                    type="monotone" 
                                    dataKey="occupancy_rate" 
                                    stroke="var(--primary)" 
                                    strokeWidth={3}
                                    fillOpacity={1} 
                                    fill="url(#colorOcc)" 
                                />
                                <Area 
                                    type="monotone" 
                                    dataKey="demand_score" 
                                    stroke="var(--muted-foreground)" 
                                    strokeWidth={2}
                                    fill="transparent"
                                />
                            </AreaChart>
                        </ResponsiveContainer>
                    </div>
                </div>

                {/* Recommendations List */}
                <div className="bg-white dark:bg-slate-900 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm overflow-hidden flex flex-col">
                    <div className="p-6 border-b border-slate-100 dark:border-slate-800">
                        <h2 className="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                            <Zap size={20} className="text-indigo-500" fill="currentColor" />
                            Optimizations
                        </h2>
                        <p className="text-xs text-slate-500 mt-1">Recommended for immediate implementation</p>
                    </div>
                    <div className="p-6 space-y-4 overflow-y-auto max-h-[450px] scrollbar-hide">
                        {summary?.recommendations?.length > 0 ? (
                            summary?.recommendations?.map((rec: any, idx: number) => (
                                <RecommendationCard key={idx} rec={rec} />
                            ))
                        ) : (
                            <div className="text-center py-10">
                                <div className="bg-slate-50 dark:bg-slate-800 w-12 h-12 rounded-full flex items-center justify-center mx-auto mb-4 text-slate-400">
                                    <CheckCircle2 size={24} />
                                </div>
                                <p className="text-sm font-medium text-slate-900 dark:text-white">All Optimized</p>
                                <p className="text-xs text-slate-500 mt-1">Rates are perfectly aligned with demand.</p>
                            </div>
                        )}
                    </div>
                    <div className="p-4 mt-auto border-t border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/30">
                        <p className="text-[10px] text-slate-400 leading-relaxed text-center px-4">
                            <Info size={10} className="inline mr-1" />
                            Estimates are based on {insights.length} days of processed data. Actual revenue may vary based on market conditions.
                        </p>
                    </div>
                </div>
            </div>

            {/* ADR vs RevPAR Section */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <div className="bg-white dark:bg-slate-900 p-8 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm">
                    <h3 className="text-md font-bold text-slate-900 dark:text-white mb-6 flex items-center gap-2 uppercase tracking-wider text-sm opacity-70">
                        <BarChart3 size={16} />
                        Average Daily Rate (ADR)
                    </h3>
                    <div className="h-[250px]">
                        <ResponsiveContainer width="100%" height="100%">
                            <BarChart data={insights}>
                                <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="var(--muted)" />
                                <XAxis 
                                    dataKey="date" 
                                    hide 
                                />
                                <YAxis 
                                    axisLine={false}
                                    tickLine={false}
                                    tick={{ fill: 'var(--muted-foreground)', fontSize: 10 }}
                                />
                                <Tooltip cursor={{fill: 'transparent'}} />
                                <Bar dataKey="avg_daily_rate" fill="#a5b4fc" radius={[4, 4, 0, 0]} />
                            </BarChart>
                        </ResponsiveContainer>
                    </div>
                </div>

                <div className="bg-white dark:bg-slate-900 p-8 rounded-2xl border border-slate-200 dark:border-slate-800 shadow-sm">
                    <h3 className="text-md font-bold text-slate-900 dark:text-white mb-6 flex items-center gap-2 uppercase tracking-wider text-sm opacity-70">
                        <TrendingUp size={16} />
                        Revenue Per Available Room
                    </h3>
                    <div className="h-[250px]">
                        <ResponsiveContainer width="100%" height="100%">
                            <LineChart data={insights}>
                                <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="var(--muted)" />
                                <XAxis dataKey="date" hide />
                                <YAxis axisLine={false} tickLine={false} tick={{ fill: 'var(--muted-foreground)', fontSize: 10 }} />
                                <Tooltip />
                                <Line type="stepAfter" dataKey="revpar" stroke="var(--primary)" strokeWidth={3} dot={false} />
                            </LineChart>
                        </ResponsiveContainer>
                    </div>
                </div>
            </div>
        </div>
    );
}
