import React, { useState, useRef, useEffect } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { subscriptionAPI, brandingAPI } from '../../services/api';
import LoadingSpinner from '../../components/ui/LoadingSpinner';
import toast from 'react-hot-toast';
import { HiOutlinePhotograph, HiOutlineTrash, HiOutlineUpload } from 'react-icons/hi';

const STORAGE_URL = '/storage/';

function ImageUpload({ label, currentImage, fieldName, onFileSelect, onRemove, hint }) {
    const inputRef = useRef(null);
    const [preview, setPreview] = useState(null);

    const handleSelect = (e) => {
        const file = e.target.files[0];
        if (file) {
            setPreview(URL.createObjectURL(file));
            onFileSelect(fieldName, file);
        }
    };

    const imgSrc = preview || (currentImage ? STORAGE_URL + currentImage : null);

    return (
        <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">{label}</label>
            <div className="flex items-center gap-4">
                <div className="w-20 h-20 rounded-xl border-2 border-dashed border-gray-300 flex items-center justify-center overflow-hidden bg-gray-50">
                    {imgSrc ? (
                        <img src={imgSrc} alt={label} className="w-full h-full object-contain" />
                    ) : (
                        <HiOutlinePhotograph className="w-7 h-7 text-gray-400" />
                    )}
                </div>
                <div className="flex flex-col gap-2">
                    <button
                        type="button"
                        onClick={() => inputRef.current?.click()}
                        className="inline-flex items-center gap-1 px-3 py-1.5 text-sm bg-blue-50 text-blue-700 rounded-lg hover:bg-blue-100"
                    >
                        <HiOutlineUpload className="w-4 h-4" /> Upload
                    </button>
                    {imgSrc && (
                        <button
                            type="button"
                            onClick={() => {
                                setPreview(null);
                                onRemove(fieldName);
                            }}
                            className="inline-flex items-center gap-1 px-3 py-1.5 text-sm text-red-600 bg-red-50 rounded-lg hover:bg-red-100"
                        >
                            <HiOutlineTrash className="w-4 h-4" /> Remove
                        </button>
                    )}
                </div>
            </div>
            {hint && <p className="text-xs text-gray-400 mt-1">{hint}</p>}
            <input ref={inputRef} type="file" accept="image/*" className="hidden" onChange={handleSelect} />
        </div>
    );
}

function BrandingTab() {
    const queryClient = useQueryClient();
    const [form, setForm] = useState({});
    const [files, setFiles] = useState({});
    const [removals, setRemovals] = useState({});

    const { data, isLoading } = useQuery({
        queryKey: ['tenant-branding'],
        queryFn: () => brandingAPI.get().then((r) => r.data.data),
    });

    useEffect(() => {
        if (data && Object.keys(form).length === 0) {
            setForm(data);
        }
    }, [data]);

    const mutation = useMutation({
        mutationFn: (fd) => brandingAPI.update(fd),
        onSuccess: () => {
            queryClient.invalidateQueries(['tenant-branding']);
            setFiles({});
            setRemovals({});
            toast.success('Branding updated!');
        },
        onError: (err) => toast.error(err.response?.data?.message || 'Failed to update'),
    });

    const handleChange = (field, value) => setForm((p) => ({ ...p, [field]: value }));

    const handleFileSelect = (field, file) => {
        setFiles((p) => ({ ...p, [field]: file }));
        setRemovals((p) => { const c = { ...p }; delete c[field]; return c; });
    };

    const handleRemove = (field) => {
        setRemovals((p) => ({ ...p, [field]: true }));
        setFiles((p) => { const c = { ...p }; delete c[field]; return c; });
        setForm((p) => ({ ...p, [field]: null }));
    };

    const handleSocial = (platform, url) => {
        setForm((p) => ({
            ...p,
            social_links: { ...(p.social_links || {}), [platform]: url },
        }));
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        const fd = new FormData();

        ['name', 'description', 'primary_color', 'secondary_color', 'accent_color'].forEach((k) => {
            if (form[k] !== undefined && form[k] !== null) fd.append(k, form[k]);
        });

        if (form.social_links) {
            Object.entries(form.social_links).forEach(([k, v]) => {
                fd.append(`social_links[${k}]`, v || '');
            });
        }

        Object.entries(files).forEach(([k, f]) => fd.append(k, f));
        Object.keys(removals).forEach((k) => fd.append(`remove_${k}`, '1'));

        mutation.mutate(fd);
    };

    if (isLoading) return <LoadingSpinner />;

    return (
        <form onSubmit={handleSubmit} className="space-y-6">
            {/* Logo & Images */}
            <div className="card">
                <h3 className="text-lg font-semibold text-gray-800 mb-4">Restaurant Logo & Images</h3>
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <ImageUpload label="Logo" currentImage={form.logo} fieldName="logo" onFileSelect={handleFileSelect} onRemove={handleRemove} hint="Shown in header & customer menu" />
                    <ImageUpload label="Logo (Dark)" currentImage={form.logo_dark} fieldName="logo_dark" onFileSelect={handleFileSelect} onRemove={handleRemove} hint="For dark backgrounds" />
                    <ImageUpload label="Favicon" currentImage={form.favicon} fieldName="favicon" onFileSelect={handleFileSelect} onRemove={handleRemove} hint="Browser tab icon" />
                    <ImageUpload label="Banner" currentImage={form.banner_image} fieldName="banner_image" onFileSelect={handleFileSelect} onRemove={handleRemove} hint="Customer menu page banner" />
                </div>
            </div>

            {/* Restaurant Info */}
            <div className="card">
                <h3 className="text-lg font-semibold text-gray-800 mb-4">Restaurant Info</h3>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label className="label">Restaurant Name</label>
                        <input className="input" value={form.name || ''} onChange={(e) => handleChange('name', e.target.value)} />
                    </div>
                    <div className="md:col-span-2">
                        <label className="label">Description</label>
                        <textarea className="input" rows={3} value={form.description || ''} onChange={(e) => handleChange('description', e.target.value)} placeholder="A short description of your restaurant..." />
                    </div>
                </div>
            </div>

            {/* Brand Colors */}
            <div className="card">
                <h3 className="text-lg font-semibold text-gray-800 mb-4">Brand Colors</h3>
                <p className="text-sm text-gray-500 mb-4">These colors are used on the customer ordering page.</p>
                <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                    {[
                        { key: 'primary_color', label: 'Primary', default: '#3B82F6' },
                        { key: 'secondary_color', label: 'Secondary', default: '#1E40AF' },
                        { key: 'accent_color', label: 'Accent', default: '#F59E0B' },
                    ].map((c) => (
                        <div key={c.key}>
                            <label className="label">{c.label} Color</label>
                            <div className="flex items-center gap-3">
                                <input type="color" value={form[c.key] || c.default} onChange={(e) => handleChange(c.key, e.target.value)} className="w-12 h-10 rounded border cursor-pointer" />
                                <input className="input flex-1" value={form[c.key] || c.default} onChange={(e) => handleChange(c.key, e.target.value)} />
                            </div>
                        </div>
                    ))}
                </div>
                <div className="mt-4 p-4 bg-gray-50 rounded-lg">
                    <p className="text-sm text-gray-500 mb-3">Customer Panel Preview:</p>
                    <div className="flex gap-3 items-center flex-wrap">
                        <div className="h-9 px-5 rounded-full flex items-center justify-center text-white text-sm font-medium" style={{ backgroundColor: form.primary_color || '#3B82F6' }}>Add to Cart</div>
                        <div className="h-9 px-5 rounded-full flex items-center justify-center text-white text-sm font-medium" style={{ backgroundColor: form.secondary_color || '#1E40AF' }}>View Cart</div>
                        <div className="h-9 px-5 rounded-full flex items-center justify-center text-white text-sm font-medium" style={{ backgroundColor: form.accent_color || '#F59E0B' }}>Special Offer</div>
                    </div>
                </div>
            </div>

            {/* Social Links */}
            <div className="card">
                <h3 className="text-lg font-semibold text-gray-800 mb-4">Social Links</h3>
                <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div>
                        <label className="label">Facebook</label>
                        <input className="input" value={form.social_links?.facebook || ''} onChange={(e) => handleSocial('facebook', e.target.value)} placeholder="https://facebook.com/..." />
                    </div>
                    <div>
                        <label className="label">Instagram</label>
                        <input className="input" value={form.social_links?.instagram || ''} onChange={(e) => handleSocial('instagram', e.target.value)} placeholder="https://instagram.com/..." />
                    </div>
                    <div>
                        <label className="label">Website</label>
                        <input className="input" value={form.social_links?.website || ''} onChange={(e) => handleSocial('website', e.target.value)} placeholder="https://yourwebsite.com" />
                    </div>
                </div>
            </div>

            <div className="flex justify-end">
                <button type="submit" disabled={mutation.isPending} className="btn-primary px-8">
                    {mutation.isPending ? 'Saving...' : 'Save Branding'}
                </button>
            </div>
        </form>
    );
}

function SubscriptionTab() {
    const { data, isLoading } = useQuery({
        queryKey: ['subscription-current'],
        queryFn: () => subscriptionAPI.current().then((r) => r.data.data),
    });

    if (isLoading) return <LoadingSpinner />;

    return (
        <div className="card">
            <h3 className="text-lg font-semibold mb-4">Subscription</h3>
            {data?.subscription ? (
                <div className="space-y-3">
                    <div className="flex justify-between">
                        <span className="text-gray-500">Plan</span>
                        <span className="font-medium capitalize">{data.subscription.plan_type}</span>
                    </div>
                    <div className="flex justify-between">
                        <span className="text-gray-500">Status</span>
                        <span className={`font-medium ${data.expired ? 'text-red-600' : 'text-green-600'}`}>
                            {data.expired ? 'Expired' : 'Active'}
                        </span>
                    </div>
                    <div className="flex justify-between">
                        <span className="text-gray-500">Expires</span>
                        <span>{new Date(data.subscription.expires_at).toLocaleDateString()}</span>
                    </div>
                    {data.days_remaining !== undefined && (
                        <div className="flex justify-between">
                            <span className="text-gray-500">Days Remaining</span>
                            <span className={`font-bold ${data.days_remaining < 7 ? 'text-red-600' : ''}`}>
                                {data.days_remaining} days
                            </span>
                        </div>
                    )}
                    <div className="flex justify-between">
                        <span className="text-gray-500">Amount</span>
                        <span className="font-medium">৳{data.subscription.amount}</span>
                    </div>
                </div>
            ) : (
                <div className="text-center py-6">
                    <p className="text-red-600 font-medium">No active subscription</p>
                    <p className="text-gray-500 text-sm mt-1">Contact support to renew</p>
                </div>
            )}
        </div>
    );
}

export default function SettingsPage() {
    const [activeTab, setActiveTab] = useState('branding');

    const tabs = [
        { id: 'branding', label: 'Branding' },
        { id: 'subscription', label: 'Subscription' },
    ];

    return (
        <div>
            <h2 className="text-2xl font-bold text-gray-800 mb-6">Settings</h2>

            {/* Tabs */}
            <div className="flex gap-1 mb-6 bg-gray-100 rounded-lg p-1 w-fit">
                {tabs.map((tab) => (
                    <button
                        key={tab.id}
                        onClick={() => setActiveTab(tab.id)}
                        className={`px-4 py-2 text-sm font-medium rounded-md transition-colors ${
                            activeTab === tab.id
                                ? 'bg-white text-blue-700 shadow-sm'
                                : 'text-gray-600 hover:text-gray-900'
                        }`}
                    >
                        {tab.label}
                    </button>
                ))}
            </div>

            {activeTab === 'branding' && <BrandingTab />}
            {activeTab === 'subscription' && <SubscriptionTab />}
        </div>
    );
}
