/**
 * portStorage - Universal Port-Namespaced Storage Adapter for Guest-App
 */

function getPort(): string {
  if (typeof window === 'undefined') return '3004';
  return window.location.port || (window.location.protocol === 'https:' ? '443' : '80');
}

export const portStorage = {
  getPort,

  getKey(key: string, customPort?: string): string {
    const port = customPort || getPort();
    return `dm_${key}_${port}`;
  },

  getItem(key: string, customPort?: string): string | null {
    if (typeof window === 'undefined') return null;
    return localStorage.getItem(this.getKey(key, customPort));
  },

  setItem(key: string, value: string, customPort?: string): void {
    if (typeof window === 'undefined') return;
    localStorage.setItem(this.getKey(key, customPort), value);
  },

  removeItem(key: string, customPort?: string): void {
    if (typeof window === 'undefined') return;
    localStorage.removeItem(this.getKey(key, customPort));
  },

  clearPortSession(customPort?: string): void {
    if (typeof window === 'undefined') return;
    const port = customPort || getPort();
    const suffix = `_${port}`;
    const keysToRemove: string[] = [];

    for (let i = 0; i < localStorage.length; i++) {
      const k = localStorage.key(i);
      if (k && (k.endsWith(suffix) || (k.startsWith(`dm_`) && k.includes(`_${port}`)))) {
        keysToRemove.push(k);
      }
    }

    keysToRemove.forEach((k) => localStorage.removeItem(k));
  },
};

export default portStorage;
