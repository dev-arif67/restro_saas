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
    const [selectedTenant, setSelectedTenant] = useState('');
    const [showRenew, setShowRenew] = useState(false);
    const [renewTenant, setRenewTenant] = useState(null);
    const [selectedPlan, setSelectedPlan] = useState(null);

    const { data, isLoading } = useQuery({
        queryKey: ['admin-subscriptions'],
        queryFn: () => adminAPI.subscriptions.list().then((r) => r.data.data),
    });

    const { data: tenants } = useQuery({
        queryKey: ['admin-tenants-list'],
        queryFn: () => adminAPI.tenants.list().then((r) => r.data.data),
    });

    const { data: plans } = useQuery({
        queryKey: ['admin-plans'],
        queryFn: () => adminAPI.plans.list().then((r) => r.data.data),
    });

    const createMutation = useMutation({
        mutationFn: (data) => adminAPI.subscriptions.create(data),
        onSuccess: () => {
            queryClient.invalidateQueries(['admin-subscriptions']);
            setShowForm(false);
            setSelectedTenant('');
            toast.success('Subscription created');
        },
        onError: (err) => toast.error(err.response?.data?.message || 'Error'),
    });

    const renewMutation = useMutation({
        mutationFn: ({ tenantId, data }) => adminAPI.subscriptions.renew(tenantId, data),
        onSuccess: () => {
            queryClient.invalidateQueries(['admin-subscriptions']);
            setShowRenew(false);
            setRenewTenant(null);
            toast.success('Subscription renewed');
        },
        onError: (err) => toast.error(err.response?.data?.message || 'Error'),
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        const d = Object.fromEntries(new FormData(e.target));
        const plan = plans?.find((p) => p.id === parseInt(d.plan_id));
        createMutation.mutate({
            tenant_id: selectedTenant,
            plan_type: plan?.slug || d.plan_id,
            amount: plan ? parseFloat(plan.price) : parseFloat(d.amount),
            starts_at: d.starts_at,
            expires_at: d.starts_at ? new Date(new Date(d.starts_at).getTime() + (plan?.duration_days || 30) * 86400000).toISOString().split('T')[0] : undefined,
        });
    };

    const handleRenew = (e) => {
        e.preventDefault();
        const d = Object.fromEntries(new FormData(e.target));
        const plan = plans?.find((p) => p.id === parseInt(d.plan_id));
        renewMutation.mutate({
            tenantId: renewTenant.tenant_id || renewTenant.id,
            data: {
                plan_id: plan?.id || null,
                plan_type: plan?.slug || 'monthly',
                payment_method: d.payment_method || 'manual',
                payment_ref: d.payment_ref || null,
                notes: d.notes || null,
            },
        });
    };

    const openRenew = (subscription) => {
        setRenewTenant(subscription);
        setShowRenew(true);
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
                            <th className="pb-3">Start</th><th className="pb-3">Expires</th><th className="pb-3">Status</th><th className="pb-3"></th>
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
                                <td className="py-3">
                                    <button
                                        onClick={() => openRenew(s)}
                                        className="text-sm text-blue-600 hover:text-blue-800 font-medium"
                                    >
                                        Renew
                                    </button>
                                </td>
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
                        <div className="mt-2 pt-2 border-t">
                            <button
                                onClick={() => openRenew(s)}
                                className="text-sm text-blue-600 hover:text-blue-800 font-medium"
                            >
                                Renew
                            </button>
                        </div>
                    </div>
                ))}
            </div>

            {/* Add Subscription Modal */}
            <Modal isOpen={showForm} onClose={() => { setShowForm(false); setSelectedPlan(null); }} title="Add Subscription">
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div>
                        <label className="label">Tenant</label>
                        <select className="input" value={selectedTenant} onChange={(e) => setSelectedTenant(e.target.value)} required>
                            <option value="">Select a tenant</option>
                            {tenants?.map((t) => (
                                <option key={t.id} value={t.id}>{t.name} ({t.slug})</option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <label className="label">Plan</label>
                        <select name="plan_id" className="input" required onChange={(e) => setSelectedPlan(plans?.find((p) => p.id === parseInt(e.target.value)))}>
                            <option value="">Select a plan</option>
                            {plans?.map((p) => (
                                <option key={p.id} value={p.id}>{p.name} — ৳{Number(p.price).toLocaleString()} / {p.duration_days} days</option>
                            ))}
                        </select>
                    </div>
                    {selectedPlan && (
                        <div className="bg-gray-50 rounded-lg p-3 text-sm text-gray-600">
                            <p><span className="font-medium text-gray-900">Amount:</span> ৳{Number(selectedPlan.price).toLocaleString()}</p>
                            <p><span className="font-medium text-gray-900">Duration:</span> {selectedPlan.duration_days} days</p>
                        </div>
                    )}
                    <div>
                        <label className="label">Starts</label>
                        <input name="starts_at" type="date" className="input" defaultValue={new Date().toISOString().split('T')[0]} required />
                    </div>
                    <div className="flex gap-3">
                        <button type="submit" className="btn-primary" disabled={createMutation.isPending}>Save</button>
                        <button type="button" onClick={() => { setShowForm(false); setSelectedPlan(null); }} className="btn-secondary">Cancel</button>
                    </div>
                </form>
            </Modal>

            {/* Renew Subscription Modal */}
            <Modal isOpen={showRenew} onClose={() => { setShowRenew(false); setRenewTenant(null); }} title="Renew Subscription">
                {renewTenant && (
                    <form onSubmit={handleRenew} className="space-y-4">
                        <div className="bg-gray-50 rounded-lg p-3 text-sm">
                            <p className="font-medium text-gray-900">{renewTenant.tenant?.name}</p>
                            <p className="text-gray-500">Current: <span className="capitalize">{renewTenant.plan_type}</span> — Expires {new Date(renewTenant.expires_at).toLocaleDateString()}</p>
                        </div>
                        <div>
                            <label className="label">New Plan</label>
                            <select name="plan_id" className="input" required>
                                <option value="">Select a plan</option>
                                {plans?.map((p) => (
                                    <option key={p.id} value={p.id}>{p.name} — ৳{Number(p.price).toLocaleString()} / {p.duration_days} days</option>
                                ))}
                            </select>
                        </div>
                        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label className="label">Payment Method</label>
                                <select name="payment_method" className="input">
                                    <option value="manual">Manual</option>
                                    <option value="bkash">bKash</option>
                                    <option value="bank">Bank Transfer</option>
                                </select>
                            </div>
                            <div>
                                <label className="label">Payment Ref</label>
                                <input name="payment_ref" className="input" placeholder="Transaction ID" />
                            </div>
                        </div>
                        <div>
                            <label className="label">Notes</label>
                            <input name="notes" className="input" placeholder="Optional notes" />
                        </div>
                        <div className="flex gap-3">
                            <button type="submit" className="btn-primary" disabled={renewMutation.isPending}>
                                {renewMutation.isPending ? 'Renewing...' : 'Renew'}
                            </button>
                            <button type="button" onClick={() => { setShowRenew(false); setRenewTenant(null); }} className="btn-secondary">Cancel</button>
                        </div>
                    </form>
                )}
            </Modal>
        </div>
    );
}
