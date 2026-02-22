import React, { useState, useRef, useEffect, useCallback } from 'react';
import { Outlet, NavLink, useNavigate } from 'react-router-dom';
import { useAuthStore } from '../stores/authStore';
import { useBrandingStore } from '../stores/brandingStore';
import { authAPI } from '../services/api';
import PoweredBy from '../components/ui/PoweredBy';
import AdminAIChat from '../components/ai/AdminAIChat';
import {
    HiOutlineHome,
    HiOutlineClipboardList,
    HiOutlineCollection,
    HiOutlineTag,
    HiOutlineTable,
    HiOutlineShoppingCart,
    HiOutlineChartBar,
    HiOutlineCash,
    HiOutlineUsers,
    HiOutlineCog,
    HiOutlineLogout,
    HiOutlineOfficeBuilding,
    HiOutlineCreditCard,
    HiOutlineMenu,
    HiOutlineX,
    HiOutlineDesktopComputer,
    HiOutlineBell,
    HiOutlineUser,
    HiOutlineChevronDown,
    HiOutlineSpeakerphone,
    HiOutlineServer,
    HiOutlineDocumentText,
    HiOutlineMail,
    HiOutlineTicket,
} from 'react-icons/hi';
import { HiArrowsPointingOut, HiArrowsPointingIn } from 'react-icons/hi2';

// Restaurant/Tenant menu items
const tenantMenuItems = [
    { icon: HiOutlineHome, label: 'Dashboard', to: '/dashboard', roles: ['restaurant_admin', 'staff'] },
    { icon: HiOutlineDesktopComputer, label: 'POS Terminal', to: '/dashboard/pos', roles: ['restaurant_admin', 'staff'] },
    { icon: HiOutlineShoppingCart, label: 'Orders', to: '/dashboard/orders', roles: ['restaurant_admin', 'staff'] },
    { icon: HiOutlineClipboardList, label: 'Menu Items', to: '/dashboard/menu', roles: ['restaurant_admin'] },
    { icon: HiOutlineCollection, label: 'Categories', to: '/dashboard/categories', roles: ['restaurant_admin'] },
    { icon: HiOutlineTable, label: 'Tables', to: '/dashboard/tables', roles: ['restaurant_admin', 'staff'] },
    { icon: HiOutlineTag, label: 'Vouchers', to: '/dashboard/vouchers', roles: ['restaurant_admin'] },
    { icon: HiOutlineChartBar, label: 'Reports', to: '/dashboard/reports', roles: ['restaurant_admin'] },
    { icon: HiOutlineCash, label: 'Settlements', to: '/dashboard/settlements', roles: ['restaurant_admin'] },
    { icon: HiOutlineUsers, label: 'Users', to: '/dashboard/users', roles: ['restaurant_admin'] },
    { icon: HiOutlineCog, label: 'Settings', to: '/dashboard/settings', roles: ['restaurant_admin'] },
];

// Super Admin menu items
const superAdminMenuItems = [
    { icon: HiOutlineHome, label: 'Dashboard', to: '/dashboard/admin', roles: ['super_admin'] },
    { icon: HiOutlineOfficeBuilding, label: 'Tenants', to: '/dashboard/admin/tenants', roles: ['super_admin'] },
    { icon: HiOutlineCreditCard, label: 'Subscriptions', to: '/dashboard/admin/subscriptions', roles: ['super_admin'] },
    { icon: HiOutlineTicket, label: 'Plans', to: '/dashboard/admin/plans', roles: ['super_admin'] },
    { icon: HiOutlineCash, label: 'Financials', to: '/dashboard/admin/financials', roles: ['super_admin'] },
    { icon: HiOutlineSpeakerphone, label: 'Announcements', to: '/dashboard/admin/announcements', roles: ['super_admin'] },
    { icon: HiOutlineMail, label: 'Enquiries', to: '/dashboard/admin/enquiries', roles: ['super_admin'] },
    { icon: HiOutlineServer, label: 'System', to: '/dashboard/admin/system', roles: ['super_admin'] },
    { icon: HiOutlineDocumentText, label: 'Audit Logs', to: '/dashboard/admin/audit-logs', roles: ['super_admin'] },
    { icon: HiOutlineCog, label: 'Platform Settings', to: '/dashboard/admin/settings', roles: ['super_admin'] },
];

// Combine based on user role
const getMenuItems = (userRole) => {
    if (userRole === 'super_admin') {
        return superAdminMenuItems;
    }
    return tenantMenuItems.filter(item => item.roles.includes(userRole));
};

export default function DashboardLayout() {
    const { user, logout, updateUser } = useAuthStore();
    const { branding } = useBrandingStore();
    const navigate = useNavigate();
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [sidebarCollapsed, setSidebarCollapsed] = useState(false);
    const [isFullscreen, setIsFullscreen] = useState(false);
    const [profileOpen, setProfileOpen] = useState(false);
    const [notificationsOpen, setNotificationsOpen] = useState(false);
    const profileRef = useRef(null);
    const notificationsRef = useRef(null);

    const handleLogout = () => {
        logout();
        navigate('/login');
    };

    const toggleFullscreen = useCallback(() => {
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen().then(() => setIsFullscreen(true)).catch(() => {});
        } else {
            document.exitFullscreen().then(() => setIsFullscreen(false)).catch(() => {});
        }
    }, []);

    // Refresh user + tenant data on mount so VAT settings are always current
    useEffect(() => {
        authAPI.me().then((res) => {
            const userData = res.data?.data?.user ?? res.data?.user;
            if (userData) updateUser(userData);
        }).catch(() => {});
    }, []);

    // Listen for fullscreen changes (e.g. user presses Esc)
    useEffect(() => {
        const handler = () => setIsFullscreen(!!document.fullscreenElement);
        document.addEventListener('fullscreenchange', handler);
        return () => document.removeEventListener('fullscreenchange', handler);
    }, []);

    // Close dropdowns on outside click
    useEffect(() => {
        const handler = (e) => {
            if (profileRef.current && !profileRef.current.contains(e.target)) {
                setProfileOpen(false);
            }
            if (notificationsRef.current && !notificationsRef.current.contains(e.target)) {
                setNotificationsOpen(false);
            }
        };
        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, []);

    const filteredMenu = getMenuItems(user?.role);

    const sidebarWidth = sidebarCollapsed ? 'w-[72px]' : 'w-64';

    const SidebarContent = ({ collapsed = false }) => (
        <>
            {/* Logo */}
            <div className="flex items-center justify-between h-16 px-4 border-b border-gray-200 shrink-0">
                <div className="flex items-center overflow-hidden">
                    {branding.platform_logo ? (
                        <img src={`/storage/${branding.platform_logo}`} alt={branding.platform_name} className="h-8" />
                    ) : (
                        <img src="/assets/images/logo.png" alt="Logo" className="h-8" />
                    )}
                    {!collapsed && branding.platform_name && (
                        <span className="ml-2 text-sm font-bold text-gray-800 truncate hidden lg:inline">{branding.platform_name}</span>
                    )}
                </div>
                {/* Close button for mobile overlay */}
                <button onClick={() => setSidebarOpen(false)} className="md:hidden text-gray-400 hover:text-gray-600">
                    <HiOutlineX className="w-5 h-5" />
                </button>
            </div>

            {/* Navigation */}
            <nav className="flex-1 px-2 py-4 space-y-1 overflow-y-auto">
                {filteredMenu.map((item) => (
                    <NavLink
                        key={item.to}
                        to={item.to}
                        end={item.to === '/dashboard'}
                        onClick={() => setSidebarOpen(false)}
                        title={collapsed ? item.label : undefined}
                        className={({ isActive }) =>
                            `flex items-center ${collapsed ? 'justify-center px-2' : 'px-3'} py-2.5 text-sm font-medium rounded-lg transition-colors ${
                                isActive
                                    ? 'bg-blue-50 text-blue-700'
                                    : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'
                            }`
                        }
                    >
                        <item.icon className="w-5 h-5 shrink-0" />
                        {!collapsed && <span className="ml-3 truncate">{item.label}</span>}
                    </NavLink>
                ))}

                {/* Kitchen Display Link */}
                {user?.role !== 'super_admin' && (
                    <NavLink
                        to="/kitchen"
                        onClick={() => setSidebarOpen(false)}
                        title={collapsed ? 'Kitchen Display' : undefined}
                        className={`flex items-center ${collapsed ? 'justify-center px-2' : 'px-3'} py-2.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg`}
                    >
                        <HiOutlineClipboardList className="w-5 h-5 shrink-0" />
                        {!collapsed && <span className="ml-3">Kitchen Display</span>}
                    </NavLink>
                )}
            </nav>

            {/* Bottom section */}
            {!collapsed && (
                <div className="border-t border-gray-200 p-4 shrink-0">
                    {user?.role !== 'super_admin' && (
                        <div className="text-center">
                            <PoweredBy />
                        </div>
                    )}
                </div>
            )}
        </>
    );

    return (
        <div className="flex h-screen bg-gray-50">
            {/* Mobile Sidebar Overlay */}
            {sidebarOpen && (
                <div className="fixed inset-0 z-40 md:hidden">
                    <div className="fixed inset-0 bg-black/50" onClick={() => setSidebarOpen(false)} />
                    <aside className="fixed inset-y-0 left-0 z-50 w-64 bg-white flex flex-col shadow-xl">
                        <SidebarContent collapsed={false} />
                    </aside>
                </div>
            )}

            {/* Desktop Sidebar */}
            <aside className={`hidden md:flex md:flex-col ${sidebarWidth} bg-white border-r border-gray-200 shrink-0 transition-all duration-300`}>
                <SidebarContent collapsed={sidebarCollapsed} />
            </aside>

            {/* Main Content Area */}
            <div className="flex-1 flex flex-col min-w-0">
                {/* Header */}
                <header className="flex items-center justify-between h-16 px-4 md:px-6 bg-white border-b border-gray-200 sticky top-0 z-30 shrink-0">
                    {/* Left: Sidebar Toggle */}
                    <div className="flex items-center gap-2">
                        {/* Mobile hamburger */}
                        <button
                            onClick={() => setSidebarOpen(true)}
                            className="md:hidden text-gray-500 hover:text-gray-700 p-1.5 rounded-lg hover:bg-gray-100 transition-colors"
                        >
                            <HiOutlineMenu className="w-6 h-6" />
                        </button>
                        {/* Desktop sidebar toggle */}
                        <button
                            onClick={() => setSidebarCollapsed((prev) => !prev)}
                            className="hidden md:flex text-gray-500 hover:text-gray-700 p-1.5 rounded-lg hover:bg-gray-100 transition-colors"
                            title={sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar'}
                        >
                            <HiOutlineMenu className="w-6 h-6" />
                        </button>
                    </div>

                    {/* Right: Actions */}
                    <div className="flex items-center gap-1 sm:gap-2">
                        {/* Fullscreen Toggle */}
                        <button
                            onClick={toggleFullscreen}
                            className="hidden sm:flex text-gray-500 hover:text-gray-700 p-2 rounded-lg hover:bg-gray-100 transition-colors"
                            title={isFullscreen ? 'Exit fullscreen' : 'Fullscreen'}
                        >
                            {isFullscreen ? (
                                <HiArrowsPointingIn className="w-5 h-5" />
                            ) : (
                                <HiArrowsPointingOut className="w-5 h-5" />
                            )}
                        </button>

                        {/* Notifications */}
                        <div className="relative" ref={notificationsRef}>
                            <button
                                onClick={() => { setNotificationsOpen((v) => !v); setProfileOpen(false); }}
                                className="relative text-gray-500 hover:text-gray-700 p-2 rounded-lg hover:bg-gray-100 transition-colors"
                                title="Notifications"
                            >
                                <HiOutlineBell className="w-5 h-5" />
                                {/* Unread dot */}
                                <span className="absolute top-1.5 right-1.5 w-2 h-2 bg-red-500 rounded-full ring-2 ring-white" />
                            </button>
                            {notificationsOpen && (
                                <div className="absolute right-0 mt-2 w-80 bg-white rounded-xl shadow-lg ring-1 ring-gray-200 z-50">
                                    <div className="px-4 py-3 border-b border-gray-100">
                                        <h3 className="text-sm font-semibold text-gray-900">Notifications</h3>
                                    </div>
                                    <div className="max-h-72 overflow-y-auto divide-y divide-gray-100">
                                        <div className="px-4 py-8 text-center text-sm text-gray-400">
                                            No new notifications
                                        </div>
                                    </div>
                                </div>
                            )}
                        </div>

                        {/* Profile Dropdown */}
                        <div className="relative" ref={profileRef}>
                            <button
                                onClick={() => { setProfileOpen((v) => !v); setNotificationsOpen(false); }}
                                className="flex items-center gap-2 p-1.5 rounded-lg hover:bg-gray-100 transition-colors"
                            >
                                <div className="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center text-white text-sm font-medium shrink-0">
                                    {user?.name?.[0]?.toUpperCase()}
                                </div>
                                <div className="hidden sm:block text-left min-w-0">
                                    <p className="text-sm font-medium text-gray-700 truncate max-w-[120px]">{user?.name}</p>
                                    <p className="text-xs text-gray-400 capitalize">{user?.role?.replace('_', ' ')}</p>
                                </div>
                                <HiOutlineChevronDown className="w-4 h-4 text-gray-400 hidden sm:block" />
                            </button>
                            {profileOpen && (
                                <div className="absolute right-0 mt-2 w-56 bg-white rounded-xl shadow-lg ring-1 ring-gray-200 z-50 py-1">
                                    <div className="px-4 py-3 border-b border-gray-100">
                                        <p className="text-sm font-medium text-gray-900 truncate">{user?.name}</p>
                                        <p className="text-xs text-gray-500 truncate">{user?.email}</p>
                                    </div>
                                    <button
                                        onClick={() => { setProfileOpen(false); navigate('/dashboard/settings'); }}
                                        className="flex items-center w-full px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors"
                                    >
                                        <HiOutlineUser className="w-4 h-4 mr-3 text-gray-400" />
                                        Profile & Settings
                                    </button>
                                    <div className="border-t border-gray-100">
                                        <button
                                            onClick={handleLogout}
                                            className="flex items-center w-full px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 transition-colors"
                                        >
                                            <HiOutlineLogout className="w-4 h-4 mr-3" />
                                            Logout
                                        </button>
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </header>

                {/* Page Content */}
                <main className="flex-1 overflow-y-auto">
                    <div className="p-4 md:p-6 lg:p-8">
                        <Outlet />
                    </div>
                </main>
            </div>

            {/* AI Analytics Assistant - Available for restaurant_admin */}
            {user?.role === 'restaurant_admin' && <AdminAIChat />}
        </div>
    );
}
