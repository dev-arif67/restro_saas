import React, { useState } from 'react';
import { Outlet, NavLink, useNavigate } from 'react-router-dom';
import { useAuthStore } from '../stores/authStore';
import { useBrandingStore } from '../stores/brandingStore';
import PoweredBy from '../components/ui/PoweredBy';
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
} from 'react-icons/hi';

const menuItems = [
    { icon: HiOutlineHome, label: 'Dashboard', to: '/dashboard', roles: ['super_admin', 'restaurant_admin', 'staff'] },
    { icon: HiOutlineShoppingCart, label: 'Orders', to: '/dashboard/orders', roles: ['restaurant_admin', 'staff'] },
    { icon: HiOutlineClipboardList, label: 'Menu Items', to: '/dashboard/menu', roles: ['restaurant_admin'] },
    { icon: HiOutlineCollection, label: 'Categories', to: '/dashboard/categories', roles: ['restaurant_admin'] },
    { icon: HiOutlineTable, label: 'Tables', to: '/dashboard/tables', roles: ['restaurant_admin', 'staff'] },
    { icon: HiOutlineTag, label: 'Vouchers', to: '/dashboard/vouchers', roles: ['restaurant_admin'] },
    { icon: HiOutlineChartBar, label: 'Reports', to: '/dashboard/reports', roles: ['restaurant_admin'] },
    { icon: HiOutlineCash, label: 'Settlements', to: '/dashboard/settlements', roles: ['restaurant_admin'] },
    { icon: HiOutlineUsers, label: 'Users', to: '/dashboard/users', roles: ['restaurant_admin'] },
    { icon: HiOutlineCog, label: 'Settings', to: '/dashboard/settings', roles: ['restaurant_admin'] },
    // Super admin
    { icon: HiOutlineOfficeBuilding, label: 'Tenants', to: '/dashboard/tenants', roles: ['super_admin'] },
    { icon: HiOutlineCreditCard, label: 'Subscriptions', to: '/dashboard/subscriptions', roles: ['super_admin'] },
    { icon: HiOutlineCog, label: 'Platform Settings', to: '/dashboard/platform-settings', roles: ['super_admin'] },
];

export default function DashboardLayout() {
    const { user, logout } = useAuthStore();
    const { branding } = useBrandingStore();
    const navigate = useNavigate();
    const [sidebarOpen, setSidebarOpen] = useState(false);

    const handleLogout = () => {
        logout();
        navigate('/login');
    };

    const filteredMenu = menuItems.filter(
        (item) => item.roles.includes(user?.role)
    );

    const SidebarContent = () => (
        <>
            {/* Logo */}
            <div className="flex items-center justify-between h-16 px-6 border-b border-gray-200 shrink-0">
                <div className="flex items-center">
                    {branding.platform_logo ? (
                        <img src={`/storage/${branding.platform_logo}`} alt={branding.platform_name} className="h-8" />
                    ) : (
                        <img src="/assets/images/logo.png" alt="Logo" className="ml-2 h-8" />
                    )}
                </div>
                <button onClick={() => setSidebarOpen(false)} className="md:hidden text-gray-400 hover:text-gray-600">
                    <HiOutlineX className="w-5 h-5" />
                </button>
            </div>

            {/* Navigation */}
            <nav className="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
                {filteredMenu.map((item) => (
                    <NavLink
                        key={item.to}
                        to={item.to}
                        end={item.to === '/dashboard'}
                        onClick={() => setSidebarOpen(false)}
                        className={({ isActive }) =>
                            `flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors ${
                                isActive
                                    ? 'bg-blue-50 text-blue-700'
                                    : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900'
                            }`
                        }
                    >
                        <item.icon className="w-5 h-5 mr-3" />
                        {item.label}
                    </NavLink>
                ))}

                {/* Kitchen Display Link */}
                {user?.role !== 'super_admin' && (
                    <NavLink
                        to="/kitchen"
                        onClick={() => setSidebarOpen(false)}
                        className="flex items-center px-3 py-2.5 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-lg"
                    >
                        <HiOutlineClipboardList className="w-5 h-5 mr-3" />
                        Kitchen Display
                    </NavLink>
                )}
            </nav>

            {/* User */}
            <div className="border-t border-gray-200 p-4 shrink-0">
                <div className="flex items-center">
                    <div className="w-8 h-8 bg-blue-600 rounded-full flex items-center justify-center text-white text-sm font-medium shrink-0">
                        {user?.name?.[0]?.toUpperCase()}
                    </div>
                    <div className="ml-3 flex-1 min-w-0">
                        <p className="text-sm font-medium text-gray-900 truncate">{user?.name}</p>
                        <p className="text-xs text-gray-500 capitalize">{user?.role?.replace('_', ' ')}</p>
                    </div>
                    <button onClick={handleLogout} className="text-gray-400 hover:text-red-500 shrink-0">
                        <HiOutlineLogout className="w-5 h-5" />
                    </button>
                </div>
                {user?.role !== 'super_admin' && (
                    <div className="mt-3 pt-3 border-t border-gray-100 text-center">
                        <PoweredBy />
                    </div>
                )}
            </div>
        </>
    );

    return (
        <div className="flex h-screen bg-gray-50">
            {/* Mobile Sidebar Overlay */}
            {sidebarOpen && (
                <div className="fixed inset-0 z-40 md:hidden">
                    <div className="fixed inset-0 bg-black/50" onClick={() => setSidebarOpen(false)} />
                    <aside className="fixed inset-y-0 left-0 z-50 w-64 bg-white flex flex-col shadow-xl">
                        <SidebarContent />
                    </aside>
                </div>
            )}

            {/* Desktop Sidebar */}
            <aside className="hidden md:flex md:flex-col w-64 bg-white border-r border-gray-200 shrink-0">
                <SidebarContent />
            </aside>

            {/* Main Content */}
            <main className="flex-1 overflow-y-auto min-w-0">
                {/* Top Bar (mobile) */}
                <header className="md:hidden flex items-center justify-between h-14 px-4 bg-white border-b border-gray-200 sticky top-0 z-30">
                    <button onClick={() => setSidebarOpen(true)} className="text-gray-600 hover:text-gray-900">
                        <HiOutlineMenu className="w-6 h-6" />
                    </button>
                    {branding.platform_logo ? (
                        <img src={`/storage/${branding.platform_logo}`} alt={branding.platform_name} className="h-7 truncate px-3" />
                    ) : (
                        <h1 className="text-lg font-bold text-blue-600 truncate px-3">{branding.platform_name || 'RestaurantSaaS'}</h1>
                    )}
                    <button onClick={handleLogout} className="text-gray-500 shrink-0">
                        <HiOutlineLogout className="w-5 h-5" />
                    </button>
                </header>

                <div className="p-4 md:p-6 lg:p-8">
                    <Outlet />
                </div>
            </main>
        </div>
    );
}
