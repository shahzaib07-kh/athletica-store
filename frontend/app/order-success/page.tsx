'use client';

import React, { useEffect, useState, use } from 'react';
import Link from 'next/link';
import { trackOrder } from '@/lib/api';
import { CheckoutResponse, OrderTrackResponse } from '@/lib/types';
import { formatPrice, buildWhatsAppLink } from '@/lib/utils';
import { CheckCircle2, MessageCircle, Package, ArrowRight, ShieldCheck } from 'lucide-react';

interface OrderSuccessPageProps {
  searchParams: Promise<{
    order_number?: string;
  }>;
}

export default function OrderSuccessPage({ searchParams }: OrderSuccessPageProps) {
  const resolvedParams = use(searchParams);
  const orderNumber = resolvedParams.order_number || '';

  const [orderData, setOrderData] = useState<CheckoutResponse | OrderTrackResponse | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    if (!orderNumber) {
      setIsLoading(false);
      return;
    }

    // 1. Try reading from sessionStorage (immediate post-checkout)
    if (typeof window !== 'undefined') {
      const stored = sessionStorage.getItem(`order_${orderNumber}`);
      if (stored) {
        try {
          const parsed = JSON.parse(stored);
          setOrderData(parsed);
          setIsLoading(false);
          return;
        } catch {
          // Fall through to API track query
        }
      }
    }

    // 2. Query API via track endpoint
    trackOrder(orderNumber)
      .then((data) => {
        setOrderData(data);
      })
      .catch((err) => {
        console.error('Failed to load order confirmation:', err);
      })
      .finally(() => {
        setIsLoading(false);
      });
  }, [orderNumber]);

  if (!orderNumber) {
    return (
      <div className="max-w-xl mx-auto px-4 py-20 text-center space-y-4">
        <h1 className="text-2xl font-black uppercase text-white">Order Not Specified</h1>
        <p className="text-xs text-athletica-secondary">
          No order reference was provided in the request.
        </p>
        <Link
          href="/"
          className="inline-block px-6 py-3 rounded bg-white text-black font-extrabold text-xs uppercase tracking-wider"
        >
          Return to Home
        </Link>
      </div>
    );
  }

  const waUrl = (orderData as CheckoutResponse)?.whatsapp?.direct_url ||
    buildWhatsAppLink('923001234567', `Hello ATHLETICA, I have placed Order #${orderNumber}.`);

  return (
    <div className="max-w-3xl mx-auto px-4 sm:px-6 py-10 sm:py-16 space-y-8">
      
      {/* Top Success Banner */}
      <div className="p-8 rounded-2xl bg-gradient-to-b from-athletica-card to-athletica-surface border border-athletica-border text-center space-y-4 shadow-2xl">
        <div className="w-16 h-16 rounded-full bg-emerald-950 border-2 border-athletica-green flex items-center justify-center mx-auto text-athletica-green animate-in zoom-in duration-300">
          <CheckCircle2 className="w-9 h-9" />
        </div>

        <div className="space-y-1">
          <span className="text-xs font-black uppercase tracking-widest text-athletica-green">
            Order Confirmed & Placed
          </span>
          <h1 className="text-2xl sm:text-3xl font-black uppercase text-white tracking-tight">
            Thank You For Your Order!
          </h1>
          <p className="text-xs text-athletica-secondary max-w-md mx-auto">
            Your Cash on Delivery order has been successfully registered and queued for verification.
          </p>
        </div>

        {/* Order Number Highlight */}
        <div className="inline-block px-4 py-2 rounded-lg bg-athletica-bg border border-athletica-border text-xs font-mono font-bold text-white tracking-widest">
          ORDER #: {orderNumber}
        </div>
      </div>

      {/* Prominent WhatsApp CTA Card */}
      <div className="p-6 rounded-xl bg-gradient-to-r from-emerald-950/70 via-athletica-card to-athletica-card border border-emerald-900/60 space-y-3">
        <div className="flex items-center gap-3">
          <div className="p-2.5 rounded-lg bg-athletica-whatsapp/20 text-athletica-whatsapp">
            <MessageCircle className="w-6 h-6 fill-current" />
          </div>
          <div>
            <h2 className="text-sm font-extrabold uppercase text-white tracking-wide">
              Expedite Your Order on WhatsApp
            </h2>
            <p className="text-xs text-emerald-200/80">
              Click below to send your order confirmation directly to our support desk for priority dispatch.
            </p>
          </div>
        </div>

        <a
          href={waUrl}
          target="_blank"
          rel="noopener noreferrer"
          className="w-full py-4 rounded-lg bg-athletica-whatsapp text-black font-black text-xs uppercase tracking-wider flex items-center justify-center gap-2 hover:bg-emerald-400 transition-all shadow-lg shadow-athletica-whatsapp/20"
        >
          <MessageCircle className="w-4 h-4 fill-current" />
          <span>Send Order Details to WhatsApp Support</span>
        </a>
      </div>

      {/* Order Details Snapshot Card */}
      {orderData && (
        <div className="p-6 rounded-xl bg-athletica-card border border-athletica-subtle space-y-6 text-xs">
          
          {/* Customer Details */}
          <div className="space-y-3">
            <h3 className="text-xs font-black uppercase tracking-widest text-white border-b border-athletica-subtle pb-2 flex items-center gap-2">
              <Package className="w-4 h-4 text-athletica-secondary" />
              <span>Customer & Delivery Details</span>
            </h3>

            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 text-athletica-secondary">
              <div>
                <span className="text-athletica-muted block text-[11px]">Recipient Name:</span>
                <span className="text-white font-bold">{orderData.customer.name}</span>
              </div>
              <div>
                <span className="text-athletica-muted block text-[11px]">WhatsApp / Phone:</span>
                <span className="text-white font-bold">{orderData.customer.phone || 'Provided on order'}</span>
              </div>
              <div>
                <span className="text-athletica-muted block text-[11px]">Delivery City:</span>
                <span className="text-white font-bold">{orderData.customer.city}</span>
              </div>
              <div>
                <span className="text-athletica-muted block text-[11px]">Shipping Address:</span>
                <span className="text-white font-bold">{orderData.customer.delivery_address}</span>
              </div>
            </div>
          </div>

          {/* Ordered Items Table */}
          <div className="space-y-3">
            <h3 className="text-xs font-black uppercase tracking-widest text-white border-b border-athletica-subtle pb-2">
              Items Summary
            </h3>

            <div className="divide-y divide-athletica-subtle">
              {orderData.items.map((item, idx) => (
                <div key={idx} className="py-2.5 flex items-center justify-between gap-4">
                  <div className="min-w-0">
                    <p className="font-bold text-white truncate">{item.name}</p>
                    <p className="text-athletica-muted text-[11px]">
                      Size: {item.size} • Qty: {item.quantity}
                    </p>
                  </div>
                  <span className="font-black text-white flex-shrink-0">
                    {formatPrice(item.line_total)}
                  </span>
                </div>
              ))}
            </div>
          </div>

          {/* Totals */}
          <div className="space-y-2 pt-4 border-t border-athletica-subtle">
            <div className="flex justify-between text-athletica-secondary">
              <span>Subtotal:</span>
              <span className="font-bold text-white">{formatPrice(orderData.subtotal)}</span>
            </div>
            <div className="flex justify-between text-athletica-secondary">
              <span>Shipping Fee:</span>
              <span className="text-athletica-green font-bold uppercase">
                {orderData.delivery_fee === '0.00' ? 'FREE' : formatPrice(orderData.delivery_fee)}
              </span>
            </div>
            <div className="flex justify-between text-sm font-black text-white pt-2 border-t border-athletica-subtle">
              <span>Total Due at Delivery (COD):</span>
              <span>{formatPrice(orderData.total_amount)}</span>
            </div>
          </div>
        </div>
      )}

      {/* Footer Navigation Action */}
      <div className="text-center pt-4">
        <Link
          href="/shop"
          className="inline-flex items-center gap-2 px-8 py-3.5 rounded bg-white text-black font-extrabold text-xs uppercase tracking-wider hover:bg-gray-200 transition-colors"
        >
          <span>Continue Shopping</span>
          <ArrowRight className="w-4 h-4" />
        </Link>
      </div>
    </div>
  );
}
