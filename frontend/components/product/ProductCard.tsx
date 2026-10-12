import React from 'react';
import Link from 'next/link';
import Image from 'next/image';
import { Product } from '@/lib/types';
import { formatPrice, getMediaUrl } from '@/lib/utils';

interface ProductCardProps {
  product: Product;
}

export function ProductCard({ product }: ProductCardProps) {
  const isUnique = product.product_type === 'unique';
  const isOutOfStock = product.status === 'out_of_stock';
  const hasDiscount = product.discount_percent > 0;

  return (
    <Link
      href={`/product/${product.slug}`}
      className="group block bg-athletica-card border border-athletica-subtle rounded-lg overflow-hidden transition-all duration-300 hover:border-athletica-border hover:-translate-y-1 hover:shadow-xl hover:shadow-black/60 flex flex-col"
    >
      {/* Product Image Wrap */}
      <div className="relative aspect-square w-full bg-athletica-surface overflow-hidden flex items-center justify-center p-2">
        <Image
          src={getMediaUrl(product.primary_image)}
          alt={product.name}
          fill
          className="object-contain p-2 transition-transform duration-500 group-hover:scale-105"
          sizes="(max-width: 640px) 100vw, (max-width: 1024px) 50vw, 25vw"
        />

        {/* Badges */}
        <div className="absolute top-2.5 left-2.5 flex flex-col gap-1.5 z-10">
          {isUnique && (
            <span className="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider bg-amber-600/90 text-white backdrop-blur-sm">
              1-Pair Only
            </span>
          )}
          {product.condition !== 'new' && (
            <span className="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase tracking-wider bg-black/75 text-white border border-athletica-border backdrop-blur-sm">
              {product.condition}
            </span>
          )}
        </div>

        <div className="absolute top-2.5 right-2.5 flex flex-col gap-1.5 z-10">
          {isOutOfStock ? (
            <span className="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider bg-red-900/90 text-red-200">
              Out of Stock
            </span>
          ) : hasDiscount ? (
            <span className="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider bg-athletica-red text-white shadow">
              -{product.discount_percent}%
            </span>
          ) : null}
        </div>
      </div>

      {/* Product Info Body */}
      <div className="p-3.5 sm:p-4 flex-1 flex flex-col justify-between space-y-2">
        <div>
          {/* Brand & Category Row */}
          <div className="flex items-center justify-between text-[11px] font-extrabold uppercase tracking-wider text-athletica-muted mb-1">
            <span>{product.brand?.name || product.category.name}</span>
            {product.color && (
              <span className="text-athletica-muted font-normal truncate max-w-[120px]">
                {product.color}
              </span>
            )}
          </div>

          {/* Title */}
          <h3 className="font-bold text-sm text-white line-clamp-2 leading-snug group-hover:text-gray-200 transition-colors">
            {product.name}
          </h3>
        </div>

        <div className="space-y-2 pt-1 border-t border-athletica-subtle/50">
          {/* Size Preview Pills */}
          {product.available_sizes && product.available_sizes.length > 0 && (
            <div className="flex flex-wrap gap-1">
              {product.available_sizes.slice(0, 4).map((s) => (
                <span
                  key={s}
                  className="px-1.5 py-0.5 text-[10px] font-semibold bg-athletica-surface border border-athletica-border rounded text-athletica-secondary"
                >
                  {s}
                </span>
              ))}
              {product.available_sizes.length > 4 && (
                <span className="px-1.5 py-0.5 text-[10px] font-semibold text-athletica-muted">
                  +{product.available_sizes.length - 4}
                </span>
              )}
            </div>
          )}

          {/* Price Row */}
          <div className="flex items-baseline gap-2 pt-0.5">
            <span className="font-black text-sm sm:text-base text-white">
              {formatPrice(product.price)}
            </span>
            {product.old_price && (
              <span className="text-xs text-athletica-muted line-through font-medium">
                {formatPrice(product.old_price)}
              </span>
            )}
          </div>
        </div>
      </div>
    </Link>
  );
}
