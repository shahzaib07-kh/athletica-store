import React from 'react';
import Link from 'next/link';
import { notFound } from 'next/navigation';
import { fetchCategories, fetchProducts } from '@/lib/api';
import { ProductGrid } from '@/components/product/ProductGrid';
import { Metadata } from 'next';

export const revalidate = 60;

interface CategoryPageProps {
  params: Promise<{ slug: string }>;
  searchParams: Promise<{ sort?: 'newest' | 'price_asc' | 'price_desc' }>;
}

export async function generateMetadata({ params }: CategoryPageProps): Promise<Metadata> {
  const { slug } = await params;
  const categories = await fetchCategories().catch(() => []);
  const cat = categories.find((c) => c.slug === slug);

  if (!cat) {
    return { title: 'Category Not Found' };
  }

  const title = `${cat.name} — Imported Footwear & Sportswear | ATHLETICA`;
  const description =
    cat.description ||
    `Shop authentic imported ${cat.name} with Cash on Delivery nationwide across Pakistan.`;

  return {
    title,
    description,
    alternates: {
      canonical: `/category/${slug}`,
    },
    openGraph: {
      title,
      description,
      url: `/category/${slug}`,
      type: 'website',
    },
    twitter: {
      card: 'summary_large_image',
      title,
      description,
    },
  };
}

export default async function CategoryPage({ params, searchParams }: CategoryPageProps) {
  const { slug } = await params;
  const { sort = 'newest' } = await searchParams;

  const [categories, productsRes] = await Promise.all([
    fetchCategories().catch(() => []),
    fetchProducts({ category: slug, sort }).catch(() => ({ products: [], pagination: {} as any })),
  ]);

  const currentCategory = categories.find((c) => c.slug === slug);
  if (!currentCategory) {
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
        <span className="text-white">{currentCategory.name}</span>
      </nav>

      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-end justify-between gap-4 border-b border-athletica-subtle pb-6">
        <div>
          <h1 className="text-3xl sm:text-4xl font-black uppercase tracking-tight text-white">
            {currentCategory.name}
          </h1>
          <p className="text-xs text-athletica-secondary mt-1 max-w-xl">
            {currentCategory.description || `Showing in-stock authentic imported ${currentCategory.name}`}
          </p>
        </div>

        {/* Sort Options */}
        <div className="flex gap-1 text-xs">
          <Link
            href={`/category/${slug}?sort=newest`}
            className={`px-3 py-1.5 rounded border text-xs font-bold uppercase tracking-wider transition-colors ${
              sort === 'newest'
                ? 'bg-white text-black border-white'
                : 'bg-athletica-surface border-athletica-border text-athletica-secondary hover:text-white'
            }`}
          >
            Newest
          </Link>
          <Link
            href={`/category/${slug}?sort=price_asc`}
            className={`px-3 py-1.5 rounded border text-xs font-bold uppercase tracking-wider transition-colors ${
              sort === 'price_asc'
                ? 'bg-white text-black border-white'
                : 'bg-athletica-surface border-athletica-border text-athletica-secondary hover:text-white'
            }`}
          >
            Price: Low-High
          </Link>
          <Link
            href={`/category/${slug}?sort=price_desc`}
            className={`px-3 py-1.5 rounded border text-xs font-bold uppercase tracking-wider transition-colors ${
              sort === 'price_desc'
                ? 'bg-white text-black border-white'
                : 'bg-athletica-surface border-athletica-border text-athletica-secondary hover:text-white'
            }`}
          >
            Price: High-Low
          </Link>
        </div>
      </div>

      {/* Products */}
      <ProductGrid
        products={products}
        emptyMessage={`No active products currently found in ${currentCategory.name}. Check back soon for fresh imports.`}
      />
    </div>
  );
}
