'use client';

import React from 'react';
import Image from 'next/image';
import Link from 'next/link';
import { FlashSale } from '@/lib/types';
import { FlashSaleCountdown } from './FlashSaleCountdown';
import { getMediaUrl, formatPrice } from '@/lib/utils';
import { Zap, ArrowRight } from 'lucide-react';

interface FlashSaleSectionProps {
  flashSale: FlashSale;
}

export function FlashSaleSection({ flashSale }: FlashSaleSectionProps) {
  if (!flashSale || !flashSale.products || flashSale.products.length === 0) {
    return null;
  }

  return (
    <section className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div className="rounded-2xl bg-gradient-to-b from-red-950/40 via-athletica-card to-athletica-card border border-red-900/40 p-6 sm:p-8 space-y-6">
        {/* Header with Title & Countdown */}
        <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-b border-red-900/30 pb-5">
          <div className="space-y-1">
            <div className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded bg-red-600/20 text-red-400 border border-red-500/30 text-xs font-black uppercase tracking-wider">
              <Zap className="w-3.5 h-3.5 fill-current" />
              <span>FLASH PROMOTION</span>
            </div>
            <h2 className="text-xl sm:text-2xl font-black uppercase tracking-tight text-white">
              {flashSale.title}
            </h2>
          </div>

          <div className="flex items-center gap-3">
            <span className="text-xs uppercase font-extrabold tracking-wider text-athletica-secondary hidden sm:inline">
              Ends In:
            </span>
            <FlashSaleCountdown initialSeconds={flashSale.remaining_seconds} />
          </div>
        </div>

        {/* Products Grid */}
        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
          {flashSale.products.map((p) => {
            const hasDiscount = p.discount_percent > 0;
            return (
              <div
                key={p.id}
                className="group relative flex flex-col justify-between rounded-xl bg-athletica-surface border border-athletica-border overflow-hidden hover:border-red-500/50 transition-colors"
              >
                {/* Image Container */}
                <Link href={`/product/${p.slug}`} className="relative aspect-square w-full block bg-black/40 overflow-hidden">
                  {p.primary_image ? (
                    <Image
                      src={getMediaUrl(p.primary_image)}
                      alt={p.name}
                      fill
                      className="object-cover transition-transform duration-500 group-hover:scale-105"
                      sizes="(max-width: 640px) 50vw, (max-width: 1024px) 33vw, 25vw"
                    />
                  ) : (
                    <div className="w-full h-full flex items-center justify-center text-xs text-athletica-muted">
                      No Image
                    </div>
                  )}

                  {/* Badge */}
                  {hasDiscount && (
                    <span className="absolute top-2 left-2 px-2 py-0.5 rounded bg-red-600 text-white font-black text-[10px] tracking-wider uppercase">
                      -{p.discount_percent}% OFF
                    </span>
                  )}
                </Link>

                {/* Details */}
                <div className="p-4 space-y-2 flex-grow flex flex-col justify-between">
                  <div>
                    <Link href={`/product/${p.slug}`} className="block">
                      <h3 className="text-xs sm:text-sm font-extrabold uppercase tracking-tight text-white hover:text-red-400 transition-colors line-clamp-1">
                        {p.name}
                      </h3>
                    </Link>

                    {p.available_sizes.length > 0 && (
                      <p className="text-[11px] text-athletica-muted font-medium mt-0.5">
                        Sizes: {p.available_sizes.slice(0, 3).join(', ')}{p.available_sizes.length > 3 ? '…' : ''}
                      </p>
                    )}
                  </div>

                  {/* Price */}
                  <div className="pt-1 flex items-baseline gap-2">
                    <span className="text-sm sm:text-base font-black text-red-400">
                      {formatPrice(p.sale_price)}
                    </span>
                    <span className="text-xs text-athletica-muted line-through">
                      {formatPrice(p.regular_price)}
                    </span>
                  </div>
                </div>
              </div>
            );
          })}
        </div>
      </div>
    </section>
  );
}
