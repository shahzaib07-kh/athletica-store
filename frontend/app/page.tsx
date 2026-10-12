import React from 'react';
import Link from 'next/link';
import Image from 'next/image';
import {
  fetchCategories,
  fetchFeaturedProducts,
  fetchFlashSale,
  fetchHeroSlides,
  fetchProducts,
  fetchSettings,
  fetchSizes,
} from '@/lib/api';
import { ProductGrid } from '@/components/product/ProductGrid';
import { HeroSlider } from '@/components/home/HeroSlider';
import { FlashSaleSection } from '@/components/home/FlashSaleSection';
import { buildWhatsAppLink, getMediaUrl } from '@/lib/utils';
import { ArrowRight, ShieldCheck, Truck, MessageCircle, PackageCheck } from 'lucide-react';

export const revalidate = 60; // ISR revalidation every 60s

export default async function HomePage() {
  const [categories, featuredProducts, newArrivalsRes, availableSizes, settings, heroSlides, flashSale] =
    await Promise.all([
      fetchCategories().catch(() => []),
      fetchFeaturedProducts(8).catch(() => []),
      fetchProducts({ limit: 8, sort: 'newest' }).catch(() => ({ products: [], pagination: {} as any })),
      fetchSizes().catch(() => []),
      fetchSettings().catch(() => null),
      fetchHeroSlides().catch(() => []),
      fetchFlashSale().catch(() => null),
    ]);

  const newArrivals = newArrivalsRes.products || [];
  const waNumber = settings?.whatsapp_normalized || '923001234567';
  const waHeroUrl = buildWhatsAppLink(
    waNumber,
    'Hello ATHLETICA, I want to see the latest collection and available sizes.'
  );

  return (
    <div className="space-y-14 sm:space-y-20 pb-16">
      
      {/* 1. Hero Section (CMS Slider or Fallback Showcase) */}
      {heroSlides && heroSlides.length > 0 ? (
        <HeroSlider slides={heroSlides} />
      ) : (
        <section className="relative overflow-hidden bg-gradient-to-b from-athletica-card to-athletica-bg border-b border-athletica-subtle pt-10 pb-16 sm:py-24">
          <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div className="grid grid-cols-1 lg:grid-cols-12 gap-10 items-center">
              
              {/* Left Content */}
              <div className="lg:col-span-7 space-y-6 text-center lg:text-left">
                <div className="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-athletica-surface border border-athletica-border text-xs font-black uppercase tracking-wider text-athletica-secondary">
                  <span className="text-athletica-red animate-pulse">●</span>
                  <span>AUTHENTIC IMPORTED GEAR</span>
                </div>

                <h1 className="text-4xl sm:text-6xl font-black tracking-tight text-white uppercase leading-[1.05]">
                  POWER YOUR <br className="hidden sm:inline" />
                  <span className="text-transparent bg-clip-text bg-gradient-to-r from-white via-gray-200 to-gray-400">
                    PERFORMANCE
                  </span>
                </h1>

                <p className="text-sm sm:text-base text-athletica-secondary max-w-xl mx-auto lg:mx-0 leading-relaxed">
                  Handpicked genuine athletic footwear, high-end running shoes, and sportswear. Single-pair imported exclusives and multi-size essentials with Cash on Delivery across Pakistan.
                </p>

                <div className="flex flex-col sm:flex-row items-center justify-center lg:justify-start gap-3 pt-2">
                  <Link
                    href="/shop"
                    className="w-full sm:w-auto px-8 py-3.5 bg-white text-black font-extrabold text-xs uppercase tracking-wider rounded-md hover:bg-gray-200 transition-all flex items-center justify-center gap-2 group"
                  >
                    <span>Shop All Catalog</span>
                    <ArrowRight className="w-4 h-4 transition-transform group-hover:translate-x-1" />
                  </Link>

                  <a
                    href={waHeroUrl}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="w-full sm:w-auto px-7 py-3.5 bg-athletica-surface border border-athletica-border hover:bg-athletica-hover text-athletica-whatsapp font-extrabold text-xs uppercase tracking-wider rounded-md transition-colors flex items-center justify-center gap-2"
                  >
                    <MessageCircle className="w-4 h-4" />
                    <span>Order on WhatsApp</span>
                  </a>
                </div>
              </div>

              {/* Right Visual Banner */}
              <div className="lg:col-span-5 relative flex items-center justify-center">
                <div className="relative aspect-square w-full max-w-md rounded-2xl bg-gradient-to-br from-athletica-surface to-athletica-card border border-athletica-border p-6 shadow-2xl flex flex-col justify-between overflow-hidden">
                  <div className="flex items-center justify-between text-xs font-black uppercase tracking-widest text-athletica-muted">
                    <span>ATHLETICA LABS</span>
                    <span className="text-athletica-green">IN STOCK</span>
                  </div>

                  <div className="my-auto py-8 text-center space-y-2">
                    <div className="text-6xl sm:text-7xl font-black tracking-tighter text-white/90">
                      IMPORT
                    </div>
                    <p className="text-xs text-athletica-muted uppercase tracking-widest font-bold">
                      NIKE • ASICS • ADIDAS • HOKA
                    </p>
                  </div>

                  <div className="p-3 bg-athletica-bg/80 backdrop-blur rounded-lg border border-athletica-subtle flex items-center justify-between text-xs">
                    <span className="text-athletica-secondary">Nationwide Shipping</span>
                    <span className="font-extrabold text-athletica-green">FREE (Rs. 0)</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </section>
      )}

      {/* 2. Flash Sale Section (if active) */}
      {flashSale && flashSale.remaining_seconds > 0 && (
        <FlashSaleSection flashSale={flashSale} />
      )}

      {/* 3. Trust Badges Bar */}
      <section className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="grid grid-cols-2 md:grid-cols-4 gap-4 p-5 rounded-xl bg-athletica-card border border-athletica-subtle">
          <div className="flex items-center gap-3">
            <div className="p-2.5 rounded-lg bg-athletica-surface border border-athletica-border text-athletica-green">
              <PackageCheck className="w-5 h-5" />
            </div>
            <div>
              <h4 className="text-xs font-extrabold text-white uppercase tracking-wider">Cash on Delivery</h4>
              <p className="text-[11px] text-athletica-muted">Pay at your doorstep</p>
            </div>
          </div>

          <div className="flex items-center gap-3">
            <div className="p-2.5 rounded-lg bg-athletica-surface border border-athletica-border text-athletica-blue">
              <ShieldCheck className="w-5 h-5" />
            </div>
            <div>
              <h4 className="text-xs font-extrabold text-white uppercase tracking-wider">100% Authentic</h4>
              <p className="text-[11px] text-athletica-muted">Verified original pairs</p>
            </div>
          </div>

          <div className="flex items-center gap-3">
            <div className="p-2.5 rounded-lg bg-athletica-surface border border-athletica-border text-amber-400">
              <Truck className="w-5 h-5" />
            </div>
            <div>
              <h4 className="text-xs font-extrabold text-white uppercase tracking-wider">Free Shipping</h4>
              <p className="text-[11px] text-athletica-muted">Nationwide across Pakistan</p>
            </div>
          </div>

          <div className="flex items-center gap-3">
            <div className="p-2.5 rounded-lg bg-athletica-surface border border-athletica-border text-athletica-whatsapp">
              <MessageCircle className="w-5 h-5" />
            </div>
            <div>
              <h4 className="text-xs font-extrabold text-white uppercase tracking-wider">WhatsApp Support</h4>
              <p className="text-[11px] text-athletica-muted">Live size guidance</p>
            </div>
          </div>
        </div>
      </section>

      {/* 3. Category Showcase Grid */}
      {categories.length > 0 && (
        <section className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
          <div className="flex items-end justify-between border-b border-athletica-subtle pb-4">
            <div>
              <h2 className="text-xl sm:text-2xl font-black uppercase tracking-tight text-white">
                Explore Categories
              </h2>
              <p className="text-xs text-athletica-secondary mt-1">
                Find your performance footwear, apparel, and athletic essentials
              </p>
            </div>
            <Link
              href="/shop"
              className="text-xs font-extrabold uppercase tracking-wider text-athletica-secondary hover:text-white flex items-center gap-1"
            >
              <span>All Categories</span>
              <ArrowRight className="w-3.5 h-3.5" />
            </Link>
          </div>

          <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 sm:gap-4">
            {categories.map((cat) => (
              <Link
                key={cat.id}
                href={`/category/${cat.slug}`}
                className="group relative aspect-[4/5] rounded-xl bg-athletica-card border border-athletica-subtle overflow-hidden flex flex-col justify-end p-4 transition-all hover:border-white/50"
              >
                {cat.image ? (
                  <Image
                    src={getMediaUrl(cat.image)}
                    alt={cat.name}
                    fill
                    className="object-cover transition-transform duration-500 group-hover:scale-110 opacity-70 group-hover:opacity-90"
                    sizes="(max-width: 640px) 50vw, 20vw"
                  />
                ) : (
                  <div className="absolute inset-0 bg-gradient-to-t from-black via-black/40 to-transparent" />
                )}
                <div className="relative z-10 space-y-0.5">
                  <h3 className="font-extrabold text-sm text-white uppercase tracking-wide group-hover:text-gray-100">
                    {cat.name}
                  </h3>
                  <span className="text-[11px] text-athletica-secondary block font-medium">
                    {cat.product_count} Products
                  </span>
                </div>
              </Link>
            ))}
          </div>
        </section>
      )}

      {/* 4. Shop by Size Selector Bar */}
      {availableSizes.length > 0 && (
        <section className="bg-athletica-card border-y border-athletica-subtle py-8">
          <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-4">
            <div className="flex items-center justify-between">
              <div>
                <h2 className="text-sm font-extrabold uppercase tracking-widest text-white">
                  Shop by Size
                </h2>
                <p className="text-xs text-athletica-secondary">
                  Quickly locate available in-stock pairs in your size
                </p>
              </div>
              <Link
                href="/shop"
                className="text-xs font-bold uppercase tracking-wider text-athletica-secondary hover:text-white"
              >
                View All Sizes →
              </Link>
            </div>

            <div className="flex flex-wrap gap-2 pt-1">
              {availableSizes.map((sizeVal) => (
                <Link
                  key={sizeVal}
                  href={`/size/${encodeURIComponent(sizeVal)}`}
                  className="px-4 py-2 rounded-md bg-athletica-surface border border-athletica-border text-xs font-bold text-white hover:bg-white hover:text-black hover:border-white transition-all uppercase"
                >
                  {sizeVal}
                </Link>
              ))}
            </div>
          </div>
        </section>
      )}

      {/* 5. Featured Products Showcase */}
      {featuredProducts.length > 0 && (
        <section className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
          <div className="flex items-end justify-between border-b border-athletica-subtle pb-4">
            <div>
              <span className="text-xs font-black uppercase tracking-widest text-athletica-red">
                Curated Highlights
              </span>
              <h2 className="text-xl sm:text-2xl font-black uppercase tracking-tight text-white mt-0.5">
                Featured Gear
              </h2>
            </div>
            <Link
              href="/shop"
              className="text-xs font-extrabold uppercase tracking-wider text-athletica-secondary hover:text-white flex items-center gap-1"
            >
              <span>Explore Collection</span>
              <ArrowRight className="w-3.5 h-3.5" />
            </Link>
          </div>

          <ProductGrid products={featuredProducts} />
        </section>
      )}

      {/* 6. New Arrivals Showcase */}
      {newArrivals.length > 0 && (
        <section className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
          <div className="flex items-end justify-between border-b border-athletica-subtle pb-4">
            <div>
              <span className="text-xs font-black uppercase tracking-widest text-athletica-green">
                Fresh Inventory
              </span>
              <h2 className="text-xl sm:text-2xl font-black uppercase tracking-tight text-white mt-0.5">
                New Arrivals
              </h2>
            </div>
            <Link
              href="/shop?sort=newest"
              className="text-xs font-extrabold uppercase tracking-wider text-athletica-secondary hover:text-white flex items-center gap-1"
            >
              <span>View All New</span>
              <ArrowRight className="w-3.5 h-3.5" />
            </Link>
          </div>

          <ProductGrid products={newArrivals} />
        </section>
      )}

      {/* 7. WhatsApp Direct Inquiry Banner */}
      <section className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div className="rounded-2xl bg-gradient-to-r from-emerald-950/60 to-athletica-card border border-emerald-900/50 p-8 sm:p-12 flex flex-col md:flex-row items-center justify-between gap-6">
          <div className="space-y-2 text-center md:text-left">
            <h3 className="text-xl sm:text-2xl font-black uppercase tracking-tight text-white">
              Looking for a Specific Size or Model?
            </h3>
            <p className="text-xs sm:text-sm text-emerald-200/80 max-w-xl">
              Connect directly with our team on WhatsApp for size advice, live product photographs, or custom sourcing queries.
            </p>
          </div>
          <a
            href={waHeroUrl}
            target="_blank"
            rel="noopener noreferrer"
            className="px-8 py-4 bg-athletica-whatsapp text-black font-black text-xs uppercase tracking-wider rounded-lg hover:bg-emerald-400 transition-all flex items-center gap-2 flex-shrink-0 shadow-lg shadow-athletica-whatsapp/20"
          >
            <MessageCircle className="w-5 h-5 fill-current" />
            <span>Chat on WhatsApp</span>
          </a>
        </div>
      </section>
    </div>
  );
}
