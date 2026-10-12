import React from 'react';
import Link from 'next/link';
import { fetchProducts, fetchSizes } from '@/lib/api';
import { ProductGrid } from '@/components/product/ProductGrid';
import { Metadata } from 'next';

export const revalidate = 60;

interface SizePageProps {
  params: Promise<{ size: string }>;
}

export async function generateMetadata({ params }: SizePageProps): Promise<Metadata> {
  const { size } = await params;
  const decoded = decodeURIComponent(size);
  const title = `Size ${decoded} — Available Footwear & Sportswear | ATHLETICA`;
  const description = `Shop all in-stock athletic footwear and sportswear available in size ${decoded}. Fast Cash on Delivery across Pakistan.`;

  return {
    title,
    description,
    alternates: {
      canonical: `/size/${encodeURIComponent(size)}`,
    },
    openGraph: {
      title,
      description,
      url: `/size/${encodeURIComponent(size)}`,
      type: 'website',
    },
    twitter: {
      card: 'summary_large_image',
      title,
      description,
    },
  };
}

export default async function SizePage({ params }: SizePageProps) {
  const { size } = await params;
  const decodedSize = decodeURIComponent(size);

  const [allSizes, productsRes] = await Promise.all([
    fetchSizes().catch(() => []),
    fetchProducts({ size: decodedSize, limit: 30 }).catch(() => ({ products: [], pagination: {} as any })),
  ]);

  const { products } = productsRes;

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12 space-y-8">
      {/* Breadcrumb */}
      <nav className="flex items-center gap-2 text-xs font-bold text-athletica-muted uppercase tracking-wider">
        <Link href="/" className="hover:text-white transition-colors">
          Home
        </Link>
        <span>/</span>
        <Link href="/shop" className="hover:text-white transition-colors">
          Shop
        </Link>
        <span>/</span>
        <span className="text-white">Size: {decodedSize}</span>
      </nav>

      {/* Header */}
      <div className="border-b border-athletica-subtle pb-6 space-y-2">
        <h1 className="text-3xl sm:text-4xl font-black uppercase tracking-tight text-white">
          Size: {decodedSize}
        </h1>
        <p className="text-xs text-athletica-secondary">
          Showing {products.length} in-stock athletic items available in {decodedSize}
        </p>
      </div>

      {/* Quick Size Switcher */}
      {allSizes.length > 0 && (
        <div className="p-4 rounded-xl bg-athletica-card border border-athletica-subtle space-y-2">
          <span className="text-xs font-extrabold uppercase tracking-wider text-athletica-muted block">
            Select Other Sizes:
          </span>
          <div className="flex flex-wrap gap-2">
            <Link
              href="/shop"
              className="px-3 py-1.5 rounded bg-athletica-surface border border-athletica-border text-xs font-bold uppercase text-athletica-secondary hover:text-white"
            >
              All
            </Link>
            {allSizes.map((s) => (
              <Link
                key={s}
                href={`/size/${encodeURIComponent(s)}`}
                className={`px-3 py-1.5 rounded text-xs font-bold uppercase transition-colors ${
                  s.toLowerCase() === decodedSize.toLowerCase()
                    ? 'bg-white text-black font-black'
                    : 'bg-athletica-surface border border-athletica-border text-athletica-secondary hover:text-white'
                }`}
              >
                {s}
              </Link>
            ))}
          </div>
        </div>
      )}

      {/* Products */}
      <ProductGrid
        products={products}
        emptyMessage={`No in-stock products currently available in size ${decodedSize}.`}
      />
    </div>
  );
}
