import { create } from 'zustand';

export const useBrandingStore = create((set) => ({
    branding: {
        platform_name: 'RestaurantSaaS',
        platform_logo: null,
        platform_logo_dark: null,
        platform_favicon: null,
        primary_color: '#3B82F6',
        secondary_color: '#1E40AF',
        footer_text: null,
        powered_by_text: 'Powered by RestaurantSaaS',
        powered_by_url: null,
    },
    loaded: false,

    setBranding: (data) =>
        set({
            branding: { ...data },
            loaded: true,
        }),
}));
