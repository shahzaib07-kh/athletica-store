'use client';

import React, { useState } from 'react';
import Image from 'next/image';
import { ProductImage } from '@/lib/types';
import { getMediaUrl } from '@/lib/utils';

interface ProductGalleryProps {
  images: ProductImage[];
  productName: string;
}

export function ProductGallery({ images, productName }: ProductGalleryProps) {
  const [selectedIndex, setSelectedIndex] = useState(0);

  const activeImage = images[selectedIndex] || images[0] || null;

  return (
    <div className="space-y-4">
      {/* Main Large Image View */}
      <div className="relative aspect-square w-full rounded-xl bg-athletica-card border border-athletica-border overflow-hidden flex items-center justify-center p-4">
        {activeImage ? (
          <Image
            src={getMediaUrl(activeImage.image_path)}
            alt={activeImage.alt_text || productName}
            fill
            priority
            className="object-contain p-4 transition-opacity duration-300"
            sizes="(max-width: 1024px) 100vw, 50vw"
          />
        ) : (
          <Image
            src="/placeholder.svg"
            alt={productName}
            fill
            className="object-contain p-4"
          />
        )}
      </div>

      {/* Thumbnails Row */}
      {images.length > 1 && (
        <div className="flex gap-3 overflow-x-auto pb-2 scrollbar-none">
          {images.map((img, idx) => (
            <button
              key={img.id || idx}
              type="button"
              onClick={() => setSelectedIndex(idx)}
              className={`relative w-20 h-20 rounded-lg bg-athletica-card border-2 overflow-hidden flex-shrink-0 transition-all flex items-center justify-center p-1 ${
                selectedIndex === idx
                  ? 'border-white scale-95 shadow-md shadow-black'
                  : 'border-athletica-subtle hover:border-athletica-border opacity-70 hover:opacity-100'
              }`}
            >
              <Image
                src={getMediaUrl(img.image_path)}
                alt={img.alt_text || `${productName} thumbnail ${idx + 1}`}
                fill
                className="object-contain p-1"
                sizes="80px"
              />
            </button>
          ))}
        </div>
      )}
    </div>
  );
}
