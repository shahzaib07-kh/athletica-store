import React from 'react';
import Link from 'next/link';
import { notFound } from 'next/navigation';
import { fetchBrands, fetchProducts } from '@/lib/api';
import { ProductGrid } from '@/components/product/ProductGrid';
import { Metadata } from 'next';

export const revalidate = 60;

interface BrandPageProps {
  params: Promise<{ slug: string }>;
}

export async function generateMetadata({ params }: BrandPageProps): Promise<Metadata> {
  const { slug } = await params;
  const brands = await fetchBrands().catch(() => []);
  const brand = brands.find((b) => b.slug === slug);

  if (!brand) {
    return { title: 'Brand Not Found' };
  }

  const title = `${brand.name} — Authentic Imported Footwear & Apparel | ATHLETICA`;
  const description =
    brand.description ||
    `Shop 100% original imported ${brand.name} footwear and sportswear with Cash on Delivery in Pakistan.`;

  return {
    title,
    description,
    alternates: {
      canonical: `/brand/${slug}`,
    },
    openGraph: {
      title,
      description,
      url: `/brand/${slug}`,
      type: 'website',
    },
    twitter: {
      card: 'summary_large_image',
      title,
      description,
    },
  };
}

export default async function BrandPage({ params }: BrandPageProps) {
  const { slug } = await params;

  const [brands, productsRes] = await Promise.all([
    fetchBrands().catch(() => []),
    fetchProducts({ brand: slug, limit: 30 }).catch(() => ({ products: [], pagination: {} as any })),
  ]);

  const currentBrand = brands.find((b) => b.slug === slug);
  if (!currentBrand) {
    notFound();
  }

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
        <span className="text-white">{currentBrand.name}</span>
      </nav>

      {/* Header */}
      <div className="border-b border-athletica-subtle pb-6 space-y-2">
        <h1 className="text-3xl sm:text-4xl font-black uppercase tracking-tight text-white">
          {currentBrand.name}
        </h1>
        <p className="text-xs text-athletica-secondary max-w-xl">
          {currentBrand.description || `Showing in-stock authentic imported ${currentBrand.name} products`}
        </p>
      </div>

      {/* Products */}
      <ProductGrid
        products={products}
        emptyMessage={`No active products currently found for ${currentBrand.name}.`}
      />
    </div>
  );
}
