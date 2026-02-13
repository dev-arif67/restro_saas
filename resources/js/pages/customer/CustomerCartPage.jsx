import React, { useState } from 'react';
import { useParams, useNavigate, useOutletContext, useSearchParams, Link } from 'react-router-dom';
import { useMutation } from '@tanstack/react-query';
import { useCartStore } from '../../stores/cartStore';
import { customerAPI } from '../../services/api';
import toast from 'react-hot-toast';
import { HiOutlineCash, HiOutlineCreditCard } from 'react-icons/hi';

export default function CustomerCartPage() {
    const { slug } = useParams();
    const navigate = useNavigate();
    const [searchParams] = useSearchParams();
    const { restaurant, tableId } = useOutletContext() || {};
    const { items, addItem, removeItem, updateQty, clearCart, getSubtotal, getOrderPayload } = useCartStore();
    const [voucher, setVoucher] = useState('');
    const [voucherData, setVoucherData] = useState(null);
    const [orderType, setOrderType] = useState(tableId ? 'dine' : 'parcel');
    const [paymentMethod, setPaymentMethod] = useState('cash');
    const [customerName, setCustomerName] = useState('');
    const [customerPhone, setCustomerPhone] = useState('');

    const subtotal = getSubtotal();
    const discount = voucherData ? (voucherData.type === 'percentage' ? subtotal * (voucherData.discount_value / 100) : voucherData.discount_value) : 0;
    const taxRate = restaurant?.tax_rate || 0;
    const taxableAmount = subtotal - discount;
    const tax = taxableAmount * (taxRate / 100);
    const total = taxableAmount + tax;

    const validateVoucher = async () => {
        try {
            const { data } = await customerAPI.validateVoucher(slug, voucher, subtotal);
            setVoucherData(data.data.voucher);
            toast.success('Voucher applied!');
        } catch (err) {
            setVoucherData(null);
            toast.error(err.response?.data?.message || 'Invalid voucher');
        }
    };

    const placeOrder = useMutation({
        mutationFn: (payload) => customerAPI.placeOrder(slug, payload),
        onSuccess: (res) => {
            const orderNumber = res.data.data.order_number;
            clearCart();
            toast.success('Order placed!');
            navigate(`/order/${orderNumber}`);
        },
        onError: (err) => toast.error(err.response?.data?.message || 'Order failed'),
    });

    const handlePlace = () => {
        if (items.length === 0) return toast.error('Cart is empty');
        if (orderType === 'parcel' && (!customerName || !customerPhone)) {
            return toast.error('Name and phone required for takeaway');
        }

        const payload = {
            ...getOrderPayload(),
            type: orderType,
            tenant_slug: slug,
            table_id: tableId || null,
            voucher_code: voucherData?.code || undefined,
            customer_name: orderType === 'parcel' ? customerName : undefined,
            customer_phone: orderType === 'parcel' ? customerPhone : undefined,
            payment_method: paymentMethod,
        };

        placeOrder.mutate(payload);
    };

    return (
        <div className="pb-32">
            <h2 className="text-xl font-bold mb-4">Your Cart</h2>

            {items.length === 0 ? (
                <div className="text-center py-12">
                    <p className="text-gray-400 text-lg">Your cart is empty</p>
                    <Link to={`/restaurant/${slug}?${searchParams.toString()}`} className="text-blue-600 text-sm mt-2 inline-block">Browse Menu</Link>
                </div>
            ) : (
                <>
                    {/* Items */}
                    <div className="space-y-3 mb-6">
                        {items.map((item) => (
                            <div key={item.menu_item_id} className="bg-white rounded-xl p-4 flex items-center justify-between shadow-sm">
                                <div className="flex-1">
                                    <h3 className="font-medium">{item.name}</h3>
                                    <p className="text-sm text-blue-600 font-bold">৳{item.price}</p>
                                </div>
                                <div className="flex items-center gap-3">
                                    <button onClick={() => updateQty(item.menu_item_id, item.qty - 1)} className="w-8 h-8 rounded-full bg-gray-100 text-lg flex items-center justify-center">-</button>
                                    <span className="font-bold w-6 text-center">{item.qty}</span>
                                    <button onClick={() => updateQty(item.menu_item_id, item.qty + 1)} className="w-8 h-8 rounded-full bg-blue-100 text-blue-600 text-lg flex items-center justify-center">+</button>
                                    <button onClick={() => removeItem(item.menu_item_id)} className="text-red-400 text-sm ml-2">Remove</button>
                                </div>
                            </div>
                        ))}
                    </div>

                    {/* Order Type */}
                    <div className="bg-white rounded-xl p-4 shadow-sm mb-4">
                        <label className="text-sm font-medium text-gray-700 mb-2 block">Order Type</label>
                        <div className="flex gap-3">
                            {tableId && (
                                <button onClick={() => setOrderType('dine')}
                                    className={`flex-1 py-2.5 rounded-lg text-sm font-medium ${orderType === 'dine' ? 'bg-blue-600 text-white' : 'bg-gray-100'}`}>
                                    Dine-in
                                </button>
                            )}
                            <button onClick={() => setOrderType('parcel')}
                                className={`flex-1 py-2.5 rounded-lg text-sm font-medium ${orderType === 'parcel' ? 'bg-blue-600 text-white' : 'bg-gray-100'}`}>
                                Takeaway
                            </button>
                        </div>
                    </div>

                    {/* Parcel customer info */}
                    {orderType === 'parcel' && (
                        <div className="bg-white rounded-xl p-4 shadow-sm mb-4 space-y-3">
                            <div>
                                <label className="text-sm font-medium text-gray-700">Name</label>
                                <input className="w-full mt-1 px-3 py-2 rounded-lg border" value={customerName} onChange={(e) => setCustomerName(e.target.value)} required />
                            </div>
                            <div>
                                <label className="text-sm font-medium text-gray-700">Phone</label>
                                <input className="w-full mt-1 px-3 py-2 rounded-lg border" value={customerPhone} onChange={(e) => setCustomerPhone(e.target.value)} required />
                            </div>
                        </div>
                    )}

                    {/* Voucher */}
                    <div className="bg-white rounded-xl p-4 shadow-sm mb-4">
                        <label className="text-sm font-medium text-gray-700 mb-2 block">Voucher Code</label>
                        <div className="flex gap-2">
                            <input className="flex-1 px-3 py-2 rounded-lg border" placeholder="Enter code" value={voucher} onChange={(e) => setVoucher(e.target.value.toUpperCase())} />
                            <button onClick={validateVoucher} className="px-4 py-2 bg-gray-900 text-white rounded-lg text-sm font-medium" disabled={!voucher}>Apply</button>
                        </div>
                        {voucherData && (
                            <p className="text-green-600 text-sm mt-2">
                                {voucherData.type === 'percentage' ? `${voucherData.discount_value}% off` : `৳${voucherData.discount_value} off`} applied!
                            </p>
                        )}
                    </div>

                    {/* Payment Method */}
                    <div className="bg-white rounded-xl p-4 shadow-sm mb-4">
                        <label className="text-sm font-medium text-gray-700 mb-3 block">Payment Method</label>
                        <div className="grid grid-cols-2 gap-3">
                            <button
                                onClick={() => setPaymentMethod('cash')}
                                className={`flex flex-col items-center gap-2 p-4 rounded-xl border-2 transition-all ${
                                    paymentMethod === 'cash'
                                        ? 'border-blue-600 bg-blue-50 text-blue-700'
                                        : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300'
                                }`}
                            >
                                <HiOutlineCash className="w-7 h-7" />
                                <span className="text-sm font-medium">Pay at Counter</span>
                                <span className="text-xs text-gray-400">Cash / Card</span>
                            </button>
                            <button
                                onClick={() => setPaymentMethod('online')}
                                className={`flex flex-col items-center gap-2 p-4 rounded-xl border-2 transition-all ${
                                    paymentMethod === 'online'
                                        ? 'border-blue-600 bg-blue-50 text-blue-700'
                                        : 'border-gray-200 bg-white text-gray-600 hover:border-gray-300'
                                }`}
                            >
                                <HiOutlineCreditCard className="w-7 h-7" />
                                <span className="text-sm font-medium">Pay Online</span>
                                <span className="text-xs text-gray-400">bKash / SSLCommerz</span>
                            </button>
                        </div>
                        {paymentMethod === 'online' && (
                            <div className="mt-3 p-3 bg-amber-50 border border-amber-200 rounded-lg">
                                <p className="text-xs text-amber-700">
                                    <strong>Note:</strong> Online payment will be available after your order is confirmed. You'll receive a payment link.
                                </p>
                            </div>
                        )}
                    </div>

                    {/* Totals */}
                    <div className="bg-white rounded-xl p-4 shadow-sm mb-4 space-y-2">
                        <div className="flex justify-between">
                            <span className="text-gray-500">Subtotal</span><span>৳{subtotal.toFixed(2)}</span>
                        </div>
                        {discount > 0 && (
                            <div className="flex justify-between text-green-600">
                                <span>Discount</span><span>-৳{discount.toFixed(2)}</span>
                            </div>
                        )}
                        {tax > 0 && (
                            <div className="flex justify-between">
                                <span className="text-gray-500">Tax ({taxRate}%)</span><span>৳{tax.toFixed(2)}</span>
                            </div>
                        )}
                        <div className="flex justify-between text-lg font-bold pt-2 border-t">
                            <span>Total</span><span>৳{total.toFixed(2)}</span>
                        </div>
                    </div>
                </>
            )}

            {/* Place Order */}
            {items.length > 0 && (
                <div className="fixed bottom-0 left-0 right-0 p-4 bg-white border-t max-w-lg mx-auto">
                    <button
                        onClick={handlePlace}
                        disabled={placeOrder.isPending}
                        className="w-full py-4 bg-blue-600 text-white rounded-xl text-lg font-bold hover:bg-blue-700 transition disabled:opacity-50"
                    >
                        {placeOrder.isPending ? 'Placing...' : `${paymentMethod === 'online' ? 'Place Order & Pay' : 'Place Order'} - ৳${total.toFixed(2)}`}
                    </button>
                </div>
            )}
        </div>
    );
}
