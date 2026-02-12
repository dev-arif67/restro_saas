import React, { useEffect, useRef } from 'react';
import { Outlet, useParams, useSearchParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { customerAPI } from '../services/api';
import { useCartStore } from '../stores/cartStore';
import { useBrandingStore } from '../stores/brandingStore';
import LoadingSpinner from '../components/ui/LoadingSpinner';
import PoweredBy from '../components/ui/PoweredBy';

export default function CustomerLayout() {
    const { slug } = useParams();
    const [searchParams] = useSearchParams();
    const { setTenantId, setTableId, setOrderType } = useCartStore();
    const { branding } = useBrandingStore();
    const prevTitle = useRef(document.title);
    const prevFavicon = useRef(document.querySelector("link[rel~='icon']")?.href);

    const tableId = searchParams.get('table');
    const orderType = searchParams.get('type') || (tableId ? 'dine' : 'parcel');

    React.useEffect(() => {
        if (tableId) setTableId(parseInt(tableId));
        setOrderType(orderType);
    }, [slug, tableId, orderType]);

    const { data: restaurant, isLoading, error } = useQuery({
        queryKey: ['restaurant', slug],
        queryFn: () => customerAPI.restaurant(slug).then((r) => r.data.data),
    });

    // Set document title and favicon to restaurant's branding
    useEffect(() => {
        if (restaurant) {
            // Title
            if (restaurant.name) {
                document.title = restaurant.name;
            }
            // Favicon
            if (restaurant.favicon) {
                let link = document.querySelector("link[rel~='icon']");
                if (!link) {
                    link = document.createElement('link');
                    link.rel = 'icon';
                    document.head.appendChild(link);
                }
                link.href = '/storage/' + restaurant.favicon;
            }
        }
        // Restore platform title/favicon on unmount
        return () => {
            document.title = prevTitle.current || branding.platform_name || 'RestaurantSaaS';
            const link = document.querySelector("link[rel~='icon']");
            if (link && prevFavicon.current) {
                link.href = prevFavicon.current;
            }
        };
    }, [restaurant]);

    if (isLoading) return <LoadingSpinner fullScreen />;

    if (error) {
        return (
            <div className="flex items-center justify-center min-h-screen bg-gray-50 px-4">
                <div className="text-center">
                    <h2 className="text-2xl font-bold text-gray-800 mb-2">Restaurant Unavailable</h2>
                    <p className="text-gray-500">This restaurant is currently not accepting orders.</p>
                </div>
            </div>
        );
    }

    return (
        <div className="min-h-screen bg-gray-50 flex flex-col">
            {/* Header */}
            <header className="bg-white shadow-sm sticky top-0 z-40" style={{ borderBottom: `3px solid ${restaurant?.primary_color || '#3B82F6'}` }}>
                <div className="max-w-lg mx-auto px-4 py-3 flex items-center">
                    {restaurant?.logo && (
                        <img src={`/storage/${restaurant.logo}`} alt="" className="w-8 h-8 rounded-full mr-3 object-cover" />
                    )}
                    <div>
                        <h1 className="text-lg font-bold text-gray-900">{restaurant?.name}</h1>
                        {tableId && (
                            <p className="text-xs text-gray-500">Table {tableId} &middot; Dine-in</p>
                        )}
                        {!tableId && (
                            <p className="text-xs text-gray-500">Takeaway Order</p>
                        )}
                    </div>
                </div>
            </header>

            {/* Banner Image */}
            {restaurant?.banner_image && (
                <div className="max-w-lg mx-auto w-full">
                    <img src={`/storage/${restaurant.banner_image}`} alt="" className="w-full h-40 object-cover" />
                </div>
            )}

            {/* Content */}
            <div className="max-w-lg mx-auto flex-1 w-full">
                <Outlet context={{ restaurant, slug, tableId, orderType }} />
            </div>

            {/* Footer Branding */}
            <footer className="py-4 text-center">
                <PoweredBy />
            </footer>
        </div>
    );
}
