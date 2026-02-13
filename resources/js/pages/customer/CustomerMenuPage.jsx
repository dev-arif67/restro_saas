import React, { useState } from 'react';
import { useParams, useOutletContext, Link, useSearchParams } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { customerAPI } from '../../services/api';
import { useCartStore } from '../../stores/cartStore';
import LoadingSpinner from '../../components/ui/LoadingSpinner';
import toast from 'react-hot-toast';

export default function CustomerMenuPage() {
    const { slug } = useParams();
    const [searchParams] = useSearchParams();
    const { restaurant } = useOutletContext() || {};
    const [selectedCategory, setSelectedCategory] = useState(null);
    const [searchText, setSearchText] = useState('');
    const { addItem, items: cartItems } = useCartStore();

    const { data: menu, isLoading } = useQuery({
        queryKey: ['customer-menu', slug],
        queryFn: () => customerAPI.menu(slug).then((r) => r.data.data),
    });

    const handleAdd = (item) => {
        addItem(item);
        toast.success(`${item.name} added to cart`, { duration: 1500, icon: '🛒' });
    };

    if (isLoading) return <LoadingSpinner />;

    const categories = menu?.categories || [];
    const allItems = categories.flatMap((c) => c.menu_items || []);

    const filteredItems = selectedCategory
        ? categories.find((c) => c.id === selectedCategory)?.menu_items || []
        : allItems;

    const displayItems = searchText
        ? filteredItems.filter((i) => i.name.toLowerCase().includes(searchText.toLowerCase()))
        : filteredItems;

    const cartCount = cartItems.reduce((s, i) => s + i.qty, 0);

    return (
        <div>
            {/* Search */}
            <div className="mb-4">
                <input
                    type="text"
                    className="w-full px-4 py-3 rounded-xl border border-gray-200 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none"
                    placeholder="Search menu..."
                    value={searchText}
                    onChange={(e) => setSearchText(e.target.value)}
                />
            </div>

            {/* Category Tabs */}
            <div className="flex gap-2 overflow-x-auto pb-3 mb-4 scrollbar-hide">
                <button
                    onClick={() => setSelectedCategory(null)}
                    className={`px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap ${!selectedCategory ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600'}`}
                >
                    All ({allItems.length})
                </button>
                {categories.map((cat) => (
                    <button
                        key={cat.id}
                        onClick={() => setSelectedCategory(cat.id)}
                        className={`px-4 py-2 rounded-full text-sm font-medium whitespace-nowrap ${selectedCategory === cat.id ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-600'}`}
                    >
                        {cat.name} ({cat.menu_items?.length || 0})
                    </button>
                ))}
            </div>

            {/* Items Grid */}
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 pb-24">
                {displayItems.map((item) => {
                    const inCart = cartItems.find((c) => c.menu_item_id === item.id);
                    const imgSrc = item.image_url || (item.image ? `/storage/${item.image}` : null);
                    return (
                        <div key={item.id} className="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden flex flex-col">
                            {imgSrc ? (
                                <div className="w-full h-36 bg-gray-100">
                                    <img src={imgSrc} alt={item.name} className="w-full h-full object-cover" />
                                </div>
                            ) : null}
                            <div className="p-4 flex justify-between items-start flex-1">
                                <div className="flex-1 mr-3">
                                    <h3 className="font-semibold text-gray-900">{item.name}</h3>
                                    {item.description && <p className="text-sm text-gray-400 mt-1 line-clamp-2">{item.description}</p>}
                                    <p className="text-lg font-bold text-blue-600 mt-2">৳{item.price}</p>
                                </div>
                                <button
                                    onClick={() => handleAdd(item)}
                                    className="flex-shrink-0 w-10 h-10 bg-blue-600 text-white rounded-full flex items-center justify-center text-xl hover:bg-blue-700 transition relative"
                                >
                                    +
                                    {inCart && (
                                        <span className="absolute -top-1 -right-1 w-5 h-5 bg-red-500 rounded-full text-xs flex items-center justify-center">
                                            {inCart.qty}
                                        </span>
                                    )}
                                </button>
                            </div>
                        </div>
                    );
                })}
            </div>

            {/* Floating Cart Button */}
            {cartCount > 0 && (
                <div className="fixed bottom-6 left-4 right-4 max-w-lg mx-auto">
                    <Link to={`/restaurant/${slug}/cart?${searchParams.toString()}`}
                       className="flex items-center justify-between bg-blue-600 text-white rounded-2xl py-4 px-6 shadow-lg">
                        <span className="font-medium">{cartCount} items</span>
                        <span className="font-bold text-lg">View Cart</span>
                        <span className="font-bold">৳{useCartStore.getState().getSubtotal()}</span>
                    </Link>
                </div>
            )}
        </div>
    );
}
