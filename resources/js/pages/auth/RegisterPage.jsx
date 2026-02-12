import React, { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { useAuthStore } from '../../stores/authStore';
import { useBrandingStore } from '../../stores/brandingStore';
import { authAPI } from '../../services/api';
import toast from 'react-hot-toast';

export default function RegisterPage() {
    const [form, setForm] = useState({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });
    const [loading, setLoading] = useState(false);
    const { setAuth } = useAuthStore();
    const { branding } = useBrandingStore();
    const navigate = useNavigate();

    const logoSrc = branding.platform_logo ? `/storage/${branding.platform_logo}` : null;

    const handleSubmit = async (e) => {
        e.preventDefault();
        setLoading(true);

        try {
            const { data } = await authAPI.register(form);
            setAuth(data.user, data.access_token);
            navigate('/dashboard');
            toast.success('Welcome! Set up your restaurant.');
        } catch (err) {
            toast.error(err.response?.data?.message || 'Registration failed');
        } finally {
            setLoading(false);
        }
    };

    const updateForm = (field, value) => setForm((f) => ({ ...f, [field]: value }));

    return (
        <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-blue-50 to-indigo-100 px-4">
            <div className="w-full max-w-md">
                <div className="text-center mb-8">
                    {logoSrc ? (
                        <img src={logoSrc} alt={branding.platform_name} className="h-12 mx-auto mb-3" />
                    ) : (
                        <h1 className="text-3xl font-bold text-blue-600">{branding.platform_name || 'RestaurantSaaS'}</h1>
                    )}
                    <p className="text-gray-500 mt-2">Register your restaurant</p>
                </div>

                <div className="bg-white rounded-2xl shadow-lg p-8">
                    <form onSubmit={handleSubmit} className="space-y-5">
                        <div>
                            <label className="label">Full Name</label>
                            <input type="text" className="input" value={form.name} onChange={(e) => updateForm('name', e.target.value)} required />
                        </div>
                        <div>
                            <label className="label">Email</label>
                            <input type="email" className="input" value={form.email} onChange={(e) => updateForm('email', e.target.value)} required />
                        </div>
                        <div>
                            <label className="label">Password</label>
                            <input type="password" className="input" value={form.password} onChange={(e) => updateForm('password', e.target.value)} required minLength={8} />
                        </div>
                        <div>
                            <label className="label">Confirm Password</label>
                            <input type="password" className="input" value={form.password_confirmation} onChange={(e) => updateForm('password_confirmation', e.target.value)} required />
                        </div>

                        <button type="submit" disabled={loading} className="btn-primary w-full py-3">
                            {loading ? 'Creating account...' : 'Register'}
                        </button>
                    </form>

                    <p className="text-center text-sm text-gray-500 mt-6">
                        Already have an account?{' '}
                        <Link to="/login" className="text-blue-600 hover:text-blue-700 font-medium">Sign in</Link>
                    </p>
                </div>
            </div>
        </div>
    );
}
