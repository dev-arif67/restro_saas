import React, { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { userAPI } from '../../services/api';
import LoadingSpinner from '../../components/ui/LoadingSpinner';
import Modal from '../../components/ui/Modal';
import StatusBadge from '../../components/ui/StatusBadge';
import toast from 'react-hot-toast';

export default function UsersPage() {
    const queryClient = useQueryClient();
    const [showForm, setShowForm] = useState(false);
    const [editing, setEditing] = useState(null);

    const { data, isLoading } = useQuery({
        queryKey: ['users'],
        queryFn: () => userAPI.list().then((r) => r.data.data),
    });

    const saveMutation = useMutation({
        mutationFn: (data) => editing ? userAPI.update(editing.id, data) : userAPI.create(data),
        onSuccess: () => {
            queryClient.invalidateQueries(['users']);
            setShowForm(false);
            setEditing(null);
            toast.success(editing ? 'Updated' : 'Created');
        },
        onError: (err) => toast.error(err.response?.data?.message || 'Error'),
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        const d = Object.fromEntries(new FormData(e.target));
        if (!d.password && editing) delete d.password;
        saveMutation.mutate(d);
    };

    if (isLoading) return <LoadingSpinner />;

    return (
        <div>
            <div className="flex items-center justify-between mb-6">
                <h2 className="text-xl sm:text-2xl font-bold text-gray-800">Team Members</h2>
                <button onClick={() => { setEditing(null); setShowForm(true); }} className="btn-primary text-sm sm:text-base">+ Add User</button>
            </div>

            {/* Desktop Table */}
            <div className="hidden md:block card overflow-x-auto">
                <table className="w-full text-sm">
                    <thead><tr className="text-left border-b"><th className="pb-3">Name</th><th className="pb-3">Email</th><th className="pb-3">Role</th><th className="pb-3">Status</th><th className="pb-3"></th></tr></thead>
                    <tbody>
                        {data?.map((user) => (
                            <tr key={user.id} className="border-b last:border-0">
                                <td className="py-3 font-medium">{user.name}</td>
                                <td className="py-3">{user.email}</td>
                                <td className="py-3 capitalize">{user.role.replace('_', ' ')}</td>
                                <td className="py-3"><StatusBadge status={user.status} /></td>
                                <td className="py-3">
                                    <button onClick={() => { setEditing(user); setShowForm(true); }} className="text-blue-600 text-sm">Edit</button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {/* Mobile Cards */}
            <div className="md:hidden space-y-3">
                {data?.map((user) => (
                    <div key={user.id} className="card">
                        <div className="flex items-start justify-between gap-2">
                            <div className="flex items-center gap-3 min-w-0">
                                <div className="w-9 h-9 bg-blue-600 rounded-full flex items-center justify-center text-white text-sm font-medium shrink-0">
                                    {user.name?.[0]?.toUpperCase()}
                                </div>
                                <div className="min-w-0">
                                    <p className="font-medium text-gray-900 truncate">{user.name}</p>
                                    <p className="text-sm text-gray-500 truncate">{user.email}</p>
                                </div>
                            </div>
                            <StatusBadge status={user.status} />
                        </div>
                        <div className="flex items-center justify-between mt-3 pt-3 border-t">
                            <span className="text-sm text-gray-500 capitalize">{user.role.replace('_', ' ')}</span>
                            <button onClick={() => { setEditing(user); setShowForm(true); }} className="text-blue-600 text-sm font-medium">Edit</button>
                        </div>
                    </div>
                ))}
            </div>

            <Modal isOpen={showForm} onClose={() => setShowForm(false)} title={editing ? 'Edit User' : 'Add User'}>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div>
                        <label className="label">Name</label>
                        <input name="name" className="input" defaultValue={editing?.name} required />
                    </div>
                    <div>
                        <label className="label">Email</label>
                        <input name="email" type="email" className="input" defaultValue={editing?.email} required />
                    </div>
                    <div>
                        <label className="label">Password {editing && '(leave blank to keep)'}</label>
                        <input name="password" type="password" className="input" {...(!editing && { required: true })} minLength={8} />
                    </div>
                    <div>
                        <label className="label">Role</label>
                        <select name="role" className="input" defaultValue={editing?.role || 'staff'}>
                            <option value="restaurant_admin">Restaurant Admin</option>
                            <option value="staff">Staff / Waiter</option>
                            <option value="kitchen">Kitchen</option>
                        </select>
                    </div>
                    <div className="flex gap-3">
                        <button type="submit" className="btn-primary" disabled={saveMutation.isPending}>Save</button>
                        <button type="button" onClick={() => setShowForm(false)} className="btn-secondary">Cancel</button>
                    </div>
                </form>
            </Modal>
        </div>
    );
}
