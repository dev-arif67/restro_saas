import React, { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { adminAPI } from '../../services/api';
import LoadingSpinner from '../../components/ui/LoadingSpinner';
import StatusBadge from '../../components/ui/StatusBadge';
import Modal from '../../components/ui/Modal';
import toast from 'react-hot-toast';

export default function SubscriptionsPage() {
    const queryClient = useQueryClient();
    const [showForm, setShowForm] = useState(false);
    const [selectedTenant, setSelectedTenant] = useState(null);

    const { data, isLoading } = useQuery({
        queryKey: ['admin-subscriptions'],
        queryFn: () => adminAPI.subscriptions.list().then((r) => r.data.data),
    });

    const createMutation = useMutation({
        mutationFn: (data) => adminAPI.subscriptions.create(data),
        onSuccess: () => {
            queryClient.invalidateQueries(['admin-subscriptions']);
            setShowForm(false);
            toast.success('Subscription created');
        },
        onError: (err) => toast.error(err.response?.data?.message || 'Error'),
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        const d = Object.fromEntries(new FormData(e.target));
        d.tenant_id = selectedTenant;
        d.amount = parseFloat(d.amount);
        createMutation.mutate(d);
    };

    if (isLoading) return <LoadingSpinner />;

    return (
        <div>
            <div className="flex items-center justify-between mb-6">
                <h2 className="text-xl sm:text-2xl font-bold text-gray-800">Subscriptions</h2>
                <button onClick={() => setShowForm(true)} className="btn-primary text-sm sm:text-base">+ Add Subscription</button>
            </div>

            {/* Desktop Table */}
            <div className="hidden md:block card overflow-x-auto">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="text-left border-b">
                            <th className="pb-3">Tenant</th><th className="pb-3">Plan</th><th className="pb-3">Amount</th>
                            <th className="pb-3">Start</th><th className="pb-3">Expires</th><th className="pb-3">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        {data?.map((s) => (
                            <tr key={s.id} className="border-b last:border-0">
                                <td className="py-3 font-medium">{s.tenant?.name}</td>
                                <td className="py-3 capitalize">{s.plan_type}</td>
                                <td className="py-3">৳{s.amount}</td>
                                <td className="py-3">{new Date(s.starts_at).toLocaleDateString()}</td>
                                <td className="py-3">{new Date(s.expires_at).toLocaleDateString()}</td>
                                <td className="py-3"><StatusBadge status={s.status} /></td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {/* Mobile Cards */}
            <div className="md:hidden space-y-3">
                {data?.map((s) => (
                    <div key={s.id} className="card">
                        <div className="flex items-start justify-between gap-2 mb-2">
                            <p className="font-semibold text-gray-900">{s.tenant?.name}</p>
                            <StatusBadge status={s.status} />
                        </div>
                        <div className="flex items-center gap-3 text-sm text-gray-500 mb-2">
                            <span className="capitalize">{s.plan_type}</span>
                            <span className="font-medium text-gray-900">৳{s.amount}</span>
                        </div>
                        <div className="flex items-center justify-between text-xs text-gray-400 pt-2 border-t">
                            <span>Start: {new Date(s.starts_at).toLocaleDateString()}</span>
                            <span>Expires: {new Date(s.expires_at).toLocaleDateString()}</span>
                        </div>
                    </div>
                ))}
            </div>

            <Modal isOpen={showForm} onClose={() => setShowForm(false)} title="Add Subscription">
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div>
                        <label className="label">Tenant ID</label>
                        <input type="number" className="input" value={selectedTenant || ''} onChange={(e) => setSelectedTenant(e.target.value)} required />
                    </div>
                    <div>
                        <label className="label">Plan</label>
                        <select name="plan_type" className="input">
                            <option value="monthly">Monthly</option>
                            <option value="yearly">Yearly</option>
                            <option value="custom">Custom</option>
                        </select>
                    </div>
                    <div>
                        <label className="label">Amount</label>
                        <input name="amount" type="number" step="0.01" className="input" required />
                    </div>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label className="label">Starts</label>
                            <input name="starts_at" type="date" className="input" defaultValue={new Date().toISOString().split('T')[0]} required />
                        </div>
                        <div>
                            <label className="label">Expires</label>
                            <input name="expires_at" type="date" className="input" required />
                        </div>
                    </div>
                    <div className="flex gap-3">
                        <button type="submit" className="btn-primary" disabled={createMutation.isPending}>Save</button>
                        <button type="button" onClick={() => setShowForm(false)} className="btn-secondary">Cancel</button>
                    </div>
                </form>
            </Modal>
        </div>
    );
}
