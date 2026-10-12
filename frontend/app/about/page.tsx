import React from 'react';
import Link from 'next/link';
import { Metadata } from 'next';
import { ShieldCheck, Truck, Package, MessageCircle } from 'lucide-react';

export const metadata: Metadata = {
  title: 'About Us — ATHLETICA',
  description:
    'Learn about ATHLETICA, our dedication to authentic imported athletic footwear, premium sportswear, and customer-first service across Pakistan.',
  alternates: {
    canonical: '/about',
  },
  openGraph: {
    title: 'About ATHLETICA — Authentic Imported Athletic Footwear',
    description:
      'Discover our commitment to handpicked original athletic footwear and gear with nationwide Cash on Delivery.',
    url: '/about',
    type: 'website',
  },
  twitter: {
    card: 'summary_large_image',
    title: 'About Us — ATHLETICA',
    description: 'Learn about our authentic imported athletic footwear catalog.',
  },
};

export default function AboutPage() {
  return (
    <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-16 space-y-12">
      {/* Breadcrumb */}
      <nav className="flex items-center gap-2 text-xs font-bold text-athletica-muted uppercase tracking-wider">
        <Link href="/" className="hover:text-white transition-colors">
          Home
        </Link>
        <span>/</span>
        <span className="text-white">About Us</span>
      </nav>

      {/* Header */}
      <div className="border-b border-athletica-subtle pb-6 space-y-2">
        <h1 className="text-3xl sm:text-4xl font-black uppercase tracking-tight text-white">
          About ATHLETICA
        </h1>
        <p className="text-xs font-black uppercase tracking-widest text-athletica-red">
          FOCUS | POWER | PERFORMANCE
        </p>
      </div>

      {/* Body Content */}
      <div className="p-8 rounded-2xl bg-athletica-card border border-athletica-subtle space-y-8 text-xs sm:text-sm text-athletica-secondary leading-relaxed">
        <p>
          <strong className="text-white">ATHLETICA</strong> is a premier destination for genuine, handpicked imported athletic footwear, high-performance running shoes, limited-edition sneakers, and pro sportswear in Pakistan. We source authentic pairs from trusted global distribution channels to ensure every item in our catalog meets strict standards of quality, comfort, and durability.
        </p>

        {/* Philosophy Cards */}
        <div className="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-2">
          <div className="p-5 rounded-xl bg-athletica-surface border border-athletica-border space-y-2">
            <ShieldCheck className="w-6 h-6 text-athletica-blue" />
            <h3 className="font-extrabold text-sm uppercase text-white">
              100% Authentic Imports
            </h3>
            <p className="text-xs text-athletica-secondary">
              Zero replicas. Every pair is physically inspected for genuine materials, serial verification, and build quality.
            </p>
          </div>

          <div className="p-5 rounded-xl bg-athletica-surface border border-athletica-border space-y-2">
            <Package className="w-6 h-6 text-amber-400" />
            <h3 className="font-extrabold text-sm uppercase text-white">
              Single-Pair Exclusives
            </h3>
            <p className="text-xs text-athletica-secondary">
              Many of our footwear highlights represent unique, single-pair imports in specific sizes ready for immediate dispatch.
            </p>
          </div>

          <div className="p-5 rounded-xl bg-athletica-surface border border-athletica-border space-y-2">
            <Truck className="w-6 h-6 text-athletica-green" />
            <h3 className="font-extrabold text-sm uppercase text-white">
              Free Nationwide COD
            </h3>
            <p className="text-xs text-athletica-secondary">
              Shop with complete peace of mind and pay upon arrival at your doorstep in any city of Pakistan.
            </p>
          </div>

          <div className="p-5 rounded-xl bg-athletica-surface border border-athletica-border space-y-2">
            <MessageCircle className="w-6 h-6 text-athletica-whatsapp" />
            <h3 className="font-extrabold text-sm uppercase text-white">
              WhatsApp Support
            </h3>
            <p className="text-xs text-athletica-secondary">
              Direct connection with our team for size guidance, real photos, tracking updates, and custom inquiries.
            </p>
          </div>
        </div>

        {/* CTA */}
        <div className="pt-6 border-t border-athletica-subtle flex flex-col sm:flex-row items-center justify-between gap-4">
          <div>
            <h4 className="font-bold text-white text-sm">Ready to elevate your training?</h4>
            <p className="text-xs text-athletica-muted">Explore our latest footwear and athletic collection.</p>
          </div>
          <Link
            href="/shop"
            className="px-6 py-3 rounded bg-white text-black font-extrabold text-xs uppercase tracking-wider hover:bg-gray-200 transition-colors"
          >
            Browse Catalog
          </Link>
        </div>
      </div>
    </div>
  );
}
