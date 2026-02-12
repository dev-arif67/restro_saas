import React, { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { adminAPI } from '../../services/api';
import LoadingSpinner from '../../components/ui/LoadingSpinner';
import StatusBadge from '../../components/ui/StatusBadge';
import Modal from '../../components/ui/Modal';
import toast from 'react-hot-toast';

export default function TenantsPage() {
    const queryClient = useQueryClient();
    const [showOnboard, setShowOnboard] = useState(false);

    const { data, isLoading } = useQuery({
        queryKey: ['admin-tenants'],
        queryFn: () => adminAPI.tenants.list().then((r) => r.data.data),
    });

    const onboardMutation = useMutation({
        mutationFn: (data) => adminAPI.tenants.create(data),
        onSuccess: () => {
            queryClient.invalidateQueries(['admin-tenants']);
            setShowOnboard(false);
            toast.success('Tenant onboarded!');
        },
        onError: (err) => toast.error(err.response?.data?.message || 'Error'),
    });

    const toggleMutation = useMutation({
        mutationFn: ({ id, is_active }) => adminAPI.tenants.update(id, { is_active }),
        onSuccess: () => {
            queryClient.invalidateQueries(['admin-tenants']);
            toast.success('Updated');
        },
    });

    const handleOnboard = (e) => {
        e.preventDefault();
        const d = Object.fromEntries(new FormData(e.target));
        d.commission_rate = parseFloat(d.commission_rate || 5);
        onboardMutation.mutate(d);
    };

    if (isLoading) return <LoadingSpinner />;

    return (
        <div>
            <div className="flex items-center justify-between mb-6">
                <h2 className="text-xl sm:text-2xl font-bold text-gray-800">Tenants</h2>
                <button onClick={() => setShowOnboard(true)} className="btn-primary text-sm sm:text-base">+ Onboard</button>
            </div>

            {/* Desktop Table */}
            <div className="hidden md:block card overflow-x-auto">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="text-left border-b">
                            <th className="pb-3">Name</th><th className="pb-3">Slug</th><th className="pb-3">Email</th>
                            <th className="pb-3">Payment</th><th className="pb-3">Commission</th><th className="pb-3">Status</th><th className="pb-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        {data?.map((t) => (
                            <tr key={t.id} className="border-b last:border-0">
                                <td className="py-3 font-medium">{t.name}</td>
                                <td className="py-3 font-mono text-sm">{t.slug}</td>
                                <td className="py-3">{t.email}</td>
                                <td className="py-3 capitalize">{t.payment_mode}</td>
                                <td className="py-3">{t.commission_rate}%</td>
                                <td className="py-3"><StatusBadge status={t.is_active ? 'active' : 'inactive'} /></td>
                                <td className="py-3">
                                    <button
                                        onClick={() => toggleMutation.mutate({ id: t.id, is_active: !t.is_active })}
                                        className={`text-sm ${t.is_active ? 'text-red-600' : 'text-green-600'}`}
                                    >
                                        {t.is_active ? 'Deactivate' : 'Activate'}
                                    </button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {/* Mobile Cards */}
            <div className="md:hidden space-y-3">
                {data?.map((t) => (
                    <div key={t.id} className="card">
                        <div className="flex items-start justify-between gap-2 mb-2">
                            <div className="min-w-0">
                                <p className="font-semibold text-gray-900 truncate">{t.name}</p>
                                <p className="text-xs text-gray-500 font-mono">{t.slug}</p>
                            </div>
                            <StatusBadge status={t.is_active ? 'active' : 'inactive'} />
                        </div>
                        <p className="text-sm text-gray-500 truncate">{t.email}</p>
                        <div className="flex items-center justify-between mt-3 pt-3 border-t text-sm">
                            <div className="flex gap-3 text-gray-500">
                                <span className="capitalize">{t.payment_mode}</span>
                                <span>{t.commission_rate}%</span>
                            </div>
                            <button
                                onClick={() => toggleMutation.mutate({ id: t.id, is_active: !t.is_active })}
                                className={`text-sm font-medium ${t.is_active ? 'text-red-600' : 'text-green-600'}`}
                            >
                                {t.is_active ? 'Deactivate' : 'Activate'}
                            </button>
                        </div>
                    </div>
                ))}
            </div>

            {/* Onboard Modal */}
            <Modal isOpen={showOnboard} onClose={() => setShowOnboard(false)} title="Onboard Tenant" size="lg">
                <form onSubmit={handleOnboard} className="space-y-4">
                    <h4 className="font-medium text-gray-700">Restaurant Info</h4>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div><label className="label">Restaurant Name</label><input name="tenant_name" className="input" required /></div>
                        <div><label className="label">Slug</label><input name="tenant_slug" className="input" required /></div>
                    </div>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div><label className="label">Email</label><input name="tenant_email" type="email" className="input" required /></div>
                        <div><label className="label">Phone</label><input name="tenant_phone" className="input" /></div>
                    </div>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label className="label">Payment Mode</label>
                            <select name="payment_mode" className="input">
                                <option value="seller">Seller Collects</option>
                                <option value="platform">Platform Collects</option>
                            </select>
                        </div>
                        <div><label className="label">Commission %</label><input name="commission_rate" type="number" step="0.01" className="input" defaultValue="5" /></div>
                    </div>

                    <hr />
                    <h4 className="font-medium text-gray-700">Admin User</h4>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div><label className="label">Admin Name</label><input name="admin_name" className="input" required /></div>
                        <div><label className="label">Admin Email</label><input name="admin_email" type="email" className="input" required /></div>
                    </div>
                    <div><label className="label">Admin Password</label><input name="admin_password" type="password" className="input" required minLength={8} /></div>

                    <hr />
                    <h4 className="font-medium text-gray-700">Subscription</h4>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label className="label">Plan</label>
                            <select name="plan_type" className="input">
                                <option value="monthly">Monthly</option>
                                <option value="yearly">Yearly</option>
                            </select>
                        </div>
                        <div><label className="label">Amount</label><input name="amount" type="number" className="input" /></div>
                    </div>

                    <div className="flex gap-3 pt-2">
                        <button type="submit" className="btn-primary" disabled={onboardMutation.isPending}>
                            {onboardMutation.isPending ? 'Creating...' : 'Onboard'}
                        </button>
                        <button type="button" onClick={() => setShowOnboard(false)} className="btn-secondary">Cancel</button>
                    </div>
                </form>
            </Modal>
        </div>
    );
}
