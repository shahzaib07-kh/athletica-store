import React from 'react';
import { Product } from '@/lib/types';
import { ProductCard } from './ProductCard';

interface ProductGridProps {
  products: Product[];
  emptyMessage?: string;
}

export function ProductGrid({ products, emptyMessage = 'No products found.' }: ProductGridProps) {
  if (products.length === 0) {
    return (
      <div className="py-16 px-4 text-center bg-athletica-card border border-athletica-subtle rounded-xl max-w-xl mx-auto my-8">
        <p className="text-white font-bold text-base">{emptyMessage}</p>
        <p className="text-xs text-athletica-secondary mt-1">
          Try adjusting your search or filters to explore other options.
        </p>
      </div>
    );
  }

  return (
    <div className="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3 sm:gap-5">
      {products.map((p) => (
        <ProductCard key={p.id} product={p} />
      ))}
    </div>
  );
}
