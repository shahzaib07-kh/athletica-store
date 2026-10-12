'use client';

import React, { useEffect, useState } from 'react';
import Link from 'next/link';
import Image from 'next/image';
import { useRouter } from 'next/navigation';
import { useCartStore } from '@/lib/cart-store';
import { hydrateCart, submitCheckout } from '@/lib/api';
import { CheckoutCustomer, HydratedCartResponse } from '@/lib/types';
import { formatPrice, getMediaUrl } from '@/lib/utils';
import { Lock, Truck, AlertTriangle, ArrowLeft, CheckCircle2 } from 'lucide-react';

export default function CheckoutPage() {
  const router = useRouter();
  const { items, clearCart } = useCartStore();

  const [hydratedData, setHydratedData] = useState<HydratedCartResponse | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [globalError, setGlobalError] = useState('');
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

  const [formData, setFormData] = useState<CheckoutCustomer>({
    name: '',
    phone: '',
    email: '',
    city: 'Lahore',
    delivery_address: '',
  });

  useEffect(() => {
    if (items.length === 0) {
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
        console.error('Failed to hydrate cart on checkout:', err);
      })
      .finally(() => {
        setIsLoading(false);
      });
  }, [items]);

  if (!isLoading && items.length === 0) {
    return (
      <div className="max-w-xl mx-auto px-4 py-20 text-center space-y-4">
        <h1 className="text-2xl font-black uppercase text-white">Your Cart is Empty</h1>
        <p className="text-xs text-athletica-secondary">
          Please add items to your cart before proceeding to checkout.
        </p>
        <Link
          href="/shop"
          className="inline-block px-6 py-3 rounded bg-white text-black font-extrabold text-xs uppercase tracking-wider"
        >
          Return to Shop
        </Link>
      </div>
    );
  }

  const handleInputChange = (e: React.ChangeEvent<HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement>) => {
    const { name, value } = e.target;
    setFormData((prev) => ({ ...prev, [name]: value }));
    if (fieldErrors[`customer.${name}`]) {
      setFieldErrors((prev) => {
        const copy = { ...prev };
        delete copy[`customer.${name}`];
        return copy;
      });
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setGlobalError('');
    setFieldErrors({});

    if (!hydratedData?.can_checkout) {
      setGlobalError('Please resolve any out-of-stock items in your cart before checking out.');
      return;
    }

    setIsSubmitting(true);

    try {
      const payload = {
        customer: formData,
        items: items.map((i) => ({
          product_id: i.productId,
          size: i.size,
          quantity: i.quantity,
        })),
      };

      const res = await submitCheckout(payload);

      // Save order payload in session storage for immediate success page rendering
      if (typeof window !== 'undefined') {
        sessionStorage.setItem(`order_${res.order_number}`, JSON.stringify(res));
      }

      // Clear client cart
      clearCart();

      // Navigate to order success page
      router.push(`/order-success?order_number=${encodeURIComponent(res.order_number)}`);

    } catch (err: any) {
      console.error('Checkout error:', err);
      if (err.fields) {
        setFieldErrors(err.fields);
      }
      setGlobalError(err.message || 'Failed to place order. Please try again.');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12 space-y-8">
      {/* Breadcrumb */}
      <nav className="flex items-center gap-2 text-xs font-bold text-athletica-muted uppercase tracking-wider">
        <Link href="/cart" className="hover:text-white transition-colors flex items-center gap-1">
          <ArrowLeft className="w-3.5 h-3.5" />
          <span>Back to Cart</span>
        </Link>
        <span>/</span>
        <span className="text-white">Cash on Delivery Checkout</span>
      </nav>

      <div className="border-b border-athletica-subtle pb-4">
        <h1 className="text-2xl sm:text-3xl font-black uppercase tracking-tight text-white">
          Secure Checkout
        </h1>
        <p className="text-xs text-athletica-secondary mt-1">
          Enter your delivery details to place your Cash on Delivery order
        </p>
      </div>

      {globalError && (
        <div className="p-4 bg-red-950/50 border border-athletica-red/50 rounded-xl text-xs text-red-200 flex items-start gap-3 animate-in fade-in">
          <AlertTriangle className="w-5 h-5 text-athletica-red flex-shrink-0 mt-0.5" />
          <div>
            <p className="font-bold">Checkout Notice</p>
            <p className="mt-0.5">{globalError}</p>
          </div>
        </div>
      )}

      {/* Main Grid Layout */}
      <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
        
        {/* Left Form: Customer Details */}
        <div className="lg:col-span-7">
          <form onSubmit={handleSubmit} className="space-y-6">
            
            {/* Delivery Details Card */}
            <div className="p-6 rounded-xl bg-athletica-card border border-athletica-subtle space-y-4">
              <h2 className="text-xs font-black uppercase tracking-widest text-white border-b border-athletica-subtle pb-3 flex items-center justify-between">
                <span>1. Shipping & Contact Information</span>
                <span className="text-athletica-green text-[11px] font-bold">Pakistan Nationwide</span>
              </h2>

              <div className="space-y-4 text-xs">
                {/* Full Name */}
                <div>
                  <label className="block font-bold text-white uppercase tracking-wider mb-1.5">
                    Full Name <span className="text-athletica-red">*</span>
                  </label>
                  <input
                    type="text"
                    name="name"
                    value={formData.name}
                    onChange={handleInputChange}
                    required
                    placeholder="e.g. Muhammad Hamza"
                    className={`w-full px-3.5 py-2.5 rounded bg-athletica-input border ${
                      fieldErrors['customer.name'] ? 'border-athletica-red' : 'border-athletica-border'
                    } text-white focus:outline-none focus:border-white transition-colors`}
                  />
                  {fieldErrors['customer.name'] && (
                    <p className="text-athletica-red text-[11px] mt-1 font-semibold">
                      {fieldErrors['customer.name']}
                    </p>
                  )}
                </div>

                {/* Phone / WhatsApp */}
                <div>
                  <label className="block font-bold text-white uppercase tracking-wider mb-1.5">
                    Mobile / WhatsApp Number <span className="text-athletica-red">*</span>
                  </label>
                  <input
                    type="tel"
                    name="phone"
                    value={formData.phone}
                    onChange={handleInputChange}
                    required
                    placeholder="e.g. 03001234567"
                    className={`w-full px-3.5 py-2.5 rounded bg-athletica-input border ${
                      fieldErrors['customer.phone'] ? 'border-athletica-red' : 'border-athletica-border'
                    } text-white focus:outline-none focus:border-white transition-colors`}
                  />
                  <p className="text-[11px] text-athletica-muted mt-1">
                    Used for courier delivery updates and WhatsApp order confirmation.
                  </p>
                  {fieldErrors['customer.phone'] && (
                    <p className="text-athletica-red text-[11px] mt-1 font-semibold">
                      {fieldErrors['customer.phone']}
                    </p>
                  )}
                </div>

                {/* Email (Optional) */}
                <div>
                  <label className="block font-bold text-white uppercase tracking-wider mb-1.5">
                    Email Address <span className="text-athletica-muted font-normal">(Optional)</span>
                  </label>
                  <input
                    type="email"
                    name="email"
                    value={formData.email}
                    onChange={handleInputChange}
                    placeholder="e.g. hamza@example.com"
                    className={`w-full px-3.5 py-2.5 rounded bg-athletica-input border ${
                      fieldErrors['customer.email'] ? 'border-athletica-red' : 'border-athletica-border'
                    } text-white focus:outline-none focus:border-white transition-colors`}
                  />
                  {fieldErrors['customer.email'] && (
                    <p className="text-athletica-red text-[11px] mt-1 font-semibold">
                      {fieldErrors['customer.email']}
                    </p>
                  )}
                </div>

                {/* City Selection */}
                <div>
                  <label className="block font-bold text-white uppercase tracking-wider mb-1.5">
                    City <span className="text-athletica-red">*</span>
                  </label>
                  <input
                    type="text"
                    name="city"
                    value={formData.city}
                    onChange={handleInputChange}
                    required
                    placeholder="e.g. Lahore, Karachi, Islamabad, Rawalpindi"
                    className={`w-full px-3.5 py-2.5 rounded bg-athletica-input border ${
                      fieldErrors['customer.city'] ? 'border-athletica-red' : 'border-athletica-border'
                    } text-white focus:outline-none focus:border-white transition-colors`}
                  />
                  {fieldErrors['customer.city'] && (
                    <p className="text-athletica-red text-[11px] mt-1 font-semibold">
                      {fieldErrors['customer.city']}
                    </p>
                  )}
                </div>

                {/* Street Address */}
                <div>
                  <label className="block font-bold text-white uppercase tracking-wider mb-1.5">
                    Complete Delivery Address <span className="text-athletica-red">*</span>
                  </label>
                  <textarea
                    name="delivery_address"
                    value={formData.delivery_address}
                    onChange={handleInputChange}
                    required
                    rows={3}
                    placeholder="House / Apartment #, Street #, Sector / Area, Landmark"
                    className={`w-full px-3.5 py-2.5 rounded bg-athletica-input border ${
                      fieldErrors['customer.delivery_address'] ? 'border-athletica-red' : 'border-athletica-border'
                    } text-white focus:outline-none focus:border-white transition-colors resize-none`}
                  />
                  {fieldErrors['customer.delivery_address'] && (
                    <p className="text-athletica-red text-[11px] mt-1 font-semibold">
                      {fieldErrors['customer.delivery_address']}
                    </p>
                  )}
                </div>
              </div>
            </div>

            {/* Payment Method Card */}
            <div className="p-6 rounded-xl bg-athletica-card border border-athletica-subtle space-y-3">
              <h2 className="text-xs font-black uppercase tracking-widest text-white border-b border-athletica-subtle pb-3">
                2. Payment Method
              </h2>

              <div className="p-4 rounded-lg bg-athletica-surface border-2 border-white flex items-center justify-between">
                <div className="flex items-center gap-3">
                  <div className="w-4 h-4 rounded-full bg-white flex items-center justify-center">
                    <div className="w-2 h-2 rounded-full bg-black" />
                  </div>
                  <div>
                    <h3 className="text-xs font-black text-white uppercase tracking-wider">
                      Cash on Delivery (COD)
                    </h3>
                    <p className="text-[11px] text-athletica-secondary">
                      Pay the exact cash amount upon delivery at your doorstep.
                    </p>
                  </div>
                </div>
                <Truck className="w-5 h-5 text-athletica-green" />
              </div>
            </div>

            {/* Submit Button */}
            <button
              type="submit"
              disabled={isSubmitting || isLoading || !hydratedData?.can_checkout}
              className="w-full py-4 rounded-xl bg-white text-black font-black text-xs uppercase tracking-widest hover:bg-gray-200 transition-all flex items-center justify-center gap-2 disabled:opacity-40 disabled:cursor-not-allowed shadow-xl shadow-white/10"
            >
              <Lock className="w-4 h-4" />
              <span>{isSubmitting ? 'Placing Order...' : 'Place Cash on Delivery Order'}</span>
            </button>
          </form>
        </div>

        {/* Right Sidebar: Order Review */}
        <div className="lg:col-span-5 space-y-4">
          <div className="p-6 rounded-xl bg-athletica-card border border-athletica-subtle space-y-4">
            <h2 className="text-xs font-black uppercase tracking-widest text-white border-b border-athletica-subtle pb-3">
              Order Review ({hydratedData?.total_quantity || 0} items)
            </h2>

            {/* Items Mini List */}
            <div className="divide-y divide-athletica-subtle max-h-80 overflow-y-auto pr-1 space-y-3">
              {hydratedData?.items.map((item) => (
                <div key={item.cart_key} className="pt-3 first:pt-0 flex gap-3 items-center">
                  <div className="relative w-14 h-14 rounded bg-athletica-surface border border-athletica-border overflow-hidden flex-shrink-0">
                    <Image
                      src={getMediaUrl(item.primary_image)}
                      alt={item.name}
                      fill
                      className="object-cover"
                      sizes="56px"
                    />
                  </div>

                  <div className="flex-1 min-w-0 text-xs">
                    <p className="font-bold text-white truncate">{item.name}</p>
                    <p className="text-athletica-secondary text-[11px]">
                      Size: {item.size} • Qty: {item.quantity}
                    </p>
                  </div>

                  <span className="text-xs font-black text-white flex-shrink-0">
                    {formatPrice(item.line_total)}
                  </span>
                </div>
              ))}
            </div>

            {/* Cost Breakdown */}
            <div className="space-y-2 text-xs pt-4 border-t border-athletica-subtle">
              <div className="flex justify-between text-athletica-secondary">
                <span>Subtotal:</span>
                <span className="font-bold text-white">
                  {formatPrice(hydratedData?.subtotal || '0.00')}
                </span>
              </div>

              <div className="flex justify-between text-athletica-secondary">
                <span>Delivery Fee:</span>
                <span className="text-athletica-green font-extrabold uppercase">
                  {hydratedData?.delivery_fee === '0.00' ? 'FREE' : formatPrice(hydratedData?.delivery_fee)}
                </span>
              </div>

              <div className="flex justify-between text-base font-black text-white pt-3 border-t border-athletica-subtle">
                <span>Total Due at Delivery:</span>
                <span>{formatPrice(hydratedData?.total_amount || '0.00')}</span>
              </div>
            </div>

            {/* Guarantee Box */}
            <div className="p-3.5 rounded-lg bg-athletica-surface border border-athletica-border text-[11px] text-athletica-secondary space-y-1.5">
              <div className="flex items-center gap-1.5 text-white font-bold">
                <CheckCircle2 className="w-3.5 h-3.5 text-athletica-green" />
                <span>100% Genuine Imported Products</span>
              </div>
              <p>Every pair is inspected and verified before dispatch.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
