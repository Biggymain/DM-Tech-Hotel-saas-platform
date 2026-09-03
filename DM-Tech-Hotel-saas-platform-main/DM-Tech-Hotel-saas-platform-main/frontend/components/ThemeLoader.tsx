'use client';

import React from 'react';
import api from '@/lib/api';

interface ThemeLoaderProps {
  onLoaded?: () => void;
}

export default function ThemeLoader({ onLoaded }: ThemeLoaderProps) {
  const [isThemeApplied, setIsThemeApplied] = React.useState(false);

  React.useEffect(() => {
    const fetchTheme = async () => {
      try {
        const { data } = await api.get('/api/v1/theme');
        
        if (data.colors) {
            const root = document.documentElement;
            
            // Map JSON colors to CSS variables
            if (data.colors.primary) {
                root.style.setProperty('--primary', data.colors.primary);
            }
            if (data.colors.background) {
                root.style.setProperty('--background', data.colors.background);
            }
            
            // Force dark/light mode class based on backend response
            if (data.mode === 'dark') {
                root.classList.add('dark');
                root.classList.remove('light');
            } else {
                root.classList.add('light');
                root.classList.remove('dark');
            }

            console.log("Branding Assets Applied:", data.template);
        }
      } catch (err) {
        console.warn("Theme acquisition failed, defaulting to baseline branding.");
      } finally {
        setIsThemeApplied(true);
        onLoaded?.();
      }
    };

    fetchTheme();
  }, [onLoaded]);

  return isThemeApplied ? null : null;
}
