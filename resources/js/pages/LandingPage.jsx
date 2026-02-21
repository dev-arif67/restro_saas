import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { useBrandingStore } from '../stores/brandingStore';
import { contactAPI } from '../services/api';
import toast from 'react-hot-toast';
import {
    HiOutlineDevicePhoneMobile,
    HiOutlineQrCode,
    HiOutlineChartBarSquare,
    HiOutlineBuildingStorefront,
    HiOutlineComputerDesktop,
    HiOutlineBellAlert,
    HiOutlineUserGroup,
    HiOutlineCreditCard,
    HiOutlineCheckCircle,
    HiOutlineArrowRight,
    HiOutlinePaperAirplane,
} from 'react-icons/hi2';

const features = [
    {
        icon: HiOutlineBuildingStorefront,
        title: 'Multi-Tenant Platform',
        description: 'Each restaurant gets its own isolated environment with custom branding, menus, and settings.',
    },
    {
        icon: HiOutlineQrCode,
        title: 'QR Code Ordering',
        description: 'Guests scan a table QR code to browse the menu and place orders directly from their phone.',
    },
    {
        icon: HiOutlineComputerDesktop,
        title: 'POS Terminal',
        description: 'A fast, intuitive point-of-sale for counter staff — supports cash, card, and mobile payments.',
    },
    {
        icon: HiOutlineBellAlert,
        title: 'Kitchen Display',
        description: 'Real-time kitchen display system keeps the back-of-house in sync with incoming orders.',
    },
    {
        icon: HiOutlineChartBarSquare,
        title: 'Reports & Analytics',
        description: 'Sales trends, top items, revenue comparisons, and settlement tracking — all in one dashboard.',
    },
    {
        icon: HiOutlineCreditCard,
        title: 'Payment & Settlements',
        description: 'Track payments, manage settlements, and integrate with popular payment gateways.',
    },
    {
        icon: HiOutlineUserGroup,
        title: 'Role-Based Access',
        description: 'Admin, staff, and kitchen roles ensure the right people see the right screens.',
    },
    {
        icon: HiOutlineDevicePhoneMobile,
        title: 'Mobile Friendly',
        description: 'Fully responsive customer ordering experience that works beautifully on any device.',
    },
];

const steps = [
    { number: '01', title: 'Sign Up', description: 'Register your restaurant in under a minute.' },
    { number: '02', title: 'Set Up Menu', description: 'Add categories, items, images, and prices.' },
    { number: '03', title: 'Go Live', description: 'Print QR codes, place them on tables, and start taking orders.' },
];

export default function LandingPage() {
    const { branding } = useBrandingStore();
    const logoSrc = branding.platform_logo ? `/storage/${branding.platform_logo}` : null;
    const platformName = branding.platform_name || 'RestaurantSaaS';

    const [form, setForm] = useState({ name: '', email: '', phone: '', restaurant_name: '', message: '' });
    const [submitting, setSubmitting] = useState(false);
    const [submitted, setSubmitted] = useState(false);

    const handleChange = (e) => setForm({ ...form, [e.target.name]: e.target.value });

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSubmitting(true);
        try {
            await contactAPI.submit(form);
            toast.success('Your enquiry has been sent!');
            setSubmitted(true);
            setForm({ name: '', email: '', phone: '', restaurant_name: '', message: '' });
        } catch (err) {
            const errors = err.response?.data?.errors;
            if (errors) {
                Object.values(errors).flat().forEach((msg) => toast.error(msg));
            } else {
                toast.error('Something went wrong. Please try again.');
            }
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <div className="min-h-screen bg-white">
            {/* ─── Navbar ─── */}
            <nav className="sticky top-0 z-50 bg-white/80 backdrop-blur border-b border-gray-100">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between h-16">
                    <Link to="/" className="flex items-center gap-2">
                        {logoSrc ? (
                            <img src={logoSrc} alt={platformName} className="h-16" />
                        ) : (
                            <span className="text-xl font-bold text-blue-600" >{platformName}</span>
                        )}
                    </Link>
                    <div className="flex items-center gap-3">
                        <a href="#features" className="hidden sm:inline text-sm text-gray-600 hover:text-gray-900 px-3 py-2">Features</a>
                        <a href="#how-it-works" className="hidden sm:inline text-sm text-gray-600 hover:text-gray-900 px-3 py-2">How It Works</a>
                        <a href="#contact" className="hidden sm:inline text-sm text-gray-600 hover:text-gray-900 px-3 py-2">Contact</a>
                        <Link to="/login" className="btn-primary text-sm px-5 py-2">
                            Login
                        </Link>
                    </div>
                </div>
            </nav>

            {/* ─── Hero ─── */}
            <section className="relative overflow-hidden bg-gradient-to-br from-blue-50 via-white to-indigo-50">
                {/* Decorative blobs */}
                <div className="absolute -top-40 -right-40 w-[500px] h-[500px] rounded-full bg-blue-100/50 blur-3xl" />
                <div className="absolute -bottom-40 -left-40 w-[400px] h-[400px] rounded-full bg-indigo-100/50 blur-3xl" />

                <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24 sm:py-32 lg:py-40 text-center">
                    <h1 className="text-4xl sm:text-5xl lg:text-6xl font-extrabold text-gray-900 leading-tight tracking-tight">
                        The All-in-One Platform <br className="hidden sm:block" />
                        <span className="text-blue-600">for Modern Restaurants</span>
                    </h1>
                    <p className="mt-6 max-w-2xl mx-auto text-lg sm:text-xl text-gray-600 leading-relaxed">
                        QR ordering, POS terminal, kitchen display, analytics — everything you need to run your restaurant
                        smarter, all from a single dashboard.
                    </p>
                    <div className="mt-10 flex flex-col sm:flex-row items-center justify-center gap-4">
                        <Link to="/register" className="btn-primary text-base px-8 py-3 shadow-lg shadow-blue-200 flex items-center gap-2">
                            Get Started Free <HiOutlineArrowRight className="w-5 h-5" />
                        </Link>
                        <a href="#contact" className="text-base font-medium text-gray-700 hover:text-blue-600 transition-colors px-6 py-3 rounded-xl border border-gray-200 hover:border-blue-200 bg-white">
                            Contact Us
                        </a>
                    </div>
                </div>
            </section>

            {/* ─── Features ─── */}
            <section id="features" className="py-20 sm:py-28 bg-white">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="text-center max-w-2xl mx-auto mb-16">
                        <p className="text-sm font-semibold tracking-wider text-blue-600 uppercase mb-3">Features</p>
                        <h2 className="text-3xl sm:text-4xl font-bold text-gray-900">Everything your restaurant needs</h2>
                        <p className="mt-4 text-gray-500 text-lg">A powerful suite of tools designed for restaurant owners and their teams.</p>
                    </div>

                    <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-8">
                        {features.map((f, i) => (
                            <div
                                key={i}
                                className="group relative rounded-2xl border border-gray-100 p-6 hover:shadow-lg hover:border-blue-100 transition-all duration-300"
                            >
                                <div className="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-blue-50 text-blue-600 mb-4 group-hover:bg-blue-600 group-hover:text-white transition-colors">
                                    <f.icon className="w-6 h-6" />
                                </div>
                                <h3 className="text-lg font-semibold text-gray-900 mb-2">{f.title}</h3>
                                <p className="text-sm text-gray-500 leading-relaxed">{f.description}</p>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* ─── How It Works ─── */}
            <section id="how-it-works" className="py-20 sm:py-28 bg-gradient-to-b from-gray-50 to-white">
                <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="text-center max-w-2xl mx-auto mb-16">
                        <p className="text-sm font-semibold tracking-wider text-blue-600 uppercase mb-3">How It Works</p>
                        <h2 className="text-3xl sm:text-4xl font-bold text-gray-900">Up and running in minutes</h2>
                    </div>

                    <div className="grid sm:grid-cols-3 gap-10">
                        {steps.map((s, i) => (
                            <div key={i} className="text-center">
                                <div className="inline-flex items-center justify-center w-16 h-16 rounded-full bg-blue-600 text-white text-xl font-bold mb-5 shadow-lg shadow-blue-200">
                                    {s.number}
                                </div>
                                <h3 className="text-xl font-semibold text-gray-900 mb-2">{s.title}</h3>
                                <p className="text-gray-500">{s.description}</p>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* ─── CTA Banner ─── */}
            <section className="py-16 bg-blue-600">
                <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                    <h2 className="text-3xl sm:text-4xl font-bold text-white mb-4">Ready to transform your restaurant?</h2>
                    <p className="text-blue-100 text-lg mb-8 max-w-xl mx-auto">
                        Join hundreds of restaurant owners who are already saving time and boosting revenue.
                    </p>
                    <Link
                        to="/register"
                        className="inline-flex items-center gap-2 bg-white text-blue-600 font-semibold px-8 py-3 rounded-xl hover:bg-blue-50 transition-colors shadow-lg"
                    >
                        Start Free Trial <HiOutlineArrowRight className="w-5 h-5" />
                    </Link>
                </div>
            </section>

            {/* ─── Contact Form ─── */}
            <section id="contact" className="py-20 sm:py-28 bg-white">
                <div className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="text-center mb-12">
                        <p className="text-sm font-semibold tracking-wider text-blue-600 uppercase mb-3">Contact Us</p>
                        <h2 className="text-3xl sm:text-4xl font-bold text-gray-900">Have a question or want a demo?</h2>
                        <p className="mt-4 text-gray-500 text-lg">Fill out the form below and we'll get back to you shortly.</p>
                    </div>

                    {submitted ? (
                        <div className="text-center py-16 rounded-2xl border border-green-100 bg-green-50">
                            <HiOutlineCheckCircle className="w-16 h-16 text-green-500 mx-auto mb-4" />
                            <h3 className="text-2xl font-semibold text-gray-900 mb-2">Thank you!</h3>
                            <p className="text-gray-600 mb-6">We've received your enquiry and will be in touch soon.</p>
                            <button
                                onClick={() => setSubmitted(false)}
                                className="text-blue-600 font-medium hover:underline"
                            >
                                Send another message
                            </button>
                        </div>
                    ) : (
                        <form onSubmit={handleSubmit} className="space-y-6 bg-gray-50 rounded-2xl p-8 sm:p-10 border border-gray-100">
                            <div className="grid sm:grid-cols-2 gap-6">
                                <div>
                                    <label className="label">Full Name <span className="text-red-500">*</span></label>
                                    <input
                                        type="text"
                                        name="name"
                                        value={form.name}
                                        onChange={handleChange}
                                        className="input"
                                        placeholder="John Doe"
                                        required
                                    />
                                </div>
                                <div>
                                    <label className="label">Email Address <span className="text-red-500">*</span></label>
                                    <input
                                        type="email"
                                        name="email"
                                        value={form.email}
                                        onChange={handleChange}
                                        className="input"
                                        placeholder="you@example.com"
                                        required
                                    />
                                </div>
                            </div>

                            <div className="grid sm:grid-cols-2 gap-6">
                                <div>
                                    <label className="label">Phone Number</label>
                                    <input
                                        type="text"
                                        name="phone"
                                        value={form.phone}
                                        onChange={handleChange}
                                        className="input"
                                        placeholder="+880 1XXX-XXXXXX"
                                    />
                                </div>
                                <div>
                                    <label className="label">Restaurant Name</label>
                                    <input
                                        type="text"
                                        name="restaurant_name"
                                        value={form.restaurant_name}
                                        onChange={handleChange}
                                        className="input"
                                        placeholder="My Restaurant"
                                    />
                                </div>
                            </div>

                            <div>
                                <label className="label">Message <span className="text-red-500">*</span></label>
                                <textarea
                                    name="message"
                                    value={form.message}
                                    onChange={handleChange}
                                    className="input min-h-[120px] resize-y"
                                    placeholder="Tell us about your needs…"
                                    rows={5}
                                    required
                                />
                            </div>

                            <button
                                type="submit"
                                disabled={submitting}
                                className="btn-primary w-full sm:w-auto px-8 py-3 flex items-center justify-center gap-2"
                            >
                                {submitting ? (
                                    'Sending…'
                                ) : (
                                    <>
                                        Send Enquiry <HiOutlinePaperAirplane className="w-5 h-5" />
                                    </>
                                )}
                            </button>
                        </form>
                    )}
                </div>
            </section>

            {/* ─── Footer ─── */}
            <footer className="bg-gray-900 text-gray-400 py-12">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex flex-col sm:flex-row items-center justify-between gap-6">
                        <div className="flex items-center gap-2">
                            {logoSrc ? (
                                <img
                                    src={branding.platform_logo_dark ? `/storage/${branding.platform_logo_dark}` : logoSrc}
                                    alt={platformName}
                                    className="h-16"
                                />
                            ) : (
                                <span className="text-lg font-bold text-white">{platformName}</span>
                            )}
                        </div>

                        <div className="flex items-center gap-6 text-sm">
                            <a href="#features" className="hover:text-white transition-colors">Features</a>
                            <a href="#how-it-works" className="hover:text-white transition-colors">How It Works</a>
                            <a href="#contact" className="hover:text-white transition-colors">Contact</a>
                            <Link to="/login" className="hover:text-white transition-colors">Login</Link>
                        </div>
                    </div>

                    <div className="mt-8 pt-8 border-t border-gray-800 text-center text-sm">
                        {branding.footer_text || `© ${new Date().getFullYear()} ${platformName}. All rights reserved.`}
                    </div>
                </div>
            </footer>
        </div>
    );
}
