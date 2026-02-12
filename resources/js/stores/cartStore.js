import { create } from 'zustand';

export const useCartStore = create((set, get) => ({
    items: [],
    tenantId: null,
    tableId: null,
    orderType: 'dine', // dine | parcel
    customerName: '',
    customerPhone: '',
    voucherCode: '',
    voucherDiscount: 0,
    notes: '',

    setTenantId: (tenantId) => set({ tenantId }),
    setTableId: (tableId) => set({ tableId }),
    setOrderType: (type) => set({ orderType: type }),
    setCustomerName: (name) => set({ customerName: name }),
    setCustomerPhone: (phone) => set({ customerPhone: phone }),
    setVoucherCode: (code) => set({ voucherCode: code }),
    setVoucherDiscount: (discount) => set({ voucherDiscount: discount }),
    setNotes: (notes) => set({ notes }),

    addItem: (menuItem) => {
        const items = get().items;
        const existing = items.find((i) => i.menu_item_id === menuItem.id);

        if (existing) {
            set({
                items: items.map((i) =>
                    i.menu_item_id === menuItem.id
                        ? { ...i, qty: i.qty + 1 }
                        : i
                ),
            });
        } else {
            set({
                items: [
                    ...items,
                    {
                        menu_item_id: menuItem.id,
                        name: menuItem.name,
                        price: menuItem.price,
                        qty: 1,
                        special_instructions: '',
                    },
                ],
            });
        }
    },

    removeItem: (menuItemId) => {
        set({
            items: get().items.filter((i) => i.menu_item_id !== menuItemId),
        });
    },

    updateQty: (menuItemId, qty) => {
        if (qty <= 0) {
            get().removeItem(menuItemId);
            return;
        }
        set({
            items: get().items.map((i) =>
                i.menu_item_id === menuItemId ? { ...i, qty } : i
            ),
        });
    },

    updateInstructions: (menuItemId, instructions) => {
        set({
            items: get().items.map((i) =>
                i.menu_item_id === menuItemId
                    ? { ...i, special_instructions: instructions }
                    : i
            ),
        });
    },

    getSubtotal: () => {
        return get().items.reduce((sum, item) => sum + item.price * item.qty, 0);
    },

    getItemCount: () => {
        return get().items.reduce((sum, item) => sum + item.qty, 0);
    },

    clearCart: () => {
        set({
            items: [],
            voucherCode: '',
            voucherDiscount: 0,
            notes: '',
            customerName: '',
            customerPhone: '',
        });
    },

    getOrderPayload: () => {
        const state = get();
        return {
            table_id: state.tableId,
            type: state.orderType,
            customer_name: state.customerName,
            customer_phone: state.customerPhone,
            voucher_code: state.voucherCode || undefined,
            notes: state.notes || undefined,
            items: state.items.map((item) => ({
                menu_item_id: item.menu_item_id,
                qty: item.qty,
                special_instructions: item.special_instructions || undefined,
            })),
        };
    },
}));
