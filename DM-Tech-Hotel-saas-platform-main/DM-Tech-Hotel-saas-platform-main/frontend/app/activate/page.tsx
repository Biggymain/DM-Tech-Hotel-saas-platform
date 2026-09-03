'use client';

import { useState, useEffect } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { Shield, Smartphone, ArrowRight, CheckCircle2, AlertCircle, Loader2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { toast } from 'sonner';
import api from '@/lib/api';

export default function ActivatePage() {
  const [branchToken, setBranchToken] = useState('');
  const [hardwareId, setHardwareId] = useState<string | null>(null);
  const [isActivating, setIsActivating] = useState(false);
  const [isSuccess, setIsSuccess] = useState(false);

  useEffect(() => {
    // Get Hardware ID from local storage or fetch it
    const fetchId = async () => {
      try {
        const response = await api.get('/v1/hardware-id');
        setHardwareId(response.data.hardware_id);
      } catch (error) {
        console.error('Failed to resolve hardware bridge:', error);
      }
    };
    fetchId();
  }, []);

  const handleActivate = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!branchToken) {
      toast.error('Please enter your Branch Activation Token');
      return;
    }

    setIsActivating(true);
    try {
      await api.post('/v1/auth/activate-branch', {
        branch_token: branchToken,
        hardware_id: hardwareId
      });
      
      setIsSuccess(true);
      toast.success('Hardware Marriage Successful!');
      
      // Redirect to login after a brief delay
      setTimeout(() => {
        window.location.href = '/login';
      }, 3000);
    } catch (error: any) {
      const message = error.response?.data?.message || 'Activation failed. Please check your token.';
      toast.error(message);
    } finally {
      setIsActivating(false);
    }
  };

  return (
    <div className="min-h-screen bg-[#0a0a0a] flex items-center justify-center p-4 selection:bg-blue-500/30">
      <div className="absolute inset-0 bg-[radial-gradient(circle_at_center,_var(--tw-gradient-stops))] from-blue-500/5 via-transparent to-transparent pointer-events-none" />
      
      <motion.div
        initial={{ opacity: 0, y: 20 }}
        animate={{ opacity: 1, y: 0 }}
        className="w-full max-w-md"
      >
        <Card className="bg-[#111] border-white/10 shadow-2xl relative overflow-hidden">
          <div className="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-blue-500 via-indigo-500 to-blue-500" />
          
          <CardHeader className="text-center space-y-2 pt-8">
            <motion.div
              initial={{ scale: 0.8 }}
              animate={{ scale: 1 }}
              className="mx-auto w-16 h-16 bg-blue-500/10 rounded-2xl flex items-center justify-center mb-4 border border-blue-500/20"
            >
              <Shield className="w-8 h-8 text-blue-500" />
            </motion.div>
            <CardTitle className="text-2xl font-bold text-white tracking-tight">
              Digital Fortress Activation
            </CardTitle>
            <CardDescription className="text-gray-400">
              Hardware Marriage Required for this device.
            </CardDescription>
          </CardHeader>

          <CardContent className="space-y-6 pb-8">
            <AnimatePresence mode="wait">
              {!isSuccess ? (
                <motion.form
                  key="form"
                  initial={{ opacity: 0 }}
                  animate={{ opacity: 1 }}
                  exit={{ opacity: 0 }}
                  onSubmit={handleActivate}
                  className="space-y-4"
                >
                  <div className="space-y-2">
                    <label className="text-xs font-semibold text-gray-500 uppercase tracking-widest ml-1">
                      Branch Activation Token
                    </label>
                    <div className="relative">
                      <Input
                        type="text"
                        placeholder="00000000-0000-0000-0000-000000000000"
                        value={branchToken}
                        onChange={(e) => setBranchToken(e.target.value)}
                        className="bg-black/50 border-white/10 text-white placeholder:text-gray-700 h-12 focus:ring-blue-500/50 transition-all font-mono text-sm"
                        disabled={isActivating}
                      />
                    </div>
                  </div>

                  <div className="bg-white/5 rounded-xl p-4 flex items-start gap-3 border border-white/5">
                    <Smartphone className="w-5 h-5 text-gray-400 mt-0.5" />
                    <div className="space-y-1">
                      <p className="text-xs font-medium text-gray-300">Target Hardware ID</p>
                      <p className="text-[10px] font-mono text-gray-500 break-all leading-relaxed">
                        {hardwareId || 'Detecting hardware bridge...'}
                      </p>
                    </div>
                  </div>

                  <Button
                    type="submit"
                    disabled={isActivating || !hardwareId}
                    className="w-full h-12 bg-blue-600 hover:bg-blue-500 text-white font-semibold transition-all shadow-[0_0_20px_rgba(37,99,235,0.3)] group mt-4"
                  >
                    {isActivating ? (
                      <Loader2 className="w-5 h-5 animate-spin" />
                    ) : (
                      <>
                        Activate Device
                        <ArrowRight className="w-4 h-4 ml-2 group-hover:translate-x-1 transition-transform" />
                      </>
                    )}
                  </Button>

                  <div className="flex items-center justify-center gap-2 text-xs text-gray-600">
                    <AlertCircle className="w-3.5 h-3.5" />
                    This action is permanent and device-locked.
                  </div>
                </motion.form>
              ) : (
                <motion.div
                  key="success"
                  initial={{ opacity: 0, scale: 0.9 }}
                  animate={{ opacity: 1, scale: 1 }}
                  className="text-center py-8 space-y-4"
                >
                  <div className="mx-auto w-20 h-20 bg-green-500/10 rounded-full flex items-center justify-center border border-green-500/20">
                    <CheckCircle2 className="w-10 h-10 text-green-500" />
                  </div>
                  <div className="space-y-2">
                    <h3 className="text-xl font-bold text-white">Marriage Confirmed</h3>
                    <p className="text-sm text-gray-400 max-w-[250px] mx-auto">
                      Device identity has been synchronized. Redirecting to login...
                    </p>
                  </div>
                </motion.div>
              )}
            </AnimatePresence>
          </CardContent>
        </Card>
      </motion.div>
    </div>
  );
}
