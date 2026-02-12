import { create } from 'zustand';
import { persist } from 'zustand/middleware';

export const useAuthStore = create(
    persist(
        (set, get) => ({
            user: null,
            token: null,
            isAuthenticated: false,

            setAuth: (user, token) => {
                set({
                    user,
                    token,
                    isAuthenticated: true,
                });
            },

            updateUser: (userData) => {
                set((state) => ({
                    user: { ...state.user, ...userData },
                }));
            },

            logout: () => {
                set({
                    user: null,
                    token: null,
                    isAuthenticated: false,
                });
            },

            isSuperAdmin: () => get().user?.role === 'super_admin',
            isRestaurantAdmin: () => get().user?.role === 'restaurant_admin',
            isStaff: () => get().user?.role === 'staff',
            isKitchen: () => get().user?.role === 'kitchen',
            hasRole: (...roles) => roles.includes(get().user?.role),
        }),
        {
            name: 'auth-storage',
            partialize: (state) => ({
                user: state.user,
                token: state.token,
                isAuthenticated: state.isAuthenticated,
            }),
        }
    )
);
