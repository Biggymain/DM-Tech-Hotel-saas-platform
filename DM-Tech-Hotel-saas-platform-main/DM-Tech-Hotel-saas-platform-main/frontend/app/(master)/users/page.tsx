'use client';

import React, { useState, useEffect } from 'react';
import axios from 'axios';
import { toast } from 'sonner';
import { Modal } from '@/components/TailAdmin/ui/modal';
import TailButton from '@/components/TailAdmin/ui/button';
import TailInput from '@/components/TailAdmin/ui/input';
import api from '@/lib/api';

const MasterUsersPage = () => {
  const [activeTab, setActiveTab] = useState('active'); // 'active' or 'pending'
  const [users, setUsers] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [isModalOpen, setIsModalOpen] = useState(false);
  const [formData, setFormData] = useState({ name: '', email: '', password: '' });

  const fetchUsers = async () => {
    setLoading(true);
    try {
      const { data } = await api.get('/api/v1/developer/users');
      setUsers(data.data);
    } catch (err) {
      toast.error('Failed to fetch master users');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchUsers();
  }, []);

  const handleCreate = async () => {
    try {
      await api.post('/api/v1/developer/users', formData);
      toast.success('User placed in the Waiting Room');
      setIsModalOpen(false);
      setFormData({ name: '', email: '', password: '' });
      fetchUsers();
    } catch (err) {
      toast.error('Failed to create user');
    }
  };

  const handleApprove = async (id: number) => {
    try {
      await api.patch(`/api/v1/developer/users/${id}/approve`);
      toast.success('User Identity Authorized');
      fetchUsers();
    } catch (err) {
      toast.error('Approval failed');
    }
  };

  const handleDelete = async (id: number) => {
    if (id === 1) {
      toast.error('ROOT_IMMUNITY_VIOLATION: Cannot remove the primary admin');
      return;
    }
    if (!confirm('Are you sure you want to remove this user from the Fortress?')) return;
    
    try {
      await api.delete(`/api/v1/developer/users/${id}`);
      toast.success('User removed');
      fetchUsers();
    } catch (err) {
      toast.error('Deletion failed');
    }
  };

  const filteredUsers = users.filter(u => 
    activeTab === 'active' ? u.is_approved : !u.is_approved
  );

  return (
    <div className="mx-auto max-w-270">
      <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h2 className="text-title-md2 font-semibold text-black dark:text-white">
          Master Identity Management
        </h2>
        <TailButton onClick={() => setIsModalOpen(true)}>
          Add New Master Admin
        </TailButton>
      </div>

      <div className="mb-6 flex border-b border-stroke dark:border-strokedark">
        <button
          onClick={() => setActiveTab('active')}
          className={`py-4 px-6 text-sm font-medium ${
            activeTab === 'active' ? 'border-b-2 border-primary text-primary' : 'text-gray-500'
          }`}
        >
          Active Admins
        </button>
        <button
          onClick={() => setActiveTab('pending')}
          className={`py-4 px-6 text-sm font-medium flex items-center gap-2 ${
            activeTab === 'pending' ? 'border-b-2 border-primary text-primary' : 'text-gray-500'
          }`}
        >
          Waiting Room
          {users.filter(u => !u.is_approved).length > 0 && (
            <span className="bg-red-500 text-white text-[10px] px-1.5 py-0.5 rounded-full">
              {users.filter(u => !u.is_approved).length}
            </span>
          )}
        </button>
      </div>

      <div className="rounded-sm border border-stroke bg-white shadow-default dark:border-strokedark dark:bg-boxdark">
        <div className="max-w-full overflow-x-auto">
          <table className="w-full table-auto">
            <thead>
              <tr className="bg-gray-2 text-left dark:bg-meta-4">
                <th className="py-4 px-4 font-medium text-black dark:text-white">Name</th>
                <th className="py-4 px-4 font-medium text-black dark:text-white">Email</th>
                <th className="py-4 px-4 font-medium text-black dark:text-white">Status</th>
                <th className="py-4 px-4 font-medium text-black dark:text-white text-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              {filteredUsers.map((user) => (
                <tr key={user.id} className="border-b border-stroke dark:border-strokedark">
                  <td className="py-5 px-4">
                    <p className="text-black dark:text-white">{user.name}</p>
                    {user.id === 1 && <span className="text-[10px] bg-primary text-white px-1.5 rounded">ROOT</span>}
                  </td>
                  <td className="py-5 px-4">{user.email}</td>
                  <td className="py-5 px-4">
                    <span className={`inline-flex rounded-full py-1 px-3 text-xs font-medium ${
                      user.is_approved ? 'bg-success/10 text-success' : 'bg-warning/10 text-warning'
                    }`}>
                      {user.is_approved ? 'Authorized' : 'Pending Approval'}
                    </span>
                  </td>
                  <td className="py-5 px-4 text-right">
                    <div className="flex justify-end gap-3">
                      {!user.is_approved && (
                        <button 
                          onClick={() => handleApprove(user.id)}
                          className="text-success hover:underline text-sm"
                        >
                          Approve
                        </button>
                      )}
                      {user.id !== 1 && (
                        <button 
                          onClick={() => handleDelete(user.id)}
                          className="text-red-500 hover:underline text-sm"
                        >
                          Revoke
                        </button>
                      )}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
          {loading && <div className="p-8 text-center">Scanning Digital Fortress...</div>}
          {!loading && filteredUsers.length === 0 && (
            <div className="p-8 text-center text-gray-500">No users found in this sector.</div>
          )}
        </div>
      </div>

      <Modal
        isOpen={isModalOpen}
        onClose={() => setIsModalOpen(false)}
        className="max-w-[500px] p-8"
      >
        <h3 className="text-xl font-bold text-black dark:text-white mb-4">
          Add Master Admin
        </h3>
        <div className="space-y-4">
          <div>
            <label className="block text-sm font-medium mb-1">Name</label>
            <TailInput 
              value={formData.name}
              onChange={(e: any) => setFormData({...formData, name: e.target.value})}
            />
          </div>
          <div>
            <label className="block text-sm font-medium mb-1">Email</label>
            <TailInput 
              value={formData.email}
              onChange={(e: any) => setFormData({...formData, email: e.target.value})}
            />
          </div>
          <div>
            <label className="block text-sm font-medium mb-1">Temporary Password</label>
            <TailInput 
              type="password"
              value={formData.password}
              onChange={(e: any) => setFormData({...formData, password: e.target.value})}
            />
          </div>
        </div>
        <div className="flex justify-end gap-3 mt-8">
          <TailButton variant="outline" onClick={() => setIsModalOpen(false)}>Cancel</TailButton>
          <TailButton onClick={handleCreate}>Create & Seal</TailButton>
        </div>
      </Modal>
    </div>
  );
};

export default MasterUsersPage;
