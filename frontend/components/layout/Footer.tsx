import React from 'react';
import Link from 'next/link';
import { Category, SiteSettings } from '@/lib/types';
import { buildWhatsAppLink } from '@/lib/utils';

interface FooterProps {
  categories?: Category[];
  settings?: SiteSettings | null;
}

export function Footer({ categories = [], settings }: FooterProps) {
  const currentYear = new Date().getFullYear();
  const waNumber = settings?.whatsapp_normalized || '923001234567';
  const waUrl = buildWhatsAppLink(waNumber, 'Hello ATHLETICA, I have an inquiry.');

  return (
    <footer className="bg-athletica-card border-t border-athletica-subtle pt-14 pb-8 mt-20">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-10 pb-12 border-b border-athletica-subtle">
          
          {/* Col 1: Brand Info */}
          <div className="space-y-4">
            <Link href="/" className="flex items-center gap-2">
              <span className="w-6 h-6 rounded bg-white text-black font-black flex items-center justify-center text-xs">
                A
              </span>
              <span className="font-black text-lg tracking-[0.16em] text-white">
                ATHLETICA
              </span>
            </Link>
            <p className="text-xs text-athletica-secondary leading-relaxed max-w-xs">
              {settings?.tagline || 'FOCUS | POWER | PERFORMANCE'}
              <br />
              Genuine handpicked imported athletic footwear and sportswear. Cash on Delivery across Pakistan.
            </p>
            <div className="pt-2">
              <a
                href={waUrl}
                target="_blank"
                rel="noopener noreferrer"
                className="inline-flex items-center gap-2 text-xs font-bold text-athletica-whatsapp hover:underline uppercase tracking-wider"
              >
                💬 WhatsApp Support
              </a>
            </div>
          </div>

          {/* Col 2: Quick Links */}
          <div className="space-y-3">
            <h4 className="text-xs font-extrabold uppercase tracking-widest text-athletica-muted">
              Navigation
            </h4>
            <ul className="space-y-2 text-xs font-semibold text-athletica-secondary">
              <li>
                <Link href="/shop" className="hover:text-white transition-colors">
                  Shop All Products
                </Link>
              </li>
              <li>
                <Link href="/about" className="hover:text-white transition-colors">
                  About ATHLETICA
                </Link>
              </li>
              <li>
                <Link href="/contact" className="hover:text-white transition-colors">
                  Contact & Location
                </Link>
              </li>
              <li>
                <Link href="/cart" className="hover:text-white transition-colors">
                  Shopping Cart
                </Link>
              </li>
            </ul>
          </div>

          {/* Col 3: Categories */}
          <div className="space-y-3">
            <h4 className="text-xs font-extrabold uppercase tracking-widest text-athletica-muted">
              Categories
            </h4>
            <ul className="space-y-2 text-xs font-semibold text-athletica-secondary">
              {categories.slice(0, 5).map((cat) => (
                <li key={cat.id}>
                  <Link href={`/category/${cat.slug}`} className="hover:text-white transition-colors">
                    {cat.name}
                  </Link>
                </li>
              ))}
            </ul>
          </div>

          {/* Col 4: Trust Guarantees */}
          <div className="space-y-3">
            <h4 className="text-xs font-extrabold uppercase tracking-widest text-athletica-muted">
              Customer Promise
            </h4>
            <ul className="space-y-2.5 text-xs text-athletica-secondary">
              <li className="flex items-center gap-2">
                <span className="text-athletica-green font-bold">✓</span>
                <span>100% Authentic Original Imports</span>
              </li>
              <li className="flex items-center gap-2">
                <span className="text-athletica-green font-bold">✓</span>
                <span>Free Nationwide Delivery</span>
              </li>
              <li className="flex items-center gap-2">
                <span className="text-athletica-green font-bold">✓</span>
                <span>Cash on Delivery (COD)</span>
              </li>
              <li className="flex items-center gap-2">
                <span className="text-athletica-green font-bold">✓</span>
                <span>Direct WhatsApp Inquiries</span>
              </li>
            </ul>
          </div>
        </div>

        {/* Footer Bottom */}
        <div className="pt-6 flex flex-col sm:flex-row items-center justify-between gap-4 text-xs text-athletica-muted text-center sm:text-left">
          <p>© {currentYear} ATHLETICA. All rights reserved.</p>
          <p className="tracking-wide">PREMIUM ATHLETIC GEAR | LAHORE, PAKISTAN</p>
        </div>
      </div>
    </footer>
  );
}
