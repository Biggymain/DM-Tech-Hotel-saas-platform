'use client';

import * as React from 'react';
import { motion } from 'framer-motion';
import { Check, Clock, Utensils, Truck, Home, AlertCircle } from 'lucide-react';
import { cn } from '@/lib/utils';

type OrderStatus = 'pending' | 'accepted' | 'preparing' | 'ready' | 'out_for_delivery' | 'delivered' | 'cancelled';

interface OrderTrackerProps {
  status: OrderStatus;
  updatedAt: string;
}

const steps: { status: OrderStatus; label: string; icon: any }[] = [
  { status: 'pending', label: 'Order Received', icon: Clock },
  { status: 'preparing', label: 'Preparing', icon: Utensils },
  { status: 'out_for_delivery', label: 'On the Way', icon: Truck },
  { status: 'delivered', label: 'Delivered', icon: Home },
];

export default function OrderTracker({ status, updatedAt }: OrderTrackerProps) {
  const currentStepIndex = steps.findIndex((s) => {
    if (status === 'accepted' || status === 'ready') return s.status === 'preparing';
    return s.status === status;
  });

  const isCancelled = status === 'cancelled';

  return (
    <div className="w-full space-y-6 py-4">
      <div className="flex items-center justify-between px-1">
        <h3 className="font-semibold text-white">Order Progress</h3>
        <span className="text-xs text-slate-400">Updated {new Date(updatedAt).toLocaleTimeString()}</span>
      </div>

      <div className="relative">
        {/* Progress Line */}
        <div className="absolute left-[22px] top-0 bottom-0 w-0.5 bg-white/10" />
        <div 
          className="absolute left-[22px] top-0 w-0.5 bg-indigo-500 transition-all duration-1000 ease-in-out" 
          style={{ height: isCancelled ? '0%' : `${(currentStepIndex / (steps.length - 1)) * 100}%` }}
        />

        <div className="space-y-8 relative">
          {steps.map((step, index) => {
            const isCompleted = !isCancelled && index <= currentStepIndex;
            const isActive = !isCancelled && index === currentStepIndex;
            const Icon = step.icon;

            return (
              <div key={step.status} className="flex items-start group">
                <div className={cn(
                  "relative z-10 flex items-center justify-center w-11 h-11 rounded-full border-2 transition-all duration-500",
                  isCompleted ? "bg-indigo-600 border-indigo-500 shadow-lg shadow-indigo-500/20" : "bg-slate-900 border-white/10",
                  isActive && "scale-110 ring-4 ring-indigo-500/20"
                )}>
                  {isCompleted && !isActive ? (
                    <Check className="text-white" size={20} />
                  ) : (
                    <Icon className={cn("transition-colors", isCompleted ? "text-white" : "text-slate-500")} size={20} />
                  )}
                </div>
                
                <div className="ml-4 pt-2">
                  <p className={cn(
                    "font-medium transition-colors",
                    isCompleted ? "text-white" : "text-slate-500"
                  )}>
                    {step.label}
                  </p>
                  {isActive && (
                    <motion.p 
                      initial={{ opacity: 0 }}
                      animate={{ opacity: 1 }}
                      className="text-xs text-indigo-400 mt-0.5 font-medium"
                    >
                      Current Status
                    </motion.p>
                  )}
                </div>
              </div>
            );
          })}

          {isCancelled && (
            <div className="flex items-start group">
              <div className="relative z-10 flex items-center justify-center w-11 h-11 rounded-full bg-red-500/20 border-2 border-red-500 shadow-lg shadow-red-500/20">
                <AlertCircle className="text-red-500" size={20} />
              </div>
              <div className="ml-4 pt-2 text-red-400">
                <p className="font-medium">Order Cancelled</p>
                <p className="text-xs mt-0.5 opacity-80">Please contact support for details.</p>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
