'use client';

import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import axios from 'axios';
import { toast } from 'sonner';
import { Plus, Trash2, Edit2, Loader2 } from 'lucide-react';

export default function KitchenStationsPage() {
  const queryClient = useQueryClient();
  const [isEditing, setIsEditing] = useState<any>(null);

  const { data: stations, isLoading } = useQuery({
    queryKey: ['kitchen-stations'],
    queryFn: async () => {
      const res = await axios.get('/api/v1/kds/stations');
      return res.data;
    },
  });

  const createMutation = useMutation({
    mutationFn: (newStation: any) => axios.post('/api/v1/kds/stations', newStation),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['kitchen-stations'] });
      toast.success('Station created');
      setIsEditing(null);
    },
  });

  const updateMutation = useMutation({
    mutationFn: (station: any) => axios.put(`/api/v1/kds/stations/${station.id}`, station),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['kitchen-stations'] });
      toast.success('Station updated');
      setIsEditing(null);
    },
  });

  const deleteMutation = useMutation({
    mutationFn: (id: number) => axios.delete(`/api/v1/kds/stations/${id}`),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['kitchen-stations'] });
      toast.success('Station deleted');
    },
  });

  if (isLoading) return <div className="flex justify-center p-10"><Loader2 className="animate-spin" /></div>;

  return (
    <div className="p-6">
      <div className="flex justify-between items-center mb-6">
        <h1 className="text-2xl font-bold">Kitchen Stations</h1>
        <button 
          onClick={() => setIsEditing({ name: '', description: '', is_active: true })}
          className="bg-primary text-white px-4 py-2 rounded-lg flex items-center gap-2"
        >
          <Plus size={18} /> New Station
        </button>
      </div>

      <div className="grid gap-4">
        {stations?.map((station: any) => (
          <div key={station.id} className="bg-card p-4 rounded-xl border flex justify-between items-center shadow-sm">
            <div>
              <h3 className="font-semibold text-lg">{station.name}</h3>
              <p className="text-sm text-muted-foreground">{station.description || 'No description'}</p>
            </div>
            <div className="flex gap-2">
              <button onClick={() => setIsEditing(station)} className="p-2 hover:bg-accent rounded-lg">
                <Edit2 size={18} />
              </button>
              <button 
                onClick={() => {
                  if (confirm('Delete this station?')) deleteMutation.mutate(station.id);
                }}
                className="p-2 hover:bg-destructive/10 text-destructive rounded-lg"
              >
                <Trash2 size={18} />
              </button>
            </div>
          </div>
        ))}
      </div>

      {isEditing && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center p-4 z-50">
          <div className="bg-background p-6 rounded-2xl w-full max-w-md shadow-2xl border">
            <h2 className="text-xl font-bold mb-4">{isEditing.id ? 'Edit Station' : 'New Station'}</h2>
            <div className="space-y-4">
              <div>
                <label className="block text-sm font-medium mb-1">Station Name</label>
                <input 
                  type="text" 
                  value={isEditing.name} 
                  onChange={(e) => setIsEditing({ ...isEditing, name: e.target.value })}
                  className="w-full p-2 rounded-lg border bg-input"
                  placeholder="e.g. Grill, Pizzeria"
                />
              </div>
              <div>
                <label className="block text-sm font-medium mb-1">Description</label>
                <textarea 
                  value={isEditing.description} 
                  onChange={(e) => setIsEditing({ ...isEditing, description: e.target.value })}
                  className="w-full p-2 rounded-lg border bg-input"
                />
              </div>
              <div className="flex items-center gap-2">
                <input 
                  type="checkbox" 
                  checked={isEditing.is_active} 
                  onChange={(e) => setIsEditing({ ...isEditing, is_active: e.target.checked })}
                />
                <label className="text-sm">Active</label>
              </div>
            </div>
            <div className="flex justify-end gap-2 mt-6">
              <button onClick={() => setIsEditing(null)} className="px-4 py-2 hover:bg-accent rounded-lg">Cancel</button>
              <button 
                onClick={() => isEditing.id ? updateMutation.mutate(isEditing) : createMutation.mutate(isEditing)}
                className="bg-primary text-white px-4 py-2 rounded-lg"
              >
                {isEditing.id ? 'Update' : 'Create'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
