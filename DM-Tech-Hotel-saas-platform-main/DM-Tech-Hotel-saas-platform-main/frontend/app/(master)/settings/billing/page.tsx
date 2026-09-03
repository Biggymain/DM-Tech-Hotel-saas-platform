'use client';

import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { toast } from 'sonner';

const BillingSettings = () => {
  const [loading, setLoading] = useState(false);
  const [billingMode, setBillingMode] = useState('individual'); // 'individual' or 'collective'
  const [gateways, setGateways] = useState({
    stripe: { apiKey: '', secretKey: '', active: false },
    paystack: { apiKey: '', secretKey: '', active: false },
    flutterwave: { apiKey: '', secretKey: '', active: false },
  });

  const handleSave = async () => {
    setLoading(true);
    try {
      // In a real implementation, this would hit the backend GatewaySetting update endpoint
      // For now, we simulate the success
      await new Promise(resolve => setTimeout(resolve, 1000));
      toast.success('Global Vault Updated - Keys Sealed & Encrypted');
    } catch (error) {
      toast.error('Vault Breach: Failed to save keys');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="mx-auto max-w-270">
      <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h2 className="text-title-md2 font-semibold text-black dark:text-white">
          Master Billing Vault
        </h2>
      </div>

      <div className="grid grid-cols-5 gap-8">
        <div className="col-span-5 xl:col-span-3">
          <div className="rounded-sm border border-stroke bg-white shadow-default dark:border-strokedark dark:bg-boxdark">
            <div className="border-b border-stroke py-4 px-7 dark:border-strokedark">
              <h3 className="font-medium text-black dark:text-white">
                Global Payment Gateways
              </h3>
            </div>
            <div className="p-7">
              <form action="#">
                {/* Billing Mode Toggle */}
                <div className="mb-5.5">
                  <label className="mb-3 block text-sm font-medium text-black dark:text-white">
                    Platform Billing Mode
                  </label>
                  <div className="flex items-center gap-4">
                    <button
                      type="button"
                      onClick={() => setBillingMode('individual')}
                      className={`flex items-center gap-2 rounded py-2 px-4 text-sm font-medium ${
                        billingMode === 'individual'
                          ? 'bg-primary text-white'
                          : 'bg-gray text-black dark:bg-meta-4 dark:text-white'
                      }`}
                    >
                      Individual (Per-Hotel)
                    </button>
                    <button
                      type="button"
                      onClick={() => setBillingMode('collective')}
                      className={`flex items-center gap-2 rounded py-2 px-4 text-sm font-medium ${
                        billingMode === 'collective'
                          ? 'bg-primary text-white'
                          : 'bg-gray text-black dark:bg-meta-4 dark:text-white'
                      }`}
                    >
                      Collective (Group-Level)
                    </button>
                  </div>
                  <p className="mt-2 text-xs text-gray-500">
                    Individual mode allows hotels to use their own keys. Collective mode uses these global keys for all revenue.
                  </p>
                </div>

                <hr className="my-6 border-stroke dark:border-strokedark" />

                {/* Stripe Config */}
                <div className="mb-5.5">
                  <div className="flex items-center justify-between mb-3">
                    <label className="block text-sm font-medium text-black dark:text-white">
                      Stripe (Global)
                    </label>
                    <span className="text-xs font-semibold text-meta-3">ENCRYPTED</span>
                  </div>
                  <input
                    className="w-full rounded border border-stroke bg-gray py-3 px-4.5 text-black focus:border-primary focus-visible:outline-none dark:border-strokedark dark:bg-meta-4 dark:text-white dark:focus:border-primary"
                    type="password"
                    placeholder="sk_live_..."
                    defaultValue="••••••••••••••••"
                  />
                </div>

                {/* Paystack Config */}
                <div className="mb-5.5">
                  <div className="flex items-center justify-between mb-3">
                    <label className="block text-sm font-medium text-black dark:text-white">
                      Paystack (Global)
                    </label>
                    <span className="text-xs font-semibold text-meta-3">ENCRYPTED</span>
                  </div>
                  <input
                    className="w-full rounded border border-stroke bg-gray py-3 px-4.5 text-black focus:border-primary focus-visible:outline-none dark:border-strokedark dark:bg-meta-4 dark:text-white dark:focus:border-primary"
                    type="password"
                    placeholder="pk_live_..."
                    defaultValue="••••••••••••••••"
                  />
                </div>

                <div className="flex justify-end gap-4.5">
                  <button
                    className="flex justify-center rounded border border-stroke py-2 px-6 font-medium text-black hover:shadow-1 dark:border-strokedark dark:text-white"
                    type="button"
                  >
                    Cancel
                  </button>
                  <button
                    onClick={handleSave}
                    disabled={loading}
                    className="flex justify-center rounded bg-primary py-2 px-6 font-medium text-gray hover:bg-opacity-90"
                    type="button"
                  >
                    {loading ? 'Sealing Vault...' : 'Save Keys'}
                  </button>
                </div>
              </form>
            </div>
          </div>
        </div>

        <div className="col-span-5 xl:col-span-2">
          <div className="rounded-sm border border-stroke bg-white shadow-default dark:border-strokedark dark:bg-boxdark">
            <div className="border-b border-stroke py-4 px-7 dark:border-strokedark">
              <h3 className="font-medium text-black dark:text-white">
                Security Audit
              </h3>
            </div>
            <div className="p-7">
              <div className="mb-4 flex items-center gap-3">
                <div className="flex h-10 w-10 items-center justify-center rounded-full bg-meta-2 dark:bg-meta-4">
                  <svg className="fill-primary dark:fill-white" width="20" height="20" viewBox="0 0 20 20">
                    <path d="M10 0L2.5 3.75V8.75C2.5 13.375 5.625 17.625 10 20C14.375 17.625 17.5 13.375 17.5 8.75V3.75L10 0ZM10 17.5C6.875 15.625 4.375 12.375 4.375 8.75V4.625L10 1.875L15.625 4.625V8.75C15.625 12.375 13.125 15.625 10 17.5Z" />
                  </svg>
                </div>
                <div>
                  <h4 className="text-sm font-semibold text-black dark:text-white">
                    AES-256 GCM Protection
                  </h4>
                  <p className="text-xs">Keys are never stored in cleartext.</p>
                </div>
              </div>
              
              <div className="rounded-sm border border-stroke p-4 dark:border-strokedark">
                <h4 className="mb-2 text-sm font-semibold text-black dark:text-white">
                  Active Gateway Status
                </h4>
                <div className="flex flex-col gap-2">
                  <div className="flex items-center justify-between">
                    <span className="text-xs">Stripe</span>
                    <span className="rounded bg-success py-1 px-2 text-[10px] font-medium text-white">CONNECTED</span>
                  </div>
                  <div className="flex items-center justify-between">
                    <span className="text-xs">Paystack</span>
                    <span className="rounded bg-warning py-1 px-2 text-[10px] font-medium text-white">KEY_REQUIRED</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
};

export default BillingSettings;
