import { create } from 'zustand';
import { CartItemRef } from './types';

interface CartState {
  items: CartItemRef[];
  isDrawerOpen: boolean;
  isHydrated: boolean;
  addItem: (productId: number, size: string, quantity?: number, isUnique?: boolean) => void;
  updateQuantity: (productId: number, size: string, quantity: number, isUnique?: boolean) => void;
  removeItem: (productId: number, size: string) => void;
  clearCart: () => void;
  setDrawerOpen: (open: boolean) => void;
  toggleDrawer: () => void;
  getTotalCount: () => number;
}

const STORAGE_KEY = 'athletica_cart_items';

function loadInitialItems(): CartItemRef[] {
  if (typeof window === 'undefined') return [];
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (!raw) return [];
    const parsed = JSON.parse(raw);
    return Array.isArray(parsed) ? parsed : [];
  } catch {
    return [];
  }
}

function saveItems(items: CartItemRef[]): void {
  if (typeof window === 'undefined') return;
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
  } catch {
    // Ignore storage quota errors
  }
}

export const useCartStore = create<CartState>((set, get) => ({
  items: [],
  isDrawerOpen: false,
  isHydrated: false,

  addItem: (productId, size, quantity = 1, isUnique = false) => {
    const cleanSize = size.trim();
    if (productId <= 0 || !cleanSize) return;

    const current = get().items;
    const existingIndex = current.findIndex(
      (i) => i.productId === productId && i.size.toLowerCase() === cleanSize.toLowerCase()
    );

    let updated: CartItemRef[];

    if (existingIndex > -1) {
      if (isUnique) {
        // Unique items cannot have more than 1 quantity
        updated = [...current];
        updated[existingIndex].quantity = 1;
      } else {
        updated = [...current];
        const currentQty = updated[existingIndex].quantity;
        updated[existingIndex].quantity = Math.min(5, currentQty + Math.max(1, quantity));
      }
    } else {
      const finalQty = isUnique ? 1 : Math.max(1, Math.min(5, quantity));
      updated = [...current, { productId, size: cleanSize, quantity: finalQty }];
    }

    saveItems(updated);
    set({ items: updated, isDrawerOpen: true });
  },

  updateQuantity: (productId, size, quantity, isUnique = false) => {
    const cleanSize = size.trim();
    const current = get().items;

    if (quantity <= 0) {
      get().removeItem(productId, size);
      return;
    }

    const updated = current.map((item) => {
      if (item.productId === productId && item.size.toLowerCase() === cleanSize.toLowerCase()) {
        const finalQty = isUnique ? 1 : Math.max(1, Math.min(5, quantity));
        return { ...item, quantity: finalQty };
      }
      return item;
    });

    saveItems(updated);
    set({ items: updated });
  },

  removeItem: (productId, size) => {
    const cleanSize = size.trim();
    const current = get().items;
    const updated = current.filter(
      (i) => !(i.productId === productId && i.size.toLowerCase() === cleanSize.toLowerCase())
    );
    saveItems(updated);
    set({ items: updated });
  },

  clearCart: () => {
    saveItems([]);
    set({ items: [] });
  },

  setDrawerOpen: (open) => set({ isDrawerOpen: open }),
  toggleDrawer: () => set((state) => ({ isDrawerOpen: !state.isDrawerOpen })),

  getTotalCount: () => {
    return get().items.reduce((sum, item) => sum + item.quantity, 0);
  },
}));

// Initialize client-side hydration on window load
if (typeof window !== 'undefined') {
  const initial = loadInitialItems();
  useCartStore.setState({ items: initial, isHydrated: true });
}
