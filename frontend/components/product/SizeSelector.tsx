'use client';

import React from 'react';
import { ProductSize } from '@/lib/types';

interface SizeSelectorProps {
  sizes: ProductSize[];
  selectedSize: string;
  onSelectSize: (size: string) => void;
  isUnique?: boolean;
}

export function SizeSelector({
  sizes,
  selectedSize,
  onSelectSize,
  isUnique = false,
}: SizeSelectorProps) {
  if (sizes.length === 0) {
    return (
      <div className="text-xs text-athletica-muted">
        No specific size options required for this item.
      </div>
    );
  }

  return (
    <div className="space-y-2.5">
      <div className="flex items-center justify-between text-xs">
        <span className="font-extrabold uppercase tracking-wider text-white">
          Select Size: <span className="text-athletica-secondary">{selectedSize || 'None'}</span>
        </span>
        {isUnique && (
          <span className="text-amber-500 font-black text-[11px] uppercase tracking-wider">
            ★ Unique One-Pair Stock
          </span>
        )}
      </div>

      <div className="grid grid-cols-4 sm:grid-cols-6 gap-2">
        {sizes.map((s) => {
          const isAvail = s.is_available === 1;
          const isSelected = selectedSize.toLowerCase() === s.size.toLowerCase();

          return (
            <button
              key={s.id || s.size}
              type="button"
              disabled={!isAvail}
              onClick={() => onSelectSize(s.size)}
              className={`py-2.5 px-3 rounded text-xs font-bold uppercase transition-all flex items-center justify-center text-center relative ${
                !isAvail
                  ? 'bg-athletica-surface/40 border border-athletica-subtle text-athletica-muted/40 cursor-not-allowed line-through'
                  : isSelected
                  ? 'bg-white text-black font-black border-2 border-white shadow-md'
                  : 'bg-athletica-surface border border-athletica-border text-white hover:border-white/60 hover:bg-athletica-hover'
              }`}
            >
              {s.size}
            </button>
          );
        })}
      </div>
    </div>
  );
}
