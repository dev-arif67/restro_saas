import React, { useRef, useState } from 'react';
import { useReactToPrint } from 'react-to-print';
import QRCode from 'react-qr-code';
import Modal from './ui/Modal';
import html2canvas from 'html2canvas';
import toast from 'react-hot-toast';

export default function TableQRCard({ isOpen, onClose, table, tenant, qrUrl }) {
    const printRef = useRef();
    const [isDownloading, setIsDownloading] = useState(false);

    const handlePrint = useReactToPrint({
        content: () => printRef.current,
        documentTitle: `QR-Card-Table-${table?.table_number}`,
        pageStyle: `
            @page {
                size: 4in 6in;
                margin: 0;
                padding: 0;
            }
            body {
                margin: 0;
                padding: 0;
            }
        `,
        onBeforeGetContent: () => {
            return new Promise((resolve) => {
                setTimeout(resolve, 100);
            });
        },
    });

    const handleDownload = async () => {
        if (isDownloading) return;

        try {
            setIsDownloading(true);

            if (!printRef.current) {
                toast.error('Card not ready');
                return;
            }

            // Capture the card as an image
            const canvas = await html2canvas(printRef.current, {
                scale: 3,
                useCORS: true,
                allowTaint: true,
                backgroundColor: '#ffffff',
            });

            // Download the image
            canvas.toBlob((blob) => {
                const url = URL.createObjectURL(blob);
                const link = document.createElement('a');
                link.href = url;
                link.download = `Table-${table?.table_number}-QR-${new Date().toISOString().split('T')[0]}.png`;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(url);

                toast.success('QR card downloaded!');
                setIsDownloading(false);
            }, 'image/png');
        } catch (error) {
            console.error('Download failed:', error);
            toast.error('Failed to download QR card');
            setIsDownloading(false);
        }
    };

    return (
        <Modal
            isOpen={isOpen}
            onClose={onClose}
            title={`QR Code Card - Table ${table?.table_number}`}
            size="lg"
        >
            <div className="space-y-4">
                {/* Preview */}
                <div className="bg-gray-50 p-8 rounded-lg overflow-auto max-h-96 flex justify-center">
                    <div
                        ref={printRef}
                        className="bg-white"
                        style={{
                            width: '320px',
                            height: '480px',
                            padding: '20px',
                            boxShadow: '0 2px 8px rgba(0,0,0,0.15)',
                            borderRadius: '8px',
                            borderWidth: '1px',
                            borderColor: '#e5e7eb',
                            display: 'flex',
                            flexDirection: 'column',
                            justifyContent: 'space-between',
                            alignItems: 'center',
                            textAlign: 'center',
                        }}
                    >
                        {/* Header */}
                        <div className="w-full">
                            {tenant?.logo && (
                                <img
                                    src={tenant.logo}
                                    alt={tenant.name}
                                    className="h-10 mb-2 mx-auto object-contain"
                                    style={{ maxHeight: '40px' }}
                                />
                            )}
                            <h1 className="text-base font-bold text-gray-800 truncate w-full">
                                {tenant?.name}
                            </h1>
                            <p className="text-xs text-gray-600">Table {table?.table_number}</p>
                        </div>

                        {/* Divider */}
                        <div className="w-full border-t border-gray-200"></div>

                        {/* QR Code */}
                        <div className="flex justify-center">
                            {qrUrl ? (
                                <QRCode value={qrUrl} size={120} level="H" />
                            ) : (
                                <div className="w-32 h-32 bg-gray-100 rounded flex items-center justify-center text-gray-400">
                                    Loading QR...
                                </div>
                            )}
                        </div>

                        {/* Divider */}
                        <div className="w-full border-t border-gray-200"></div>

                        {/* Instructions & Contact */}
                        <div className="w-full">
                            <p className="text-xs font-semibold text-gray-700 mb-1.5">Scan to Order</p>
                            <div className="text-xs text-gray-600 space-y-0.5">
                                {tenant?.email && (
                                    <p className="truncate w-full overflow-hidden text-ellipsis">
                                        📧 {tenant.email}
                                    </p>
                                )}
                                {tenant?.phone && <p>☎️ {tenant.phone}</p>}
                                {tenant?.address && (
                                    <p className="text-xs line-clamp-1">
                                        📍 {tenant.address}
                                    </p>
                                )}
                            </div>
                        </div>

                        {/* Footer */}
                        <p className="text-xs text-gray-400">Thank you for dining!</p>
                    </div>
                </div>

                {/* Action Buttons */}
                <div className="flex gap-3 justify-end pt-4 border-t">
                    <button
                        onClick={handleDownload}
                        disabled={isDownloading}
                        className={`flex items-center gap-2 px-3 py-2 text-sm ${
                            isDownloading
                                ? 'btn-secondary opacity-50 cursor-not-allowed'
                                : 'btn-secondary hover:opacity-90'
                        }`}
                    >
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth={2}
                                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"
                            />
                        </svg>
                        {isDownloading ? 'Downloading...' : 'Download PNG'}
                    </button>
                    <button
                        onClick={handlePrint}
                        className="btn-primary flex items-center gap-2 px-3 py-2 text-sm hover:opacity-90"
                    >
                        <svg className="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path
                                strokeLinecap="round"
                                strokeLinejoin="round"
                                strokeWidth={2}
                                d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"
                            />
                        </svg>
                        Print
                    </button>
                    <button
                        onClick={onClose}
                        className="btn-secondary px-3 py-2 text-sm hover:opacity-90"
                    >
                        Close
                    </button>
                </div>

                {/* Helpful note */}
                <div className="bg-blue-50 border border-blue-200 rounded p-3 text-xs text-blue-800">
                    <p className="font-semibold mb-1">Tips:</p>
                    <ul className="list-disc list-inside space-y-0.5">
                        <li>Print on 4x6 inch card stock or label paper</li>
                        <li>Use PNG download for digital distribution or email</li>
                        <li>Laminate printed cards for durability</li>
                    </ul>
                </div>
            </div>
        </Modal>
    );
}
