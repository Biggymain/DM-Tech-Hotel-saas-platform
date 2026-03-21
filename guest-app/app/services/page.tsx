'use client';

import * as React from 'react';
import { motion } from 'framer-motion';
import { useRouter } from 'next/navigation';
import { 
  ArrowLeft, 
  Wind, 
  Wrench, 
  Waves, 
  ConciergeBell, 
  Smartphone, 
  Loader2,
  CheckCircle2,
  Layers
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { toast } from 'sonner';
import api from '@/lib/api';

const SERVICE_TYPES = [
  { id: 'housekeeping', label: 'Housekeeping', icon: Wind, description: 'Room cleaning & turndown', color: 'bg-blue-500' },
  { id: 'extra_towels', label: 'Extra Towels', icon: Layers, description: 'Fresh towels to your room', color: 'bg-emerald-500' },
  { id: 'maintenance', label: 'Maintenance', icon: Wrench, description: 'Report a technical issue', color: 'bg-orange-500' },
  { id: 'concierge', label: 'Concierge', icon: ConciergeBell, description: 'Booking & local info', color: 'bg-purple-500' },
  { id: 'laundry', label: 'Laundry', icon: Smartphone, description: 'Pick up & dry cleaning', color: 'bg-pink-500' },
];

export default function GuestServiceHub() {
  const router = useRouter();
  const [selectedType, setSelectedType] = React.useState<string | null>(null);
  const [description, setDescription] = React.useState('');
  const [isSubmitting, setIsSubmitting] = React.useState(false);
  const [isSuccess, setIsSuccess] = React.useState(false);

  const handleSubmit = async () => {
    if (!selectedType) return;
    setIsSubmitting(true);

    try {
      await api.post('/api/v1/guest/service-requests', {
        request_type: selectedType === 'extra_towels' ? 'housekeeping' : selectedType,
        description: selectedType === 'extra_towels' ? `Extra towels: ${description}` : description,
        // session_token is handled by axios interceptor if we set it up, otherwise we need to pull from storage
        session_token: localStorage.getItem('guest_session_token') || '',
      });

      setIsSuccess(true);
      toast.success('Request sent successfully');
      setTimeout(() => router.push('/'), 3000);
    } catch (error) {
      toast.error('Failed to send request. Please try again.');
    } finally {
      setIsSubmitting(false);
    }
  };

  if (isSuccess) {
    return (
      <div className="flex flex-col items-center justify-center min-h-[60vh] text-center space-y-6">
        <motion.div 
          initial={{ scale: 0 }}
          animate={{ scale: 1 }}
          className="w-20 h-20 bg-emerald-500 rounded-full flex items-center justify-center"
        >
          <CheckCircle2 className="text-white" size={40} />
        </motion.div>
        <div className="space-y-2">
          <h2 className="text-2xl font-bold text-white">Request Received!</h2>
          <p className="text-slate-400">Our team has been notified and will be with you shortly.</p>
        </div>
        <Button onClick={() => router.push('/')} variant="outline" className="border-white/10 text-white">
          Back to Home
        </Button>
      </div>
    );
  }

  return (
    <div className="space-y-8 animate-in fade-in duration-500">
      <header className="flex items-center space-x-4">
        <Button 
          variant="ghost" 
          size="icon" 
          onClick={() => router.back()}
          className="rounded-full bg-white/5 border border-white/10"
        >
          <ArrowLeft size={18} />
        </Button>
        <div>
          <h1 className="text-2xl font-bold text-white">Service Hub</h1>
          <p className="text-sm text-slate-400">How can we help you today?</p>
        </div>
      </header>

      {!selectedType ? (
        <div className="grid gap-4">
          {SERVICE_TYPES.map((service, index) => (
            <motion.div
              key={service.id}
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: index * 0.1 }}
              whileTap={{ scale: 0.98 }}
              onClick={() => setSelectedType(service.id)}
            >
              <Card className="bg-white/5 border-white/10 hover:bg-white/10 transition-colors cursor-pointer overflow-hidden group">
                <CardContent className="p-4 flex items-center">
                  <div className={`p-3 rounded-xl ${service.color}/20 ${service.color.replace('bg-', 'text-')} group-hover:scale-110 transition-transform`}>
                    <service.icon size={24} />
                  </div>
                  <div className="ml-4 flex-1">
                    <h3 className="font-semibold text-white">{service.label}</h3>
                    <p className="text-xs text-slate-400">{service.description}</p>
                  </div>
                </CardContent>
              </Card>
            </motion.div>
          ))}
        </div>
      ) : (
        <div className="space-y-6">
          <div className="flex items-center p-4 bg-indigo-500/10 border border-indigo-500/20 rounded-2xl">
            <div className="p-2 bg-indigo-500 rounded-lg text-white">
              {SERVICE_TYPES.find(s => s.id === selectedType)?.icon({ size: 18 })}
            </div>
            <div className="ml-3">
              <p className="text-xs font-medium text-indigo-400 uppercase tracking-wider">Selected Service</p>
              <p className="text-sm font-bold text-white">{SERVICE_TYPES.find(s => s.id === selectedType)?.label}</p>
            </div>
          </div>

          <div className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="note" className="text-slate-300">Additional Details (Optional)</Label>
              <Textarea 
                id="note" 
                placeholder="Ex: Please bring two fresh towels or AC temperature issue..."
                className="bg-white/5 border-white/10 text-white min-h-[120px]"
                value={description}
                onChange={(e) => setDescription(e.target.value)}
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor="room" className="text-slate-300">Confirm Room Number</Label>
              <Input 
                id="room" 
                placeholder="Ex: 304"
                className="bg-white/5 border-white/10 text-white"
              />
            </div>
          </div>

          <div className="flex gap-3">
            <Button 
              variant="ghost" 
              className="flex-1 border border-white/10 text-white h-12 rounded-xl"
              onClick={() => setSelectedType(null)}
            >
              Changed Mind
            </Button>
            <Button 
              className="flex-[2] bg-indigo-600 hover:bg-indigo-500 text-white font-bold h-12 rounded-xl shadow-lg shadow-indigo-600/20"
              disabled={isSubmitting}
              onClick={handleSubmit}
            >
              {isSubmitting ? <Loader2 className="animate-spin mr-2" /> : 'Submit Request'}
            </Button>
          </div>
        </div>
      )}
    </div>
  );
}
