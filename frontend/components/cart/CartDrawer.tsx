'use client';

import React, { useEffect, useState, useTransition } from 'react';
import Image from 'next/image';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { X, ShoppingBag, Trash2, ArrowRight, AlertTriangle } from 'lucide-react';
import { useCartStore } from '@/lib/cart-store';
import { hydrateCart } from '@/lib/api';
import { HydratedCartResponse } from '@/lib/types';
import { formatPrice, getMediaUrl } from '@/lib/utils';

export function CartDrawer() {
  const router = useRouter();
  const { items, isDrawerOpen, setDrawerOpen, updateQuantity, removeItem } = useCartStore();
  const [hydratedData, setHydratedData] = useState<HydratedCartResponse | null>(null);
  const [isLoading, setIsLoading] = useState(false);
  const [, startTransition] = useTransition();

  useEffect(() => {
    if (!isDrawerOpen) return;

    if (items.length === 0) {
      setHydratedData(null);
      return;
    }

    setIsLoading(true);
    const payload = items.map((i) => ({
      product_id: i.productId,
      size: i.size,
      quantity: i.quantity,
    }));

    hydrateCart(payload)
      .then((data) => {
        setHydratedData(data);
      })
      .catch((err) => {
        console.error('Failed to hydrate cart in drawer:', err);
      })
      .finally(() => {
        setIsLoading(false);
      });
  }, [items, isDrawerOpen]);

  if (!isDrawerOpen) return null;

  const handleCheckoutClick = () => {
    setDrawerOpen(false);
    startTransition(() => {
      router.push('/checkout');
    });
  };

  const handleViewCartClick = () => {
    setDrawerOpen(false);
    startTransition(() => {
      router.push('/cart');
    });
  };

  return (
    <div className="fixed inset-0 z-50 flex justify-end animate-in fade-in duration-200">
      {/* Backdrop */}
      <div
        className="fixed inset-0 bg-black/75 backdrop-blur-sm"
        onClick={() => setDrawerOpen(false)}
      />

      {/* Drawer Panel */}
      <div className="relative w-full max-w-md h-full bg-athletica-card border-l border-athletica-border flex flex-col z-10 shadow-2xl animate-in slide-in-from-right duration-300">
        
        {/* Header */}
        <div className="p-4 border-b border-athletica-subtle flex items-center justify-between">
          <div className="flex items-center gap-2">
            <ShoppingBag className="w-5 h-5 text-athletica-secondary" />
            <h2 className="font-extrabold text-sm uppercase tracking-wider text-white">
              Your Shopping Cart ({items.reduce((s, i) => s + i.quantity, 0)})
            </h2>
          </div>
          <button
            type="button"
            onClick={() => setDrawerOpen(false)}
            className="p-1.5 text-athletica-secondary hover:text-white rounded hover:bg-athletica-surface transition-colors"
            aria-label="Close Cart"
          >
            <X className="w-5 h-5" />
          </button>
        </div>

        {/* Content Body */}
        <div className="flex-1 overflow-y-auto p-4 space-y-4">
          {items.length === 0 ? (
            <div className="h-full flex flex-col items-center justify-center text-center py-12 text-athletica-muted space-y-4">
              <ShoppingBag className="w-16 h-16 stroke-1 text-athletica-border" />
              <div>
                <p className="text-white font-bold text-base">Your cart is empty</p>
                <p className="text-xs text-athletica-secondary mt-1">
                  Explore our authentic imported footwear and gear.
                </p>
              </div>
              <button
                type="button"
                onClick={() => {
                  setDrawerOpen(false);
                  router.push('/shop');
                }}
                className="px-6 py-2.5 bg-white text-black text-xs font-extrabold uppercase tracking-wider rounded hover:bg-gray-200 transition-colors"
              >
                Browse Catalog
              </button>
            </div>
          ) : (
            <>
              {hydratedData?.has_unavailable_items && (
                <div className="p-3 bg-red-950/40 border border-athletica-red/40 rounded text-xs text-red-300 flex items-start gap-2">
                  <AlertTriangle className="w-4 h-4 text-athletica-red flex-shrink-0 mt-0.5" />
                  <span>
                    Some items in your cart are currently unavailable and must be removed to checkout.
                  </span>
                </div>
              )}

              {/* Items List */}
              <div className="space-y-3">
                {hydratedData?.items.map((item) => {
                  const isUnique = item.product_type === 'unique';
                  return (
                    <div
                      key={item.cart_key}
                      className={`p-3 rounded-lg border ${
                        item.is_available
                          ? 'bg-athletica-surface border-athletica-subtle'
                          : 'bg-red-950/20 border-red-900/50'
                      } flex gap-3`}
                    >
                      {/* Thumbnail */}
                      <div className="relative w-20 h-20 rounded bg-athletica-bg border border-athletica-border overflow-hidden flex-shrink-0">
                        <Image
                          src={getMediaUrl(item.primary_image)}
                          alt={item.name}
                          fill
                          className="object-cover"
                          sizes="80px"
                        />
                      </div>

                      {/* Details */}
                      <div className="flex-1 min-w-0 flex flex-col justify-between">
                        <div>
                          <div className="flex items-start justify-between gap-1">
                            <Link
                              href={`/product/${item.slug}`}
                              onClick={() => setDrawerOpen(false)}
                              className="text-xs font-bold text-white hover:text-athletica-red truncate block"
                            >
                              {item.name}
                            </Link>
                            <button
                              type="button"
                              onClick={() => removeItem(item.product_id, item.size)}
                              className="text-athletica-muted hover:text-athletica-red p-0.5"
                              title="Remove item"
                            >
                              <Trash2 className="w-3.5 h-3.5" />
                            </button>
                          </div>

                          <div className="flex items-center gap-2 mt-1 text-[11px] text-athletica-secondary">
                            <span className="bg-athletica-card px-1.5 py-0.5 rounded border border-athletica-border font-semibold">
                              Size: {item.size}
                            </span>
                            {isUnique && (
                              <span className="text-athletica-amber font-bold">1-Pair</span>
                            )}
                          </div>

                          {!item.is_available && (
                            <p className="text-[11px] text-athletica-red mt-1 font-semibold">
                              {item.unavailable_reason || 'Out of stock'}
                            </p>
                          )}
                        </div>

                        {/* Price & Quantity Controls */}
                        <div className="flex items-center justify-between mt-2 pt-1 border-t border-athletica-subtle/60">
                          <span className="text-xs font-extrabold text-white">
                            {formatPrice(item.line_total)}
                          </span>

                          <div className="flex items-center gap-1.5">
                            {!isUnique ? (
                              <div className="flex items-center border border-athletica-border rounded bg-athletica-input">
                                <button
                                  type="button"
                                  onClick={() =>
                                    updateQuantity(item.product_id, item.size, item.quantity - 1)
                                  }
                                  className="px-2 py-0.5 text-xs text-athletica-secondary hover:text-white"
                                >
                                  -
                                </button>
                                <span className="px-2 text-xs font-bold text-white">
                                  {item.quantity}
                                </span>
                                <button
                                  type="button"
                                  onClick={() =>
                                    updateQuantity(item.product_id, item.size, item.quantity + 1)
                                  }
                                  disabled={item.quantity >= 5}
                                  className="px-2 py-0.5 text-xs text-athletica-secondary hover:text-white disabled:opacity-30"
                                >
                                  +
                                </button>
                              </div>
                            ) : (
                              <span className="text-[11px] text-athletica-muted font-medium">
                                Qty: 1
                              </span>
                            )}
                          </div>
                        </div>
                      </div>
                    </div>
                  );
                })}
              </div>
            </>
          )}
        </div>

        {/* Footer Summary & Checkout */}
        {items.length > 0 && (
          <div className="p-4 border-t border-athletica-subtle bg-athletica-surface space-y-3">
            <div className="space-y-1.5 text-xs">
              <div className="flex justify-between text-athletica-secondary">
                <span>Subtotal:</span>
                <span className="font-bold text-white">
                  {formatPrice(hydratedData?.subtotal || '0.00')}
                </span>
              </div>
              <div className="flex justify-between text-athletica-secondary">
                <span>Delivery:</span>
                <span className="text-athletica-green font-bold uppercase">
                  {hydratedData?.delivery_fee === '0.00' ? 'FREE' : formatPrice(hydratedData?.delivery_fee)}
                </span>
              </div>
              <div className="flex justify-between text-sm font-black text-white pt-2 border-t border-athletica-border">
                <span>Total (COD):</span>
                <span>{formatPrice(hydratedData?.total_amount || '0.00')}</span>
              </div>
            </div>

            <div className="space-y-2 pt-1">
              <button
                type="button"
                onClick={handleCheckoutClick}
                disabled={isLoading || !hydratedData?.can_checkout}
                className="w-full py-3 bg-white text-black font-extrabold text-xs uppercase tracking-wider rounded flex items-center justify-center gap-2 hover:bg-gray-200 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
              >
                <span>Proceed to Checkout</span>
                <ArrowRight className="w-4 h-4" />
              </button>

              <button
                type="button"
                onClick={handleViewCartClick}
                className="w-full py-2.5 bg-transparent border border-athletica-border text-athletica-secondary hover:text-white hover:bg-athletica-hover font-bold text-xs uppercase tracking-wider rounded transition-colors"
              >
                View Full Cart
              </button>
            </div>
          </div>
        )}
      </div>
    </div>
  );
}
