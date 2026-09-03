/**
 * portStorage - Universal Port-Namespaced Storage Adapter
 *
 * Automatically scopes all localStorage / sessionStorage keys to the active browser port
 * (e.g. dm_auth_token_3002 vs dm_auth_token_3000) to prevent cross-port token bleeding
 * and session clobbering across the 6-port architecture.
 */

function getPort(): string {
  if (typeof window === 'undefined') return '3000';
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

  /**
   * Isolated logout: Clears all keys belonging strictly to the active port
   * without wiping sessions on other ports (e.g., 3000, 3002, 3003, 3004).
   */
  clearPortSession(customPort?: string): void {
    if (typeof window === 'undefined') return;
    const port = customPort || getPort();
    const suffix = `_${port}`;
    const keysToRemove: string[] = [];

    for (let i = 0; i < localStorage.length; i++) {
      const k = localStorage.key(i);
      if (k && (k.endsWith(suffix) || k.startsWith(`dm_`) && k.includes(`_${port}`))) {
        keysToRemove.push(k);
      }
    }

    keysToRemove.forEach((k) => localStorage.removeItem(k));
  },

  /**
   * Session storage helpers with port scoping
   */
  session: {
    getItem(key: string, customPort?: string): string | null {
      if (typeof window === 'undefined') return null;
      return sessionStorage.getItem(portStorage.getKey(key, customPort));
    },

    setItem(key: string, value: string, customPort?: string): void {
      if (typeof window === 'undefined') return;
      sessionStorage.setItem(portStorage.getKey(key, customPort), value);
    },

    removeItem(key: string, customPort?: string): void {
      if (typeof window === 'undefined') return;
      sessionStorage.removeItem(portStorage.getKey(key, customPort));
    },
  },
};

export default portStorage;
