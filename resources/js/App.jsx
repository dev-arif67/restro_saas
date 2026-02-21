import React, { Suspense, lazy } from 'react';
import { Routes, Route, Navigate } from 'react-router-dom';
import { useAuthStore } from './stores/authStore';
import LoadingSpinner from './components/ui/LoadingSpinner';

// Layouts
const DashboardLayout = lazy(() => import('./layouts/DashboardLayout'));
const CustomerLayout = lazy(() => import('./layouts/CustomerLayout'));

// Auth Pages
const LoginPage = lazy(() => import('./pages/auth/LoginPage'));
const RegisterPage = lazy(() => import('./pages/auth/RegisterPage'));

// Dashboard Pages
const DashboardPage = lazy(() => import('./pages/dashboard/DashboardPage'));
const MenuItemsPage = lazy(() => import('./pages/dashboard/MenuItemsPage'));
const CategoriesPage = lazy(() => import('./pages/dashboard/CategoriesPage'));
const TablesPage = lazy(() => import('./pages/dashboard/TablesPage'));
const OrdersPage = lazy(() => import('./pages/dashboard/OrdersPage'));
const VouchersPage = lazy(() => import('./pages/dashboard/VouchersPage'));
const ReportsPage = lazy(() => import('./pages/dashboard/ReportsPage'));
const SettlementsPage = lazy(() => import('./pages/dashboard/SettlementsPage'));
const UsersPage = lazy(() => import('./pages/dashboard/UsersPage'));
const SettingsPage = lazy(() => import('./pages/dashboard/SettingsPage'));

// POS
const POSPage = lazy(() => import('./pages/dashboard/POSPage'));

// Kitchen
const KitchenDisplayPage = lazy(() => import('./pages/kitchen/KitchenDisplayPage'));

// Customer
const CustomerMenuPage = lazy(() => import('./pages/customer/CustomerMenuPage'));
const CustomerCartPage = lazy(() => import('./pages/customer/CustomerCartPage'));
const OrderTrackingPage = lazy(() => import('./pages/customer/OrderTrackingPage'));

// Landing
const LandingPage = lazy(() => import('./pages/LandingPage'));

// Admin
const TenantsPage = lazy(() => import('./pages/admin/TenantsPage'));
const SubscriptionsPage = lazy(() => import('./pages/admin/SubscriptionsPage'));
const AdminSettingsPage = lazy(() => import('./pages/admin/AdminSettingsPage'));

function ProtectedRoute({ children, roles }) {
    const { user, token } = useAuthStore();

    if (!token || !user) {
        return <Navigate to="/login" replace />;
    }

    if (roles && !roles.includes(user.role)) {
        return <Navigate to="/dashboard" replace />;
    }

    return children;
}

export default function App() {
    return (
        <Suspense fallback={<LoadingSpinner fullScreen />}>
            <Routes>
                {/* Auth Routes */}
                <Route path="/login" element={<LoginPage />} />
                <Route path="/register" element={<RegisterPage />} />

                {/* Dashboard Routes */}
                <Route
                    path="/dashboard"
                    element={
                        <ProtectedRoute>
                            <DashboardLayout />
                        </ProtectedRoute>
                    }
                >
                    <Route index element={<DashboardPage />} />
                    <Route path="menu" element={<MenuItemsPage />} />
                    <Route path="categories" element={<CategoriesPage />} />
                    <Route path="tables" element={<TablesPage />} />
                    <Route path="orders" element={<OrdersPage />} />
                    <Route path="vouchers" element={<VouchersPage />} />
                    <Route path="reports" element={<ReportsPage />} />
                    <Route path="settlements" element={<SettlementsPage />} />
                    <Route path="users" element={<UsersPage />} />
                    <Route path="settings" element={<SettingsPage />} />
                    <Route path="pos" element={
                        <ProtectedRoute roles={['restaurant_admin', 'staff']}>
                            <POSPage />
                        </ProtectedRoute>
                    } />
                    {/* Admin routes */}
                    <Route path="tenants" element={<TenantsPage />} />
                    <Route path="subscriptions" element={<SubscriptionsPage />} />
                    <Route path="platform-settings" element={<AdminSettingsPage />} />
                </Route>

                {/* Kitchen Display */}
                <Route
                    path="/kitchen"
                    element={
                        <ProtectedRoute roles={['kitchen', 'restaurant_admin', 'super_admin']}>
                            <KitchenDisplayPage />
                        </ProtectedRoute>
                    }
                />

                {/* Customer Routes (public, no auth) */}
                <Route path="/restaurant/:slug" element={<CustomerLayout />}>
                    <Route index element={<CustomerMenuPage />} />
                    <Route path="cart" element={<CustomerCartPage />} />
                </Route>
                <Route path="/order/:orderNumber" element={<OrderTrackingPage />} />

                {/* Landing Page */}
                <Route path="/" element={<LandingPage />} />
                <Route path="*" element={<Navigate to="/" replace />} />
            </Routes>
        </Suspense>
    );
}
