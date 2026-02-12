import React from 'react';

export default function Modal({ isOpen, onClose, title, children, size = 'md' }) {
    if (!isOpen) return null;

    const sizeClasses = {
        sm: 'max-w-md',
        md: 'max-w-lg',
        lg: 'max-w-2xl',
        xl: 'max-w-4xl',
    };

    return (
        <div className="fixed inset-0 z-50 overflow-y-auto">
            <div className="flex items-end sm:items-center justify-center min-h-screen px-0 sm:px-4 pt-4 pb-0 sm:pb-20 text-center">
                {/* Backdrop */}
                <div className="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onClick={onClose} />

                {/* Modal Panel */}
                <div className={`relative inline-block w-full ${sizeClasses[size]} p-4 sm:p-6 sm:my-8 text-left align-middle bg-white shadow-xl rounded-t-2xl sm:rounded-2xl transform transition-all max-h-[90vh] sm:max-h-[85vh] overflow-y-auto`}>
                    {/* Header */}
                    <div className="flex items-center justify-between mb-4 sticky top-0 bg-white pb-2">
                        <h3 className="text-lg font-semibold text-gray-900">{title}</h3>
                        <button
                            onClick={onClose}
                            className="text-gray-400 hover:text-gray-500 transition p-1"
                        >
                            <svg className="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    {/* Content */}
                    {children}
                </div>
            </div>
        </div>
    );
}
