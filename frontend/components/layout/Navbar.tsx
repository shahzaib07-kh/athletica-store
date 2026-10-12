'use client';

import React, { useState, useEffect } from 'react';
import Link from 'next/link';
import Image from 'next/image';
import { usePathname, useRouter } from 'next/navigation';
import { ShoppingBag, Search, Menu, X, Plus, Minus, User } from 'lucide-react';
import { useCartStore } from '@/lib/cart-store';
import { Category, SiteSettings } from '@/lib/types';

interface NavbarProps {
  categories?: Category[];
  settings?: SiteSettings | null;
}

export function Navbar({ categories = [], settings }: NavbarProps) {
  const [searchQuery, setSearchQuery] = useState('');
  const [isDrawerOpen, setIsDrawerOpen] = useState(false);
  const [isSizeOpen, setIsSizeOpen] = useState(false);
  const [isBrandOpen, setIsBrandOpen] = useState(false);
  const [mounted, setMounted] = useState(false);

  const pathname = usePathname();
  const router = useRouter();
  const { toggleDrawer, getTotalCount } = useCartStore();

  useEffect(() => {
    setMounted(true);
  }, []);

  // Close drawer on route change
  useEffect(() => {
    setIsDrawerOpen(false);
  }, [pathname]);

  const handleSearchSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    if (searchQuery.trim()) {
      router.push(`/shop?q=${encodeURIComponent(searchQuery.trim())}`);
    }
  };

  const totalCount = mounted ? getTotalCount() : 0;

  // Standard sizes & brands for quick drawer navigation
  const shoeSizes = ['EU 39', 'EU 40', 'EU 41', 'EU 42', 'EU 43', 'EU 44', 'EU 45', 'EU 46'];
  const popularBrands = [
    { name: 'Nike', slug: 'nike' },
    { name: 'Adidas', slug: 'adidas' },
    { name: 'ASICS', slug: 'asics' },
    { name: 'Hoka', slug: 'hoka' },
    { name: 'On Running / QC', slug: 'qc' },
  ];

  return (
    <>
      <header className="sticky top-0 z-40 bg-[#f3f4f6] border-b border-gray-300/80 shadow-xs">
        <div className="w-full max-w-[1360px] mx-auto px-3 sm:px-6 lg:px-8 py-2.5 flex items-center justify-between gap-3 sm:gap-6">
          
          {/* 1. LEFT SIDE: Hamburger menu icon + "ATHLETICA" text + Running Man logo */}
          <div className="flex items-center gap-2.5 sm:gap-3 flex-shrink-0">
            {/* Hamburger Icon */}
            <button
              type="button"
              onClick={() => setIsDrawerOpen(true)}
              className="p-1.5 text-gray-900 hover:text-black hover:bg-gray-200/70 rounded-md transition-colors"
              aria-label="Open Navigation Menu"
              title="Menu"
            >
              <Menu className="w-6 h-6 sm:w-7 sm:h-7" strokeWidth={2.3} />
            </button>

            {/* Brand Logo: ATHLETICA text first, followed immediately by running man symbol */}
            <Link href="/" className="flex items-center gap-2 group select-none">
              <span className="font-black text-xl sm:text-2xl tracking-[0.14em] text-black uppercase transition-opacity group-hover:opacity-90 leading-none">
                ATHLETICA
              </span>
              <div className="relative h-7 w-12 sm:h-8 sm:w-14 flex-shrink-0">
                <Image
                  src="/images/running-man.png"
                  alt="ATHLETICA Running Man"
                  fill
                  sizes="56px"
                  className="object-contain"
                  priority
                />
              </div>
            </Link>
          </div>

          {/* 2. CENTER: Balanced, compact pill-shaped Search Bar */}
          <div className="hidden md:flex flex-1 max-w-xl mx-auto px-2 justify-center">
            <form onSubmit={handleSearchSubmit} className="w-full max-w-lg relative flex items-center">
              <div className="w-full flex items-center bg-white hover:border-gray-400 focus-within:ring-2 focus-within:ring-black border border-gray-300 rounded-full pl-4 pr-1.5 py-1 shadow-2xs transition-all">
                <input
                  type="text"
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                  placeholder="Search for products"
                  className="w-full bg-transparent text-xs sm:text-sm text-gray-900 placeholder-gray-500 focus:outline-none py-0.5"
                />
                <button
                  type="submit"
                  className="bg-black text-white text-[11px] font-bold uppercase tracking-wider px-5 py-1.5 rounded-full hover:bg-gray-800 transition-colors flex-shrink-0 shadow-xs"
                >
                  search
                </button>
              </div>
            </form>
          </div>

          {/* 3. RIGHT SIDE: Shopping Cart ONLY (Clean and Prominent with Yellow Badge) */}
          <div className="flex items-center flex-shrink-0">
            <button
              type="button"
              onClick={toggleDrawer}
              className="p-2 text-gray-900 hover:text-black hover:bg-gray-200/70 rounded-full transition-colors relative"
              aria-label={`Shopping Cart with ${totalCount} items`}
              title="Shopping Bag"
            >
              <ShoppingBag className="w-6 h-6 sm:w-7 sm:h-7" strokeWidth={2.2} />
              <span className="absolute top-0 right-0 min-w-5 h-5 px-1 rounded-full bg-[#facc15] text-black text-[11px] font-black flex items-center justify-center border-2 border-[#f3f4f6] shadow-xs leading-none">
                {totalCount}
              </span>
            </button>
          </div>
        </div>

        {/* Mobile Search Bar (Responsive for ~390px, fits screen width without overflow) */}
        <div className="md:hidden w-full px-3 pb-2.5 pt-0.5 bg-[#f3f4f6] border-t border-gray-200/80">
          <form onSubmit={handleSearchSubmit} className="w-full">
            <div className="w-full flex items-center bg-white border border-gray-300 rounded-full pl-3.5 pr-1 py-0.5 shadow-2xs">
              <input
                type="text"
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                placeholder="Search for products"
                className="w-full min-w-0 bg-transparent text-xs text-gray-900 placeholder-gray-500 focus:outline-none py-1"
              />
              <button
                type="submit"
                className="bg-black text-white text-[10px] font-bold uppercase tracking-wider px-3.5 py-1 rounded-full hover:bg-gray-800 transition-colors flex-shrink-0"
              >
                search
              </button>
            </div>
          </form>
        </div>
      </header>

      {/* Off-Canvas Slide-Out Navigation Drawer (White Theme) */}
      {isDrawerOpen && (
        <div className="fixed inset-0 z-50 flex">
          {/* Backdrop */}
          <div
            className="fixed inset-0 bg-black/60 backdrop-blur-xs transition-opacity duration-200"
            onClick={() => setIsDrawerOpen(false)}
          />

          {/* Drawer Panel */}
          <nav className="relative w-4/5 max-w-sm h-full bg-white border-r border-gray-200 flex flex-col z-10 shadow-2xl animate-in slide-in-from-left duration-300">
            {/* Drawer Header */}
            <div className="p-4 border-b border-gray-100 flex items-center justify-between">
              <div className="flex items-center gap-2">
                <span className="font-black text-lg tracking-[0.14em] text-black uppercase leading-none">
                  ATHLETICA
                </span>
                <div className="relative h-6 w-8 flex-shrink-0">
                  <Image
                    src="/images/running-man.png"
                    alt="ATHLETICA Running Man"
                    fill
                    sizes="32px"
                    className="object-contain"
                  />
                </div>
              </div>
              <button
                type="button"
                onClick={() => setIsDrawerOpen(false)}
                className="p-2 text-gray-500 hover:text-black rounded hover:bg-gray-100"
                aria-label="Close Navigation Menu"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            {/* Navigation Links */}
            <div className="flex-1 overflow-y-auto py-3 px-3 space-y-0.5 text-sm font-bold uppercase tracking-wider text-gray-800">
              {/* Home */}
              <Link
                href="/"
                className={`block px-3 py-2.5 rounded hover:bg-gray-100 hover:text-black transition-colors ${
                  pathname === '/' ? 'bg-gray-100 text-black' : ''
                }`}
              >
                HOME
              </Link>

              {/* Required Categories */}
              <Link
                href="/shop?category=shoes"
                className={`block px-3 py-2.5 rounded hover:bg-gray-100 hover:text-black transition-colors ${
                  pathname === '/shop' && !searchQuery ? 'text-black' : ''
                }`}
              >
                SHOES
              </Link>

              <Link
                href="/shop?category=sliders"
                className="block px-3 py-2.5 rounded hover:bg-gray-100 hover:text-black transition-colors"
              >
                SLIDERS
              </Link>

              <Link
                href="/shop?category=caps"
                className="block px-3 py-2.5 rounded hover:bg-gray-100 hover:text-black transition-colors"
              >
                CAPS
              </Link>

              <Link
                href="/shop?category=shirts"
                className="block px-3 py-2.5 rounded hover:bg-gray-100 hover:text-black transition-colors"
              >
                SHIRTS
              </Link>

              <Link
                href="/shop?category=trousers"
                className="block px-3 py-2.5 rounded hover:bg-gray-100 hover:text-black transition-colors"
              >
                TROUSERS
              </Link>

              {/* Flash Sale in Red */}
              <Link
                href="/shop"
                className="block px-3 py-2.5 rounded text-red-600 font-extrabold hover:bg-red-50 transition-colors"
              >
                FLASH SALE
              </Link>

              {/* Accordion: SHOP BY SIZE */}
              <div className="border-t border-gray-100 pt-1 mt-1">
                <button
                  type="button"
                  onClick={() => setIsSizeOpen(!isSizeOpen)}
                  className="w-full flex items-center justify-between px-3 py-2.5 rounded hover:bg-gray-100 text-gray-800 font-bold text-left uppercase tracking-wider text-sm transition-colors"
                >
                  <span>SHOP BY SIZE</span>
                  {isSizeOpen ? <Minus className="w-4 h-4 text-gray-500" /> : <Plus className="w-4 h-4 text-gray-500" />}
                </button>
                {isSizeOpen && (
                  <div className="grid grid-cols-4 gap-1.5 px-3 py-2 bg-gray-50 rounded-lg my-1">
                    {shoeSizes.map((sz) => (
                      <Link
                        key={sz}
                        href={`/shop?size=${encodeURIComponent(sz)}`}
                        className="text-center py-1.5 px-2 bg-white border border-gray-200 rounded text-xs font-semibold text-gray-700 hover:border-black hover:text-black transition-colors"
                      >
                        {sz}
                      </Link>
                    ))}
                  </div>
                )}
              </div>

              {/* Accordion: SHOP BY BRAND */}
              <div className="border-t border-gray-100 pt-1 mt-1">
                <button
                  type="button"
                  onClick={() => setIsBrandOpen(!isBrandOpen)}
                  className="w-full flex items-center justify-between px-3 py-2.5 rounded hover:bg-gray-100 text-gray-800 font-bold text-left uppercase tracking-wider text-sm transition-colors"
                >
                  <span>SHOP BY BRAND</span>
                  {isBrandOpen ? <Minus className="w-4 h-4 text-gray-500" /> : <Plus className="w-4 h-4 text-gray-500" />}
                </button>
                {isBrandOpen && (
                  <div className="flex flex-col gap-1 px-3 py-2 bg-gray-50 rounded-lg my-1 text-xs">
                    {popularBrands.map((b) => (
                      <Link
                        key={b.slug}
                        href={`/shop?q=${encodeURIComponent(b.name)}`}
                        className="py-1.5 px-2 text-gray-700 font-semibold hover:text-black transition-colors"
                      >
                        {b.name}
                      </Link>
                    ))}
                  </div>
                )}
              </div>

              {/* Informational Links */}
              <div className="pt-4 mt-4 border-t border-gray-200 space-y-1">
                <Link
                  href="/about"
                  className="block px-3 py-2 text-xs font-semibold text-gray-500 hover:text-black uppercase tracking-wider"
                >
                  ABOUT ATHLETICA
                </Link>
                <Link
                  href="/contact"
                  className="block px-3 py-2 text-xs font-semibold text-gray-500 hover:text-black uppercase tracking-wider"
                >
                  CONTACT & SUPPORT
                </Link>
              </div>
            </div>

            {/* Drawer Footer */}
            <div className="p-4 border-t border-gray-100 bg-gray-50">
              <div className="text-center text-xs font-bold text-gray-600 uppercase tracking-widest">
                🚚 COD Across Pakistan
              </div>
            </div>
          </nav>
        </div>
      )}
    </>
  );
}

