'use client';

import React, { useState, useEffect } from 'react';
import { toast } from 'sonner';
import { Building2, Snowflake, Flame, ShieldAlert } from 'lucide-react';
import api from '@/lib/api';

const OrganizationsPage = () => {
  const [orgs, setOrgs] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  const fetchOrgs = async () => {
    setLoading(true);
    try {
      const { data } = await api.get('/api/v1/developer/organizations');
      setOrgs(data.data);
    } catch (err) {
      toast.error('Failed to retrieve organization grid');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchOrgs();
  }, []);

  const handleToggle = async (id: number, currentStatus: boolean) => {
    const action = currentStatus ? 'FREEZE' : 'UNFREEZE';
    if (!confirm(`SOVEREIGN OVERRIDE: Are you sure you want to ${action} this branch?`)) return;

    try {
      await api.post(`/api/v1/developer/organizations/${id}/toggle-status`);
      toast.success(`Sovereign Override Successful: Branch ${action}D`);
      fetchOrgs();
    } catch (err) {
      toast.error('Override rejected: Critical system failure');
    }
  };

  return (
    <div className="mx-auto max-w-270">
      <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h2 className="text-title-md2 font-semibold text-black dark:text-white">
          SaaS Organization Control
        </h2>
      </div>

      {loading ? (
        <div className="py-24 text-center">Scanning Organization DNA...</div>
      ) : (
        <div className="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">
          {orgs.map((org) => (
            <div key={org.id} className="rounded-sm border border-stroke bg-white p-6 shadow-default dark:border-strokedark dark:bg-boxdark">
              <div className="flex items-center justify-between mb-4">
                <div className="flex h-12 w-12 items-center justify-center rounded-full bg-primary/10">
                  <Building2 className="text-primary h-6 w-6" />
                </div>
                <span className={`text-[10px] font-bold px-2 py-1 rounded uppercase tracking-widest ${
                  org.is_active ? 'bg-success/10 text-success' : 'bg-red-500/10 text-red-500'
                }`}>
                  {org.is_active ? 'Operational' : 'Frozen'}
                </span>
              </div>
              
              <h3 className="mb-1 text-xl font-bold text-black dark:text-white">
                {org.name}
              </h3>
              <p className="text-sm text-gray-500 mb-6">{org.contact_email}</p>

              <div className="space-y-4">
                <h4 className="text-xs font-semibold text-gray-400 uppercase tracking-tighter">Branches</h4>
                {org.branches?.map((branch: any) => (
                  <div key={branch.id} className="flex items-center justify-between p-3 rounded bg-gray-50 dark:bg-meta-4">
                    <div className="flex flex-col">
                      <span className="text-sm font-bold">{branch.name}</span>
                      <span className="text-[10px] opacity-50">{branch.domain}</span>
                    </div>
                    <button
                      onClick={() => handleToggle(branch.id, branch.is_active)}
                      className={`p-2 rounded-full transition-all ${
                        branch.is_active 
                          ? 'bg-red-500/10 text-red-500 hover:bg-red-500 hover:text-white' 
                          : 'bg-success/10 text-success hover:bg-success hover:text-white'
                      }`}
                      title={branch.is_active ? 'Freeze Branch' : 'Unfreeze Branch'}
                    >
                      {branch.is_active ? <Snowflake size={16} /> : <Flame size={16} />}
                    </button>
                  </div>
                ))}
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
};

export default OrganizationsPage;
