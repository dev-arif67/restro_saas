import React, { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { tableAPI } from '../../services/api';
import LoadingSpinner from '../../components/ui/LoadingSpinner';
import StatusBadge from '../../components/ui/StatusBadge';
import Modal from '../../components/ui/Modal';
import toast from 'react-hot-toast';
import QRCode from 'react-qr-code';

export default function TablesPage() {
    const queryClient = useQueryClient();
    const [showForm, setShowForm] = useState(false);
    const [showTransfer, setShowTransfer] = useState(false);
    const [showQr, setShowQr] = useState(null);
    const [editing, setEditing] = useState(null);

    const { data: tables, isLoading } = useQuery({
        queryKey: ['tables'],
        queryFn: () => tableAPI.list().then((r) => r.data.data),
    });

    const saveMutation = useMutation({
        mutationFn: (data) => editing ? tableAPI.update(editing.id, data) : tableAPI.create(data),
        onSuccess: () => {
            queryClient.invalidateQueries(['tables']);
            setShowForm(false);
            setEditing(null);
            toast.success(editing ? 'Updated' : 'Table created');
        },
        onError: (err) => toast.error(err.response?.data?.message || 'Error'),
    });

    const transferMutation = useMutation({
        mutationFn: (data) => tableAPI.transfer(data),
        onSuccess: () => {
            queryClient.invalidateQueries(['tables']);
            setShowTransfer(false);
            toast.success('Table transferred!');
        },
        onError: (err) => toast.error(err.response?.data?.message || 'Transfer failed'),
    });

    const deleteMutation = useMutation({
        mutationFn: (id) => tableAPI.delete(id),
        onSuccess: () => {
            queryClient.invalidateQueries(['tables']);
            toast.success('Table deleted');
        },
        onError: (err) => toast.error(err.response?.data?.message || 'Error'),
    });

    const handleShowQr = async (table) => {
        try {
            const { data } = await tableAPI.qrCode(table.id);
            setShowQr({ ...table, qrUrl: data.data.qr_url });
        } catch {
            toast.error('Failed to generate QR');
        }
    };

    if (isLoading) return <LoadingSpinner />;

    return (
        <div>
            <div className="flex items-center justify-between mb-6">
                <h2 className="text-xl sm:text-2xl font-bold text-gray-800">Tables</h2>
                <div className="flex gap-2">
                    <button onClick={() => setShowTransfer(true)} className="btn-secondary text-sm sm:text-base">Transfer</button>
                    <button onClick={() => { setEditing(null); setShowForm(true); }} className="btn-primary text-sm sm:text-base">+ Add Table</button>
                </div>
            </div>

            <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
                {tables?.map((table) => (
                    <div key={table.id} className={`card text-center cursor-pointer hover:shadow-md transition ${table.status === 'occupied' ? 'border-red-200 bg-red-50' : ''}`}>
                        <p className="text-2xl font-bold text-gray-800">{table.table_number}</p>
                        <StatusBadge status={table.status} />
                        <p className="text-xs text-gray-400 mt-2">Cap: {table.capacity}</p>
                        {table.active_orders_count > 0 && (
                            <p className="text-xs text-red-600 mt-1">{table.active_orders_count} active orders</p>
                        )}
                        <div className="flex justify-center gap-2 mt-3 pt-3 border-t">
                            <button onClick={() => handleShowQr(table)} className="text-xs text-blue-600">QR</button>
                            <button onClick={() => { setEditing(table); setShowForm(true); }} className="text-xs text-gray-600">Edit</button>
                            <button onClick={() => { if (confirm('Delete?')) deleteMutation.mutate(table.id); }} className="text-xs text-red-600">Del</button>
                        </div>
                    </div>
                ))}
            </div>

            {/* Create/Edit Form */}
            <Modal isOpen={showForm} onClose={() => setShowForm(false)} title={editing ? 'Edit Table' : 'Add Table'}>
                <form onSubmit={(e) => { e.preventDefault(); const d = Object.fromEntries(new FormData(e.target)); saveMutation.mutate(d); }} className="space-y-4">
                    <div>
                        <label className="label">Table Number</label>
                        <input name="table_number" className="input" defaultValue={editing?.table_number} required />
                    </div>
                    <div>
                        <label className="label">Capacity</label>
                        <input name="capacity" type="number" className="input" defaultValue={editing?.capacity || 4} min={1} />
                    </div>
                    <div className="flex gap-3">
                        <button type="submit" className="btn-primary" disabled={saveMutation.isPending}>Save</button>
                        <button type="button" onClick={() => setShowForm(false)} className="btn-secondary">Cancel</button>
                    </div>
                </form>
            </Modal>

            {/* Transfer Modal */}
            <Modal isOpen={showTransfer} onClose={() => setShowTransfer(false)} title="Transfer Table">
                <form onSubmit={(e) => { e.preventDefault(); const d = Object.fromEntries(new FormData(e.target)); transferMutation.mutate(d); }} className="space-y-4">
                    <div>
                        <label className="label">From Table</label>
                        <select name="from_table_id" className="input" required>
                            <option value="">Select...</option>
                            {tables?.filter((t) => t.status === 'occupied').map((t) => (
                                <option key={t.id} value={t.id}>{t.table_number}</option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <label className="label">To Table</label>
                        <select name="to_table_id" className="input" required>
                            <option value="">Select...</option>
                            {tables?.filter((t) => t.status === 'available').map((t) => (
                                <option key={t.id} value={t.id}>{t.table_number}</option>
                            ))}
                        </select>
                    </div>
                    <div className="flex gap-3">
                        <button type="submit" className="btn-primary" disabled={transferMutation.isPending}>Transfer</button>
                        <button type="button" onClick={() => setShowTransfer(false)} className="btn-secondary">Cancel</button>
                    </div>
                </form>
            </Modal>

            {/* QR Modal */}
            <Modal isOpen={!!showQr} onClose={() => setShowQr(null)} title={`QR Code - ${showQr?.table_number}`}>
                <div className="text-center py-4">
                    {showQr?.qrUrl && (
                        <div className="inline-block p-4 bg-white rounded-lg">
                            <QRCode value={showQr.qrUrl} size={200} />
                        </div>
                    )}
                    <p className="text-sm text-gray-500 mt-4">Scan to order from {showQr?.table_number}</p>
                    <p className="text-xs text-gray-400 mt-2 break-all">{showQr?.qrUrl}</p>
                </div>
            </Modal>
        </div>
    );
}
