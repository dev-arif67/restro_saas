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
    HiOutlineCog6Tooth,
    HiOutlineClipboardDocumentList,
    HiOutlineShieldCheck,
    HiOutlineMegaphone,
    HiOutlineServerStack,
    HiOutlineDocumentChartBar,
    HiOutlineReceiptPercent,
    HiOutlineCurrencyDollar,
    HiOutlineRocketLaunch,
    HiOutlineSparkles,
    HiOutlineCheckBadge,
} from 'react-icons/hi2';

const PRIMARY_COLOR = '#ED802A';

// Restaurant Features
const restaurantFeatures = [
    {
        icon: HiOutlineQrCode,
        title: 'QR Code Ordering',
        description: 'Guests scan table QR codes to browse menus and place orders directly from their phones.',
    },
    {
        icon: HiOutlineComputerDesktop,
        title: 'POS Terminal',
        description: 'Lightning-fast point-of-sale for counter staff with cash, card, and mobile payment support.',
    },
    {
        icon: HiOutlineBellAlert,
        title: 'Kitchen Display',
        description: 'Real-time kitchen display system keeps your back-of-house in perfect sync.',
    },
    {
        icon: HiOutlineChartBarSquare,
        title: 'Reports & Analytics',
        description: 'Sales trends, top items, revenue comparisons, and settlement tracking in one dashboard.',
    },
    {
        icon: HiOutlineReceiptPercent,
        title: 'VAT & Tax Management',
        description: 'Automatic VAT calculation, inclusive/exclusive pricing, and compliant tax reports.',
    },
    {
        icon: HiOutlineCreditCard,
        title: 'Flexible Payments',
        description: 'Cash, card, online payments, and "Pay Later" options for dine-in customers.',
    },
    {
        icon: HiOutlineClipboardDocumentList,
        title: 'Order Management',
        description: 'Track orders from placement to completion with real-time status updates.',
    },
    {
        icon: HiOutlineDevicePhoneMobile,
        title: 'Mobile Responsive',
        description: 'Beautiful customer ordering experience that works flawlessly on any device.',
    },
];

// Admin Platform Features
const adminFeatures = [
    {
        icon: HiOutlineBuildingStorefront,
        title: 'Multi-Tenant Architecture',
        description: 'Each restaurant gets isolated data, custom branding, and independent settings.',
    },
    {
        icon: HiOutlineDocumentChartBar,
        title: 'Platform Analytics',
        description: 'Real-time MRR, ARR, churn rate, tenant growth, and revenue insights.',
    },
    {
        icon: HiOutlineUserGroup,
        title: 'Tenant Management',
        description: 'Full control over tenants with impersonation, bulk actions, and detailed views.',
    },
    {
        icon: HiOutlineCurrencyDollar,
        title: 'Subscription Plans',
        description: 'Create and manage flexible subscription plans with custom features and pricing.',
    },
    {
        icon: HiOutlineCog6Tooth,
        title: 'Financial Settlements',
        description: 'Track commissions, record payments, and manage cross-tenant settlements.',
    },
    {
        icon: HiOutlineMegaphone,
        title: 'Announcements',
        description: 'Broadcast announcements to all tenants via in-app notifications or email.',
    },
    {
        icon: HiOutlineServerStack,
        title: 'System Monitoring',
        description: 'Health checks, queue stats, cache management, and log viewer.',
    },
    {
        icon: HiOutlineShieldCheck,
        title: 'Audit Logging',
        description: 'Complete activity tracking with filterable, exportable audit trails.',
    },
];

const pricingPlans = [
    {
        name: 'Starter',
        price: '1,999',
        period: '/month',
        description: 'Perfect for small restaurants getting started',
        features: [
            'Up to 10 Tables',
            'QR Code Ordering',
            'Basic POS Terminal',
            'Kitchen Display',
            'Email Support',
        ],
        highlighted: false,
    },
    {
        name: 'Professional',
        price: '4,999',
        period: '/month',
        description: 'Ideal for growing restaurants',
        features: [
            'Unlimited Tables',
            'All Starter Features',
            'Advanced Reports',
            'Multiple Staff Accounts',
            'Online Payments (SSLCommerz)',
            'Priority Support',
        ],
        highlighted: true,
    },
    {
        name: 'Enterprise',
        price: 'Custom',
        period: '',
        description: 'For restaurant chains & franchises',
        features: [
            'Everything in Professional',
            'Multi-Location Support',
            'Dedicated Account Manager',
            'Custom Integrations',
            'SLA Guarantee',
            'On-site Training',
        ],
        highlighted: false,
    },
];

const steps = [
    { number: '01', title: 'Sign Up', description: 'Register your restaurant in under a minute with our simple onboarding.' },
    { number: '02', title: 'Configure', description: 'Add your menu, categories, tables, and customize your branding.' },
    { number: '03', title: 'Go Live', description: 'Print QR codes, train your staff, and start accepting orders!' },
];

const stats = [
    { value: '500+', label: 'Restaurants' },
    { value: '2M+', label: 'Orders Processed' },
    { value: '99.9%', label: 'Uptime' },
    { value: '24/7', label: 'Support' },
];

export default function LandingPage() {
    const { branding } = useBrandingStore();
    const logoSrc = branding.platform_logo ? `/storage/${branding.platform_logo}` : null;
    const platformName = 'Infyrasoft';

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
            <nav className="sticky top-0 z-50 bg-white/95 backdrop-blur border-b border-gray-100">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex items-center justify-between h-16">
                    <Link to="/" className="flex items-center gap-2">
                        {logoSrc ? (
                            <img src={logoSrc} alt={platformName} className="h-10" />
                        ) : (
                            <span className="text-2xl font-bold" style={{ color: PRIMARY_COLOR }}>{platformName}</span>
                        )}
                    </Link>
                    <div className="flex items-center gap-1 sm:gap-3">
                        <a href="#features" className="hidden md:inline text-sm text-gray-600 hover:text-gray-900 px-3 py-2">Features</a>
                        <a href="#admin" className="hidden md:inline text-sm text-gray-600 hover:text-gray-900 px-3 py-2">Platform</a>
                        <a href="#pricing" className="hidden md:inline text-sm text-gray-600 hover:text-gray-900 px-3 py-2">Pricing</a>
                        <a href="#contact" className="hidden md:inline text-sm text-gray-600 hover:text-gray-900 px-3 py-2">Contact</a>
                        <Link to="/login" className="text-sm font-medium px-5 py-2.5 rounded-xl text-white transition-all hover:opacity-90" style={{ backgroundColor: PRIMARY_COLOR }}>
                            Login
                        </Link>
                    </div>
                </div>
            </nav>

            {/* ─── Hero ─── */}
            <section className="relative overflow-hidden" style={{ background: `linear-gradient(135deg, #FFF7ED 0%, #FFFFFF 50%, #FEF3C7 100%)` }}>
                {/* Decorative elements */}
                <div className="absolute top-20 right-10 w-72 h-72 rounded-full blur-3xl opacity-30" style={{ backgroundColor: PRIMARY_COLOR }} />
                <div className="absolute bottom-10 left-10 w-96 h-96 rounded-full blur-3xl opacity-20" style={{ backgroundColor: '#FBBF24' }} />

                <div className="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 sm:py-28 lg:py-36">
                    <div className="text-center max-w-4xl mx-auto">
                        <div className="inline-flex items-center gap-2 px-4 py-2 rounded-full text-sm font-medium mb-6" style={{ backgroundColor: `${PRIMARY_COLOR}15`, color: PRIMARY_COLOR }}>
                            <HiOutlineSparkles className="w-4 h-4" />
                            Complete Restaurant Management Solution
                        </div>
                        <h1 className="text-4xl sm:text-5xl lg:text-6xl font-extrabold text-gray-900 leading-tight tracking-tight">
                            Transform Your Restaurant <br className="hidden sm:block" />
                            <span style={{ color: PRIMARY_COLOR }}>Into a Digital Powerhouse</span>
                        </h1>
                        <p className="mt-6 max-w-2xl mx-auto text-lg sm:text-xl text-gray-600 leading-relaxed">
                            QR ordering, POS terminal, kitchen display, analytics, multi-tenant platform —
                            everything you need to run your restaurant business smarter.
                        </p>
                        <div className="mt-10 flex flex-col sm:flex-row items-center justify-center gap-4">
                            <Link
                                to="/register"
                                className="text-base px-8 py-3.5 rounded-xl text-white font-semibold shadow-lg flex items-center gap-2 transition-all hover:opacity-90"
                                style={{ backgroundColor: PRIMARY_COLOR, boxShadow: `0 10px 40px -10px ${PRIMARY_COLOR}80` }}
                            >
                                Start Free Trial <HiOutlineRocketLaunch className="w-5 h-5" />
                            </Link>
                            <a href="#contact" className="text-base font-medium text-gray-700 hover:text-gray-900 transition-colors px-6 py-3.5 rounded-xl border border-gray-200 bg-white hover:border-gray-300">
                                Request Demo
                            </a>
                        </div>
                    </div>
                </div>

                {/* Stats Banner */}
                <div className="relative max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 pb-16">
                    <div className="bg-white rounded-2xl shadow-xl border border-gray-100 p-6 sm:p-8">
                        <div className="grid grid-cols-2 sm:grid-cols-4 gap-6 text-center">
                            {stats.map((stat, i) => (
                                <div key={i}>
                                    <p className="text-3xl sm:text-4xl font-bold" style={{ color: PRIMARY_COLOR }}>{stat.value}</p>
                                    <p className="text-sm text-gray-500 mt-1">{stat.label}</p>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            </section>

            {/* ─── Restaurant Features ─── */}
            <section id="features" className="py-20 sm:py-28 bg-white">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="text-center max-w-2xl mx-auto mb-16">
                        <p className="text-sm font-semibold tracking-wider uppercase mb-3" style={{ color: PRIMARY_COLOR }}>Restaurant Features</p>
                        <h2 className="text-3xl sm:text-4xl font-bold text-gray-900">Everything Your Restaurant Needs</h2>
                        <p className="mt-4 text-gray-500 text-lg">A powerful suite of tools designed for modern restaurant operations.</p>
                    </div>

                    <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
                        {restaurantFeatures.map((f, i) => (
                            <div
                                key={i}
                                className="group relative rounded-2xl border border-gray-100 p-6 hover:shadow-xl hover:border-orange-100 transition-all duration-300 bg-white"
                            >
                                <div
                                    className="inline-flex items-center justify-center w-12 h-12 rounded-xl mb-4 transition-colors"
                                    style={{ backgroundColor: `${PRIMARY_COLOR}15`, color: PRIMARY_COLOR }}
                                >
                                    <f.icon className="w-6 h-6" />
                                </div>
                                <h3 className="text-lg font-semibold text-gray-900 mb-2">{f.title}</h3>
                                <p className="text-sm text-gray-500 leading-relaxed">{f.description}</p>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* ─── Admin Platform Features ─── */}
            <section id="admin" className="py-20 sm:py-28" style={{ backgroundColor: '#FFFBF5' }}>
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="text-center max-w-2xl mx-auto mb-16">
                        <p className="text-sm font-semibold tracking-wider uppercase mb-3" style={{ color: PRIMARY_COLOR }}>SaaS Platform</p>
                        <h2 className="text-3xl sm:text-4xl font-bold text-gray-900">Powerful Admin Management</h2>
                        <p className="mt-4 text-gray-500 text-lg">Enterprise-grade platform features for managing multiple restaurants.</p>
                    </div>

                    <div className="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
                        {adminFeatures.map((f, i) => (
                            <div
                                key={i}
                                className="group relative rounded-2xl border border-orange-100 p-6 hover:shadow-xl transition-all duration-300 bg-white"
                            >
                                <div
                                    className="inline-flex items-center justify-center w-12 h-12 rounded-xl mb-4 text-white transition-colors"
                                    style={{ backgroundColor: PRIMARY_COLOR }}
                                >
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
            <section id="how-it-works" className="py-20 sm:py-28 bg-white">
                <div className="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="text-center max-w-2xl mx-auto mb-16">
                        <p className="text-sm font-semibold tracking-wider uppercase mb-3" style={{ color: PRIMARY_COLOR }}>How It Works</p>
                        <h2 className="text-3xl sm:text-4xl font-bold text-gray-900">Up and Running in Minutes</h2>
                    </div>

                    <div className="grid sm:grid-cols-3 gap-10">
                        {steps.map((s, i) => (
                            <div key={i} className="text-center relative">
                                {i < steps.length - 1 && (
                                    <div className="hidden sm:block absolute top-8 left-[60%] w-[80%] h-0.5 bg-gradient-to-r from-orange-200 to-transparent" />
                                )}
                                <div
                                    className="inline-flex items-center justify-center w-16 h-16 rounded-full text-white text-xl font-bold mb-5 shadow-lg relative z-10"
                                    style={{ backgroundColor: PRIMARY_COLOR, boxShadow: `0 10px 30px -10px ${PRIMARY_COLOR}80` }}
                                >
                                    {s.number}
                                </div>
                                <h3 className="text-xl font-semibold text-gray-900 mb-2">{s.title}</h3>
                                <p className="text-gray-500">{s.description}</p>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* ─── Pricing ─── */}
            <section id="pricing" className="py-20 sm:py-28 bg-gray-50">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="text-center max-w-2xl mx-auto mb-16">
                        <p className="text-sm font-semibold tracking-wider uppercase mb-3" style={{ color: PRIMARY_COLOR }}>Pricing</p>
                        <h2 className="text-3xl sm:text-4xl font-bold text-gray-900">Simple, Transparent Pricing</h2>
                        <p className="mt-4 text-gray-500 text-lg">Choose the plan that fits your restaurant's needs.</p>
                    </div>

                    <div className="grid md:grid-cols-3 gap-8 max-w-5xl mx-auto">
                        {pricingPlans.map((plan, i) => (
                            <div
                                key={i}
                                className={`rounded-2xl p-8 ${plan.highlighted ? 'bg-white shadow-2xl border-2 scale-105 relative' : 'bg-white shadow-lg border border-gray-100'}`}
                                style={{ borderColor: plan.highlighted ? PRIMARY_COLOR : undefined }}
                            >
                                {plan.highlighted && (
                                    <div
                                        className="absolute -top-4 left-1/2 -translate-x-1/2 px-4 py-1 rounded-full text-white text-sm font-medium"
                                        style={{ backgroundColor: PRIMARY_COLOR }}
                                    >
                                        Most Popular
                                    </div>
                                )}
                                <h3 className="text-xl font-bold text-gray-900 mb-2">{plan.name}</h3>
                                <p className="text-gray-500 text-sm mb-4">{plan.description}</p>
                                <div className="mb-6">
                                    <span className="text-4xl font-bold text-gray-900">৳{plan.price}</span>
                                    <span className="text-gray-500">{plan.period}</span>
                                </div>
                                <ul className="space-y-3 mb-8">
                                    {plan.features.map((feature, j) => (
                                        <li key={j} className="flex items-center gap-3 text-sm text-gray-600">
                                            <HiOutlineCheckBadge className="w-5 h-5 flex-shrink-0" style={{ color: PRIMARY_COLOR }} />
                                            {feature}
                                        </li>
                                    ))}
                                </ul>
                                <Link
                                    to="/register"
                                    className={`block text-center py-3 rounded-xl font-semibold transition-all ${plan.highlighted ? 'text-white' : 'text-gray-700 border border-gray-200 hover:border-gray-300'}`}
                                    style={{ backgroundColor: plan.highlighted ? PRIMARY_COLOR : 'white' }}
                                >
                                    Get Started
                                </Link>
                            </div>
                        ))}
                    </div>
                </div>
            </section>

            {/* ─── CTA Banner ─── */}
            <section className="py-20" style={{ backgroundColor: PRIMARY_COLOR }}>
                <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                    <h2 className="text-3xl sm:text-4xl font-bold text-white mb-4">Ready to Transform Your Restaurant?</h2>
                    <p className="text-orange-100 text-lg mb-8 max-w-xl mx-auto">
                        Join hundreds of restaurant owners who are already saving time and boosting revenue with {platformName}.
                    </p>
                    <Link
                        to="/register"
                        className="inline-flex items-center gap-2 bg-white font-semibold px-8 py-3.5 rounded-xl hover:bg-orange-50 transition-colors shadow-lg"
                        style={{ color: PRIMARY_COLOR }}
                    >
                        Start Your Free Trial <HiOutlineArrowRight className="w-5 h-5" />
                    </Link>
                </div>
            </section>

            {/* ─── Contact Form ─── */}
            <section id="contact" className="py-20 sm:py-28 bg-white">
                <div className="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="text-center mb-12">
                        <p className="text-sm font-semibold tracking-wider uppercase mb-3" style={{ color: PRIMARY_COLOR }}>Contact Us</p>
                        <h2 className="text-3xl sm:text-4xl font-bold text-gray-900">Have a Question or Want a Demo?</h2>
                        <p className="mt-4 text-gray-500 text-lg">Fill out the form below and we'll get back to you shortly.</p>
                    </div>

                    {submitted ? (
                        <div className="text-center py-16 rounded-2xl border border-green-100 bg-green-50">
                            <HiOutlineCheckCircle className="w-16 h-16 text-green-500 mx-auto mb-4" />
                            <h3 className="text-2xl font-semibold text-gray-900 mb-2">Thank You!</h3>
                            <p className="text-gray-600 mb-6">We've received your enquiry and will be in touch soon.</p>
                            <button
                                onClick={() => setSubmitted(false)}
                                className="font-medium hover:underline"
                                style={{ color: PRIMARY_COLOR }}
                            >
                                Send another message
                            </button>
                        </div>
                    ) : (
                        <form onSubmit={handleSubmit} className="space-y-6 rounded-2xl p-8 sm:p-10 border border-gray-100 shadow-lg bg-white">
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
                                className="w-full sm:w-auto px-8 py-3.5 rounded-xl text-white font-semibold flex items-center justify-center gap-2 transition-all hover:opacity-90"
                                style={{ backgroundColor: PRIMARY_COLOR }}
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
            <footer className="bg-gray-900 text-gray-400 py-16">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="grid md:grid-cols-4 gap-10 mb-12">
                        {/* Brand */}
                        <div className="md:col-span-2">
                            <Link to="/" className="inline-block mb-4">
                                {logoSrc ? (
                                    <img
                                        src={branding.platform_logo_dark ? `/storage/${branding.platform_logo_dark}` : logoSrc}
                                        alt={platformName}
                                        className="h-10"
                                    />
                                ) : (
                                    <span className="text-2xl font-bold" style={{ color: PRIMARY_COLOR }}>{platformName}</span>
                                )}
                            </Link>
                            <p className="text-gray-500 max-w-md">
                                The complete restaurant management platform. From QR ordering to enterprise analytics,
                                we help restaurants of all sizes operate smarter.
                            </p>
                        </div>

                        {/* Links */}
                        <div>
                            <h4 className="text-white font-semibold mb-4">Product</h4>
                            <ul className="space-y-2 text-sm">
                                <li><a href="#features" className="hover:text-white transition-colors">Features</a></li>
                                <li><a href="#admin" className="hover:text-white transition-colors">Platform</a></li>
                                <li><a href="#pricing" className="hover:text-white transition-colors">Pricing</a></li>
                                <li><a href="#how-it-works" className="hover:text-white transition-colors">How It Works</a></li>
                            </ul>
                        </div>

                        <div>
                            <h4 className="text-white font-semibold mb-4">Company</h4>
                            <ul className="space-y-2 text-sm">
                                <li><a href="#contact" className="hover:text-white transition-colors">Contact</a></li>
                                <li><Link to="/login" className="hover:text-white transition-colors">Login</Link></li>
                                <li><Link to="/register" className="hover:text-white transition-colors">Sign Up</Link></li>
                            </ul>
                        </div>
                    </div>

                    <div className="pt-8 border-t border-gray-800 flex flex-col sm:flex-row items-center justify-between gap-4 text-sm">
                        <p>{branding.footer_text || `© ${new Date().getFullYear()} ${platformName}. All rights reserved.`}</p>
                        <div className="flex items-center gap-4">
                            <a href="#" className="hover:text-white transition-colors">Privacy Policy</a>
                            <a href="#" className="hover:text-white transition-colors">Terms of Service</a>
                        </div>
                    </div>
                </div>
            </footer>
        </div>
    );
}
