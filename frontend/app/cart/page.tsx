'use client';

import React, { useEffect, useState } from 'react';
import Link from 'next/link';
import Image from 'next/image';
import { useRouter } from 'next/navigation';
import { useCartStore } from '@/lib/cart-store';
import { hydrateCart } from '@/lib/api';
import { HydratedCartResponse } from '@/lib/types';
import { formatPrice, getMediaUrl } from '@/lib/utils';
import { ShoppingBag, Trash2, ArrowRight, AlertTriangle, ShieldCheck } from 'lucide-react';

export default function CartPage() {
  const router = useRouter();
  const { items, updateQuantity, removeItem } = useCartStore();
  const [hydratedData, setHydratedData] = useState<HydratedCartResponse | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    if (items.length === 0) {
      setHydratedData(null);
      setIsLoading(false);
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
        console.error('Failed to hydrate cart:', err);
      })
      .finally(() => {
        setIsLoading(false);
      });
  }, [items]);

  if (items.length === 0) {
    return (
      <div className="max-w-4xl mx-auto px-4 py-20 text-center space-y-6">
        <div className="w-20 h-20 rounded-full bg-athletica-card border border-athletica-border flex items-center justify-center mx-auto text-athletica-muted">
          <ShoppingBag className="w-10 h-10" />
        </div>
        <div className="space-y-2">
          <h1 className="text-2xl sm:text-3xl font-black uppercase text-white">
            Your Cart is Empty
          </h1>
          <p className="text-xs sm:text-sm text-athletica-secondary max-w-md mx-auto">
            You haven't added any items to your cart yet. Explore our genuine imported footwear catalog.
          </p>
        </div>
        <Link
          href="/shop"
          className="inline-flex items-center gap-2 px-8 py-3.5 rounded bg-white text-black font-extrabold text-xs uppercase tracking-wider hover:bg-gray-200 transition-colors"
        >
          <span>Explore All Products</span>
          <ArrowRight className="w-4 h-4" />
        </Link>
      </div>
    );
  }

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12 space-y-8">
      {/* Breadcrumb */}
      <nav className="flex items-center gap-2 text-xs font-bold text-athletica-muted uppercase tracking-wider">
        <Link href="/" className="hover:text-white transition-colors">
          Home
        </Link>
        <span>/</span>
        <span className="text-white">Shopping Cart</span>
      </nav>

      <div className="border-b border-athletica-subtle pb-4">
        <h1 className="text-2xl sm:text-3xl font-black uppercase tracking-tight text-white">
          Shopping Cart ({items.reduce((s, i) => s + i.quantity, 0)})
        </h1>
      </div>

      {hydratedData?.has_unavailable_items && (
        <div className="p-4 bg-red-950/40 border border-athletica-red/40 rounded-xl text-xs text-red-200 flex items-center gap-3">
          <AlertTriangle className="w-5 h-5 text-athletica-red flex-shrink-0" />
          <span>
            One or more items in your cart are currently out of stock. Please remove unavailable items to continue to checkout.
          </span>
        </div>
      )}

      {/* Cart Grid Layout */}
      <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        
        {/* Left Column: Items List */}
        <div className="lg:col-span-8 space-y-4">
          <div className="bg-athletica-card border border-athletica-subtle rounded-xl divide-y divide-athletica-subtle overflow-hidden">
            {hydratedData?.items.map((item) => {
              const isUnique = item.product_type === 'unique';
              return (
                <div
                  key={item.cart_key}
                  className={`p-4 sm:p-6 flex flex-col sm:flex-row gap-4 sm:items-center justify-between ${
                    !item.is_available ? 'bg-red-950/20' : ''
                  }`}
                >
                  {/* Image & Title */}
                  <div className="flex gap-4 items-center">
                    <div className="relative w-20 h-20 sm:w-24 sm:h-24 rounded-lg bg-athletica-surface border border-athletica-border overflow-hidden flex-shrink-0">
                      <Image
                        src={getMediaUrl(item.primary_image)}
                        alt={item.name}
                        fill
                        className="object-cover"
                        sizes="96px"
                      />
                    </div>

                    <div className="space-y-1 min-w-0">
                      <Link
                        href={`/product/${item.slug}`}
                        className="text-sm font-bold text-white hover:text-athletica-red transition-colors line-clamp-1"
                      >
                        {item.name}
                      </Link>

                      <div className="flex items-center gap-2 text-xs text-athletica-secondary">
                        <span className="px-2 py-0.5 rounded bg-athletica-surface border border-athletica-border font-semibold">
                          Size: {item.size}
                        </span>
                        {isUnique && (
                          <span className="text-amber-500 font-bold text-[11px]">
                            1-Pair Exclusive
                          </span>
                        )}
                      </div>

                      <div className="text-xs text-athletica-muted">
                        Unit Price: {formatPrice(item.unit_price)}
                      </div>

                      {!item.is_available && (
                        <p className="text-xs text-athletica-red font-bold pt-1">
                          {item.unavailable_reason || 'Item unavailable'}
                        </p>
                      )}
                    </div>
                  </div>

                  {/* Quantity Controls & Line Total */}
                  <div className="flex items-center justify-between sm:justify-end gap-6 pt-2 sm:pt-0 border-t sm:border-t-0 border-athletica-subtle">
                    {!isUnique ? (
                      <div className="flex items-center border border-athletica-border rounded bg-athletica-surface">
                        <button
                          type="button"
                          onClick={() =>
                            updateQuantity(item.product_id, item.size, item.quantity - 1)
                          }
                          className="px-2.5 py-1 text-xs text-athletica-secondary hover:text-white"
                        >
                          -
                        </button>
                        <span className="px-3 text-xs font-bold text-white">
                          {item.quantity}
                        </span>
                        <button
                          type="button"
                          onClick={() =>
                            updateQuantity(item.product_id, item.size, item.quantity + 1)
                          }
                          disabled={item.quantity >= 5}
                          className="px-2.5 py-1 text-xs text-athletica-secondary hover:text-white disabled:opacity-30"
                        >
                          +
                        </button>
                      </div>
                    ) : (
                      <span className="text-xs text-athletica-muted font-bold px-3 py-1 bg-athletica-surface rounded border border-athletica-border">
                        Qty: 1
                      </span>
                    )}

                    <div className="text-right min-w-[100px]">
                      <span className="text-sm sm:text-base font-black text-white block">
                        {formatPrice(item.line_total)}
                      </span>
                      <button
                        type="button"
                        onClick={() => removeItem(item.product_id, item.size)}
                        className="text-xs text-athletica-muted hover:text-athletica-red transition-colors inline-flex items-center gap-1 mt-0.5"
                      >
                        <Trash2 className="w-3 h-3" />
                        <span>Remove</span>
                      </button>
                    </div>
                  </div>
                </div>
              );
            })}
          </div>

          <div className="flex justify-between items-center text-xs pt-2">
            <Link
              href="/shop"
              className="text-athletica-secondary hover:text-white font-bold uppercase tracking-wider"
            >
              ← Continue Shopping
            </Link>
          </div>
        </div>

        {/* Right Column: Order Summary */}
        <div className="lg:col-span-4 space-y-4">
          <div className="p-6 rounded-xl bg-athletica-card border border-athletica-subtle space-y-4">
            <h2 className="text-xs font-black uppercase tracking-widest text-white border-b border-athletica-subtle pb-3">
              Order Summary
            </h2>

            <div className="space-y-2.5 text-xs">
              <div className="flex justify-between text-athletica-secondary">
                <span>Subtotal ({hydratedData?.total_quantity || 0} items):</span>
                <span className="font-bold text-white">
                  {formatPrice(hydratedData?.subtotal || '0.00')}
                </span>
              </div>

              <div className="flex justify-between text-athletica-secondary">
                <span>Nationwide Shipping:</span>
                <span className="text-athletica-green font-extrabold uppercase">
                  {hydratedData?.delivery_fee === '0.00' ? 'FREE' : formatPrice(hydratedData?.delivery_fee)}
                </span>
              </div>

              <div className="flex justify-between text-base font-black text-white pt-3 border-t border-athletica-subtle">
                <span>Total Due at Delivery:</span>
                <span>{formatPrice(hydratedData?.total_amount || '0.00')}</span>
              </div>
            </div>

            <button
              type="button"
              onClick={() => router.push('/checkout')}
              disabled={isLoading || !hydratedData?.can_checkout}
              className="w-full py-4 bg-white text-black font-extrabold text-xs uppercase tracking-wider rounded-lg flex items-center justify-center gap-2 hover:bg-gray-200 transition-all disabled:opacity-40 disabled:cursor-not-allowed shadow-lg shadow-white/10"
            >
              <span>Proceed to COD Checkout</span>
              <ArrowRight className="w-4 h-4" />
            </button>

            <div className="pt-2 flex items-center gap-2 text-[11px] text-athletica-muted justify-center">
              <ShieldCheck className="w-4 h-4 text-athletica-green" />
              <span>Cash on Delivery across Pakistan</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
