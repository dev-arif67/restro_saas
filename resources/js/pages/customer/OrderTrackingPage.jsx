import React, { useState } from 'react';
import { useParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { orderAPI } from '../../services/api';
import LoadingSpinner from '../../components/ui/LoadingSpinner';
import StatusBadge from '../../components/ui/StatusBadge';
import POSInvoice from '../../components/POSInvoice';
import { HiOutlineDocumentText, HiOutlineCash, HiOutlineCreditCard } from 'react-icons/hi';

const STATUS_STEPS = ['placed', 'confirmed', 'preparing', 'ready', 'served', 'completed'];

export default function OrderTrackingPage() {
    const { orderNumber } = useParams();
    const [showInvoice, setShowInvoice] = useState(false);
    const [invoiceData, setInvoiceData] = useState(null);

    const { data, isLoading } = useQuery({
        queryKey: ['track-order', orderNumber],
        queryFn: () => orderAPI.track(orderNumber).then((r) => r.data.data),
        refetchInterval: 5000,
    });

    const handleViewInvoice = async () => {
        try {
            const res = await orderAPI.invoice(orderNumber);
            setInvoiceData(res.data.data);
            setShowInvoice(true);
        } catch {
            // fallback: use track data
            setInvoiceData({ order: data, restaurant: null });
            setShowInvoice(true);
        }
    };

    if (isLoading) return <LoadingSpinner fullScreen />;

    if (!data) {
        return (
            <div className="min-h-screen flex items-center justify-center bg-gray-50">
                <div className="text-center">
                    <p className="text-2xl font-bold text-gray-800">Order Not Found</p>
                    <p className="text-gray-500 mt-2">Check the order number and try again</p>
                </div>
            </div>
        );
    }

    const currentIdx = STATUS_STEPS.indexOf(data.status);
    const isPaid = data.payment_status === 'paid';

    return (
        <div className="min-h-screen bg-gray-50 py-8 px-4">
            <div className="max-w-md mx-auto">
                {/* Header */}
                <div className="text-center mb-8">
                    <h1 className="text-2xl font-bold text-gray-900">Order #{data.order_number}</h1>
                    <div className="mt-2 flex items-center justify-center gap-2 flex-wrap">
                        <StatusBadge status={data.status} />
                        <span className="text-sm text-gray-400 capitalize">{data.type === 'dine' ? 'Dine-in' : 'Takeaway'}</span>
                    </div>
                </div>

                {/* Payment Status Card */}
                <div className={`rounded-2xl p-5 shadow-sm mb-6 ${isPaid ? 'bg-green-50 border border-green-200' : 'bg-amber-50 border border-amber-200'}`}>
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-3">
                            {data.payment_method === 'cash' ? (
                                <div className="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
                                    <HiOutlineCash className="w-5 h-5 text-blue-600" />
                                </div>
                            ) : (
                                <div className="w-10 h-10 rounded-full bg-purple-100 flex items-center justify-center">
                                    <HiOutlineCreditCard className="w-5 h-5 text-purple-600" />
                                </div>
                            )}
                            <div>
                                <p className="font-semibold text-gray-900">
                                    {data.payment_method === 'cash' ? 'Pay at Counter' : 'Online Payment'}
                                </p>
                                <p className={`text-sm font-medium ${isPaid ? 'text-green-600' : 'text-amber-600'}`}>
                                    {isPaid ? 'Payment Received' : 'Payment Pending'}
                                </p>
                            </div>
                        </div>
                        <p className="text-xl font-bold text-gray-900">৳{data.grand_total}</p>
                    </div>
                    {data.payment_method === 'cash' && !isPaid && (
                        <p className="text-sm text-amber-700 mt-3 bg-amber-100 rounded-lg p-2 text-center">
                            Please pay <strong>৳{parseFloat(data.grand_total).toFixed(2)}</strong> at the counter
                        </p>
                    )}
                    {data.transaction_id && (
                        <p className="text-xs text-gray-500 mt-2">Transaction ID: {data.transaction_id}</p>
                    )}
                </div>

                {/* Invoice Button */}
                <button
                    onClick={handleViewInvoice}
                    className="w-full mb-6 flex items-center justify-center gap-2 py-3 bg-white border border-gray-200 rounded-2xl text-gray-700 font-medium hover:bg-gray-50 shadow-sm transition"
                >
                    <HiOutlineDocumentText className="w-5 h-5" />
                    View & Download Invoice
                </button>

                {/* Progress Tracker */}
                {data.status !== 'cancelled' && (
                    <div className="bg-white rounded-2xl p-6 shadow-sm mb-6">
                        <h3 className="font-semibold mb-4">Order Progress</h3>
                        <div className="space-y-4">
                            {STATUS_STEPS.map((step, i) => {
                                const isCompleted = i <= currentIdx;
                                const isCurrent = i === currentIdx;
                                return (
                                    <div key={step} className="flex items-center gap-4">
                                        <div className={`w-8 h-8 rounded-full flex items-center justify-center flex-shrink-0 ${
                                            isCompleted ? 'bg-green-500 text-white' : 'bg-gray-200 text-gray-400'
                                        } ${isCurrent ? 'ring-4 ring-green-200' : ''}`}>
                                            {isCompleted ? '✓' : i + 1}
                                        </div>
                                        <div className="flex-1">
                                            <p className={`font-medium capitalize ${isCompleted ? 'text-green-700' : 'text-gray-400'}`}>{step}</p>
                                        </div>
                                        {isCurrent && (
                                            <span className="text-xs bg-green-100 text-green-700 px-2 py-1 rounded-full animate-pulse">Current</span>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                )}

                {data.status === 'cancelled' && (
                    <div className="bg-red-50 border border-red-200 rounded-2xl p-6 text-center mb-6">
                        <p className="text-red-600 font-bold text-lg">Order Cancelled</p>
                    </div>
                )}

                {/* Order Items */}
                <div className="bg-white rounded-2xl p-6 shadow-sm mb-6">
                    <h3 className="font-semibold mb-4">Items</h3>
                    <div className="space-y-3">
                        {data.items?.map((item) => (
                            <div key={item.id} className="flex justify-between">
                                <div>
                                    <span className="font-bold mr-2">{item.qty}x</span>
                                    <span>{item.menu_item?.name}</span>
                                </div>
                                <span className="font-medium">৳{item.line_total}</span>
                            </div>
                        ))}
                    </div>
                    <div className="border-t mt-4 pt-4 space-y-1">
                        <div className="flex justify-between text-sm">
                            <span className="text-gray-500">Subtotal</span><span>৳{data.subtotal}</span>
                        </div>
                        {parseFloat(data.discount) > 0 && (
                            <div className="flex justify-between text-sm text-green-600">
                                <span>Discount</span><span>-৳{data.discount}</span>
                            </div>
                        )}
                        {parseFloat(data.tax) > 0 && (
                            <div className="flex justify-between text-sm">
                                <span className="text-gray-500">Tax</span><span>৳{data.tax}</span>
                            </div>
                        )}
                        <div className="flex justify-between text-lg font-bold pt-2 border-t">
                            <span>Total</span><span>৳{data.grand_total}</span>
                        </div>
                    </div>
                </div>

                {/* Details */}
                <div className="bg-white rounded-2xl p-6 shadow-sm">
                    <h3 className="font-semibold mb-3">Details</h3>
                    <div className="text-sm space-y-2 text-gray-600">
                        {data.table && <p>Table: {data.table.table_number}</p>}
                        {data.customer_name && <p>Name: {data.customer_name}</p>}
                        {data.customer_phone && <p>Phone: {data.customer_phone}</p>}
                        <p>Placed: {new Date(data.created_at).toLocaleString()}</p>
                        {data.paid_at && <p>Paid: {new Date(data.paid_at).toLocaleString()}</p>}
                    </div>
                </div>
            </div>

            {/* Invoice Modal */}
            {showInvoice && invoiceData && (
                <POSInvoice
                    order={invoiceData.order}
                    restaurant={invoiceData.restaurant}
                    onClose={() => setShowInvoice(false)}
                />
            )}
        </div>
    );
}
