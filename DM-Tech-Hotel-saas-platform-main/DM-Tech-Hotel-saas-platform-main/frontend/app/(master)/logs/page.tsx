'use client';

import React, { useState, useEffect } from 'react';
import { toast } from 'sonner';
import api from '@/lib/api';

const SiemLogsPage = () => {
  const [logs, setLogs] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  const fetchLogs = async () => {
    setLoading(true);
    try {
      const { data } = await api.get('/api/v1/siem/alerts');
      setLogs(data.data);
    } catch (err) {
      toast.error('SIEM scan failed: Connection timeout');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchLogs();
    const interval = setInterval(fetchLogs, 30000); // Auto-refresh every 30s
    return () => clearInterval(interval);
  }, []);

  const handleBan = async (hardwareId: string) => {
    if (hardwareId === 'Unknown') {
      toast.error('Cannot ban an unknown hardware ID');
      return;
    }
    if (!confirm(`WARROOM ACTION: Are you sure you want to PERMANENTLY BAN device [${hardwareId}]?`)) return;

    try {
      await api.post('/api/v1/siem/ban-hardware', { hardware_id: hardwareId });
      toast.success('ACTIVE RESPONSE: Hardware Locked in Supabase Licensing Hub');
      fetchLogs();
    } catch (err) {
      toast.error('Active response failed: Sovereign override rejected');
    }
  };

  const getSeverityColor = (score: number) => {
    if (score >= 10) return 'text-red-500 bg-red-500/10 border-red-500/20';
    if (score >= 5) return 'text-yellow-500 bg-yellow-500/10 border-yellow-500/20';
    return 'text-blue-500 bg-blue-500/10 border-blue-500/20';
  };

  return (
    <div className="mx-auto max-w-270">
      <div className="mb-6 flex items-center justify-between">
        <h2 className="text-title-md2 font-semibold text-black dark:text-white">
          SIEM Watchdog Feed
        </h2>
        <span className="flex h-3 w-3">
          <span className="animate-ping absolute inline-flex h-3 w-3 rounded-full bg-red-400 opacity-75"></span>
          <span className="relative inline-flex rounded-full h-3 w-3 bg-red-500"></span>
        </span>
      </div>

      <div className="rounded-sm border border-stroke bg-white shadow-default dark:border-strokedark dark:bg-boxdark">
        <div className="max-w-full overflow-x-auto">
          <table className="w-full table-auto">
            <thead>
              <tr className="bg-gray-2 text-left dark:bg-meta-4">
                <th className="py-4 px-4 font-medium text-black dark:text-white">Incident</th>
                <th className="py-4 px-4 font-medium text-black dark:text-white">User / Identity</th>
                <th className="py-4 px-4 font-medium text-black dark:text-white">Hardware Hash</th>
                <th className="py-4 px-4 font-medium text-black dark:text-white">Severity</th>
                <th className="py-4 px-4 font-medium text-black dark:text-white text-right">Active Response</th>
              </tr>
            </thead>
            <tbody>
              {logs.map((log) => (
                <tr key={log.id} className="border-b border-stroke dark:border-strokedark hover:bg-gray-50 dark:hover:bg-meta-4/20 transition-colors">
                  <td className="py-5 px-4">
                    <p className="text-sm font-medium text-black dark:text-white">{log.message}</p>
                    <p className="text-[10px] text-gray-500 mt-1 uppercase">{new Date(log.created_at).toLocaleString()}</p>
                  </td>
                  <td className="py-5 px-4 text-sm">
                    {log.user} <span className="text-xs text-gray-400 opacity-50">(ID: {log.user_id || 'N/A'})</span>
                  </td>
                  <td className="py-5 px-4">
                    <code className="text-xs font-mono bg-gray-100 dark:bg-gray-800 p-1 rounded">
                      {log.hardware_id}
                    </code>
                  </td>
                  <td className="py-5 px-4">
                    <span className={`inline-flex rounded border py-1 px-2 text-xs font-black ${getSeverityColor(log.severity)}`}>
                      LVL {log.severity}
                    </span>
                  </td>
                  <td className="py-5 px-4 text-right">
                    <button 
                      onClick={() => handleBan(log.hardware_id)}
                      className="bg-red-600 hover:bg-red-700 text-white text-[10px] font-bold py-1 px-3 rounded shadow-sm transition-all"
                    >
                      BAN DEVICE
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
          {loading && <div className="p-8 text-center text-sm">Synchronizing with Security Sentry...</div>}
          {!loading && logs.length === 0 && (
            <div className="p-12 text-center">
              <p className="text-gray-500 font-medium">No active threats detected. Fortress is Secure.</p>
            </div>
          )}
        </div>
      </div>
    </div>
  );
};

export default SiemLogsPage;
