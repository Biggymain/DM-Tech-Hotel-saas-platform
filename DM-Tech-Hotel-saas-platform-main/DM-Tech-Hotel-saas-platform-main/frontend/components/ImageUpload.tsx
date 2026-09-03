'use client';

import * as React from 'react';
import { Image as ImageIcon, Upload, Loader2, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { toast } from 'sonner';
import api from '@/lib/api';

interface ImageUploadProps {
  value: string;
  onChange: (url: string) => void;
  folder?: string;
  label?: string;
}

export function ImageUpload({ value, onChange, folder = 'website', label }: ImageUploadProps) {
  const [uploading, setUploading] = React.useState(false);
  const fileInputRef = React.useRef<HTMLInputElement>(null);

  const handleUpload = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    // Basic client-side validation
    if (!file.type.startsWith('image/')) {
      toast.error('Please select an image file');
      return;
    }

    if (file.size > 5 * 1024 * 1024) {
      toast.error('Image size must be less than 5MB');
      return;
    }

    try {
      setUploading(true);
      const formData = new FormData();
      formData.append('image', file);
      formData.append('folder', folder);

      const { data } = await api.post('/api/v1/organization/website/upload-image', formData, {
        headers: {
          'Content-Type': 'multipart/form-data',
        },
      });

      onChange(data.url);
      toast.success('Image uploaded successfully');
    } catch (error: any) {
      console.error('Upload failed:', error);
      toast.error(error?.response?.data?.message || 'Failed to upload image');
    } finally {
      setUploading(false);
      if (fileInputRef.current) {
        fileInputRef.current.value = '';
      }
    }
  };

  const clearImage = () => {
    onChange('');
  };

  return (
    <div className="space-y-2">
      {label && <label className="text-sm font-medium">{label}</label>}
      <div className="flex flex-col gap-4">
        <div className="flex gap-4 items-start">
          <div className="relative group overflow-hidden rounded-xl border border-dashed border-border bg-muted/30 flex items-center justify-center h-24 w-24 flex-shrink-0">
            {value ? (
              <>
                <img src={value} alt="Preview" className="h-full w-full object-cover transition-transform group-hover:scale-105" />
                <button 
                  onClick={clearImage}
                  className="absolute top-1 right-1 p-1 rounded-full bg-destructive/80 text-destructive-foreground opacity-0 group-hover:opacity-100 transition-opacity"
                >
                  <X size={12} />
                </button>
              </>
            ) : (
              <ImageIcon size={24} className="text-muted-foreground/30" />
            )}
            
            {uploading && (
              <div className="absolute inset-0 bg-background/60 backdrop-blur-sm flex items-center justify-center">
                <Loader2 size={24} className="animate-spin text-primary" />
              </div>
            )}
          </div>

          <div className="flex-1 space-y-3">
            <div className="flex gap-2">
              <Input 
                placeholder="https://..." 
                value={value} 
                onChange={(e) => onChange(e.target.value)}
                className="flex-1 h-9 text-xs"
              />
              <input
                type="file"
                className="hidden"
                ref={fileInputRef}
                onChange={handleUpload}
                accept="image/*"
              />
              <Button 
                variant="outline" 
                size="sm" 
                className="gap-2 h-9" 
                onClick={() => fileInputRef.current?.click()}
                disabled={uploading}
              >
                {uploading ? <Loader2 size={14} className="animate-spin" /> : <Upload size={14} />}
                Upload
              </Button>
            </div>
            <p className="text-[10px] text-muted-foreground">
              Paste a URL or upload a file (JPG, PNG, WEBP, max 5MB).
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}
