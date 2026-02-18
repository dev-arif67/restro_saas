import React, { useRef } from 'react';
import { HiOutlineDownload, HiOutlinePrinter } from 'react-icons/hi';

export default function POSInvoice({ order, restaurant, onClose }) {
    const invoiceRef = useRef(null);

    const handlePrint = () => {
        const content = invoiceRef.current;
        if (!content) return;

        const printWindow = window.open('', '_blank', 'width=320,height=600');
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="utf-8" />
                <title>Invoice - ${order.order_number}</title>
                <style>
                    * { margin: 0; padding: 0; box-sizing: border-box; }
                    body { font-family: 'Courier New', monospace; font-size: 12px; color: #000; width: 80mm; margin: 0 auto; padding: 8px; }
                    .center { text-align: center; }
                    .bold { font-weight: bold; }
                    .divider { border-top: 1px dashed #000; margin: 6px 0; }
                    .row { display: flex; justify-content: space-between; padding: 1px 0; }
                    .item-row { display: flex; justify-content: space-between; padding: 2px 0; }
                    .restaurant-name { font-size: 16px; font-weight: bold; }
                    .order-num { font-size: 14px; font-weight: bold; margin: 4px 0; }
                    .total-row { font-size: 14px; font-weight: bold; }
                    .footer { font-size: 10px; color: #666; margin-top: 8px; }
                    .badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: bold; text-transform: uppercase; }
                    .badge-paid { background: #dcfce7; color: #166534; }
                    .badge-pending { background: #fef3c7; color: #92400e; }
                    .badge-cash { background: #e0f2fe; color: #075985; }
                    .badge-online { background: #f3e8ff; color: #6b21a8; }
                    @media print {
                        body { width: 80mm; }
                        @page { size: 80mm auto; margin: 0; }
                    }
                </style>
            </head>
            <body>
                ${content.innerHTML}
            </body>
            </html>
        `);
        printWindow.document.close();
        printWindow.focus();
        setTimeout(() => {
            printWindow.print();
            printWindow.close();
        }, 250);
    };

    if (!order) return null;

    const isPaid = order.payment_status === 'paid';
    const paymentLabelMap = {
        cash: 'Cash',
        card: 'Card / POS',
        mobile_banking: 'Mobile Banking',
        online: 'Online',
    };
    const paymentLabel = paymentLabelMap[order.payment_method] ?? order.payment_method;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" onClick={onClose}>
            <div className="bg-white rounded-2xl max-w-sm w-full max-h-[90vh] overflow-y-auto shadow-2xl" onClick={(e) => e.stopPropagation()}>
                {/* Action buttons */}
                <div className="flex items-center justify-between p-4 border-b bg-gray-50 rounded-t-2xl">
                    <h3 className="font-bold text-gray-800">Invoice</h3>
                    <div className="flex gap-2">
                        <button onClick={handlePrint} className="flex items-center gap-1 px-3 py-1.5 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            <HiOutlinePrinter className="w-4 h-4" /> Print
                        </button>
                        <button onClick={onClose} className="px-3 py-1.5 text-sm bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                            Close
                        </button>
                    </div>
                </div>

                {/* Invoice Content */}
                <div ref={invoiceRef} className="p-5">
                    {/* Restaurant Header */}
                    <div className="center" style={{ textAlign: 'center' }}>
                        {restaurant?.logo && (
                            <img src={`/storage/${restaurant.logo}`} alt="" style={{ height: '40px', margin: '0 auto 6px' }} />
                        )}
                        <div className="restaurant-name" style={{ fontSize: '16px', fontWeight: 'bold' }}>
                            {restaurant?.name || 'Restaurant'}
                        </div>
                        {restaurant?.address && (
                            <div style={{ fontSize: '11px', color: '#666', marginTop: '2px' }}>{restaurant.address}</div>
                        )}
                        {restaurant?.phone && (
                            <div style={{ fontSize: '11px', color: '#666' }}>Tel: {restaurant.phone}</div>
                        )}
                    </div>

                    <div className="divider" style={{ borderTop: '1px dashed #000', margin: '8px 0' }}></div>

                    {/* Order Info */}
                    <div style={{ textAlign: 'center' }}>
                        <div className="order-num" style={{ fontSize: '14px', fontWeight: 'bold' }}>
                            {order.order_number}
                        </div>
                        <div style={{ fontSize: '11px', color: '#666', display: 'flex', justifyContent: 'center', gap: '8px', flexWrap: 'wrap', marginTop: '4px' }}>
                            <span style={{ textTransform: 'capitalize' }}>{order.type === 'dine' ? 'Dine-in' : 'Takeaway'}</span>
                            {order.table && <span>| Table {order.table.table_number}</span>}
                            <span>| {new Date(order.created_at).toLocaleString()}</span>
                        </div>
                    </div>

                    <div className="divider" style={{ borderTop: '1px dashed #000', margin: '8px 0' }}></div>

                    {/* Customer */}
                    {(order.customer_name || order.customer_phone) && (
                        <>
                            <div style={{ fontSize: '11px' }}>
                                {order.customer_name && <div>Customer: {order.customer_name}</div>}
                                {order.customer_phone && <div>Phone: {order.customer_phone}</div>}
                            </div>
                            <div className="divider" style={{ borderTop: '1px dashed #000', margin: '8px 0' }}></div>
                        </>
                    )}

                    {/* Items */}
                    <div>
                        <div style={{ display: 'flex', justifyContent: 'space-between', fontWeight: 'bold', fontSize: '11px', marginBottom: '4px' }}>
                            <span>Item</span>
                            <span>Total</span>
                        </div>
                        {order.items?.map((item) => (
                            <div key={item.id} style={{ display: 'flex', justifyContent: 'space-between', padding: '2px 0', fontSize: '12px' }}>
                                <span>{item.qty}x {item.menu_item?.name || 'Item'}</span>
                                <span>৳{parseFloat(item.line_total).toFixed(2)}</span>
                            </div>
                        ))}
                    </div>

                    <div className="divider" style={{ borderTop: '1px dashed #000', margin: '8px 0' }}></div>

                    {/* Totals */}
                    <div style={{ fontSize: '12px' }}>
                        <div style={{ display: 'flex', justifyContent: 'space-between', padding: '1px 0' }}>
                            <span>Subtotal</span>
                            <span>৳{parseFloat(order.subtotal).toFixed(2)}</span>
                        </div>
                        {parseFloat(order.discount) > 0 && (
                            <div style={{ display: 'flex', justifyContent: 'space-between', padding: '1px 0', color: '#16a34a' }}>
                                <span>Discount</span>
                                <span>-৳{parseFloat(order.discount).toFixed(2)}</span>
                            </div>
                        )}
                        {parseFloat(order.tax) > 0 && (
                            <div style={{ display: 'flex', justifyContent: 'space-between', padding: '1px 0' }}>
                                <span>Tax</span>
                                <span>৳{parseFloat(order.tax).toFixed(2)}</span>
                            </div>
                        )}
                        <div className="divider" style={{ borderTop: '1px dashed #000', margin: '6px 0' }}></div>
                        <div className="total-row" style={{ display: 'flex', justifyContent: 'space-between', fontSize: '15px', fontWeight: 'bold' }}>
                            <span>TOTAL</span>
                            <span>৳{parseFloat(order.grand_total).toFixed(2)}</span>
                        </div>
                    </div>

                    <div className="divider" style={{ borderTop: '1px dashed #000', margin: '8px 0' }}></div>

                    {/* Payment Info */}
                    <div style={{ textAlign: 'center', fontSize: '12px' }}>
                        <div style={{ display: 'flex', justifyContent: 'center', gap: '6px', flexWrap: 'wrap' }}>
                            <span className={`badge ${order.payment_method === 'cash' ? 'badge-cash' : 'badge-online'}`}
                                style={{
                                    display: 'inline-block', padding: '2px 8px', borderRadius: '4px',
                                    fontSize: '10px', fontWeight: 'bold', textTransform: 'uppercase',
                                    background: order.payment_method === 'cash' ? '#e0f2fe' : order.payment_method === 'card' ? '#ede9fe' : '#f0fdf4',
                                    color: order.payment_method === 'cash' ? '#075985' : order.payment_method === 'card' ? '#5b21b6' : '#166534',
                                }}>
                                {paymentLabel}
                            </span>
                            <span className={`badge ${isPaid ? 'badge-paid' : 'badge-pending'}`}
                                style={{
                                    display: 'inline-block', padding: '2px 8px', borderRadius: '4px',
                                    fontSize: '10px', fontWeight: 'bold', textTransform: 'uppercase',
                                    background: isPaid ? '#dcfce7' : '#fef3c7',
                                    color: isPaid ? '#166534' : '#92400e',
                                }}>
                                {isPaid ? 'PAID' : 'UNPAID'}
                            </span>
                        </div>
                        {order.transaction_id && (
                            <div style={{ fontSize: '10px', color: '#666', marginTop: '4px' }}>
                                TXN: {order.transaction_id}
                            </div>
                        )}
                    </div>

                    <div className="divider" style={{ borderTop: '1px dashed #000', margin: '8px 0' }}></div>

                    {/* Footer */}
                    <div className="footer" style={{ textAlign: 'center', fontSize: '10px', color: '#666', marginTop: '8px' }}>
                        <p>Thank you for your order!</p>
                        {order.payment_method === 'cash' && !isPaid && (
                            <p style={{ marginTop: '4px', fontWeight: 'bold', color: '#92400e' }}>
                                Please pay ৳{parseFloat(order.grand_total).toFixed(2)} at the counter
                            </p>
                        )}
                    </div>
                </div>
            </div>
        </div>
    );
}
