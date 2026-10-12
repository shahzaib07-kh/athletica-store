import React from 'react';
import Link from 'next/link';
import { Metadata } from 'next';
import {
  fetchBrands,
  fetchCategories,
  fetchProducts,
  fetchSizes,
  ProductFilters,
} from '@/lib/api';
import { ProductGrid } from '@/components/product/ProductGrid';
import { SlidersHorizontal } from 'lucide-react';

export const revalidate = 0; // Dynamic server rendering for catalog filters

interface ShopPageProps {
  searchParams: Promise<{
    q?: string;
    category?: string;
    brand?: string;
    size?: string;
    type?: 'unique' | 'regular';
    condition?: 'new' | 'used' | 'refurbished';
    sort?: 'newest' | 'price_asc' | 'price_desc';
    page?: string;
  }>;
}

export async function generateMetadata({ searchParams }: ShopPageProps): Promise<Metadata> {
  const params = await searchParams;
  const titleParts = [];
  if (params.q) titleParts.push(`Search: "${params.q}"`);
  if (params.category) titleParts.push(`Category: ${params.category}`);
  if (params.brand) titleParts.push(`Brand: ${params.brand}`);
  if (params.size) titleParts.push(`Size: ${params.size}`);

  const title = titleParts.length > 0
    ? `${titleParts.join(' | ')} — ATHLETICA Catalog`
    : 'Shop All Athletic Footwear & Sportswear — ATHLETICA';

  return {
    title,
    description:
      'Browse our full collection of genuine imported athletic footwear, running shoes, and sportswear. Free Cash on Delivery across Pakistan.',
    alternates: {
      canonical: '/shop',
    },
    openGraph: {
      title,
      description: 'Explore genuine imported athletic footwear and sportswear in Pakistan.',
      url: '/shop',
    },
  };
}

export default async function ShopPage({ searchParams }: ShopPageProps) {
  const resolvedParams = await searchParams;
  const pageNum = parseInt(resolvedParams.page || '1', 10) || 1;

  const filters: ProductFilters = {
    q: resolvedParams.q,
    category: resolvedParams.category,
    brand: resolvedParams.brand,
    size: resolvedParams.size,
    type: resolvedParams.type,
    condition: resolvedParams.condition,
    sort: resolvedParams.sort || 'newest',
    page: pageNum,
    limit: 20,
  };

  const [productsRes, categories, brands, sizes] = await Promise.all([
    fetchProducts(filters).catch(() => ({ products: [], pagination: {} as any })),
    fetchCategories().catch(() => []),
    fetchBrands().catch(() => []),
    fetchSizes().catch(() => []),
  ]);

  const { products, pagination } = productsRes;

  const buildFilterUrl = (newParams: Record<string, string | undefined>) => {
    const p = new URLSearchParams();
    const merged = {
      q: resolvedParams.q,
      category: resolvedParams.category,
      brand: resolvedParams.brand,
      size: resolvedParams.size,
      type: resolvedParams.type,
      sort: resolvedParams.sort,
      ...newParams,
    };

    Object.entries(merged).forEach(([k, v]) => {
      if (v) p.set(k, v);
    });

    const qs = p.toString();
    return `/shop${qs ? `?${qs}` : ''}`;
  };

  const hasActiveFilters = Boolean(
    resolvedParams.q ||
      resolvedParams.category ||
      resolvedParams.brand ||
      resolvedParams.size ||
      resolvedParams.type
  );

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12 space-y-8">
      
      {/* Header & Breadcrumb */}
      <div className="space-y-3">
        <nav className="flex items-center gap-2 text-xs font-bold text-athletica-muted uppercase tracking-wider">
          <Link href="/" className="hover:text-white transition-colors">
            Home
          </Link>
          <span>/</span>
          <span className="text-white">Shop Catalog</span>
        </nav>

        <div className="flex flex-col sm:flex-row sm:items-end justify-between gap-4 border-b border-athletica-subtle pb-4">
          <div>
            <h1 className="text-2xl sm:text-3xl font-black uppercase tracking-tight text-white">
              {resolvedParams.q ? `Search: "${resolvedParams.q}"` : 'All Products'}
            </h1>
            <p className="text-xs text-athletica-secondary mt-1">
              Showing {products.length} of {pagination?.total_items || products.length} in-stock athletic items
            </p>
          </div>

          {/* Sort Selector Form */}
          <div className="flex items-center gap-2">
            <span className="text-xs text-athletica-muted font-bold uppercase tracking-wider">
              Sort:
            </span>
            <div className="flex gap-1 text-xs">
              <Link
                href={buildFilterUrl({ sort: 'newest' })}
                className={`px-3 py-1.5 rounded border text-xs font-bold uppercase tracking-wider transition-colors ${
                  filters.sort === 'newest'
                    ? 'bg-white text-black border-white'
                    : 'bg-athletica-surface border-athletica-border text-athletica-secondary hover:text-white'
                }`}
              >
                Newest
              </Link>
              <Link
                href={buildFilterUrl({ sort: 'price_asc' })}
                className={`px-3 py-1.5 rounded border text-xs font-bold uppercase tracking-wider transition-colors ${
                  filters.sort === 'price_asc'
                    ? 'bg-white text-black border-white'
                    : 'bg-athletica-surface border-athletica-border text-athletica-secondary hover:text-white'
                }`}
              >
                Price: Low-High
              </Link>
              <Link
                href={buildFilterUrl({ sort: 'price_desc' })}
                className={`px-3 py-1.5 rounded border text-xs font-bold uppercase tracking-wider transition-colors ${
                  filters.sort === 'price_desc'
                    ? 'bg-white text-black border-white'
                    : 'bg-athletica-surface border-athletica-border text-athletica-secondary hover:text-white'
                }`}
              >
                Price: High-Low
              </Link>
            </div>
          </div>
        </div>
      </div>

      {/* Filter Chips Bar */}
      <div className="space-y-4 p-4 rounded-xl bg-athletica-card border border-athletica-subtle">
        
        {/* Category Pills */}
        <div className="flex items-center gap-2 overflow-x-auto pb-1 text-xs">
          <span className="text-athletica-muted font-extrabold uppercase tracking-wider flex-shrink-0">
            Category:
          </span>
          <Link
            href={buildFilterUrl({ category: undefined, page: undefined })}
            className={`px-3 py-1 rounded-full text-xs font-bold tracking-wide uppercase transition-colors flex-shrink-0 ${
              !resolvedParams.category
                ? 'bg-white text-black'
                : 'bg-athletica-surface text-athletica-secondary border border-athletica-border hover:text-white'
            }`}
          >
            All
          </Link>
          {categories.map((cat) => (
            <Link
              key={cat.id}
              href={buildFilterUrl({ category: cat.slug, page: undefined })}
              className={`px-3 py-1 rounded-full text-xs font-bold tracking-wide uppercase transition-colors flex-shrink-0 ${
                resolvedParams.category === cat.slug
                  ? 'bg-white text-black'
                  : 'bg-athletica-surface text-athletica-secondary border border-athletica-border hover:text-white'
              }`}
            >
              {cat.name} ({cat.product_count})
            </Link>
          ))}
        </div>

        {/* Brand Pills */}
        {brands.length > 0 && (
          <div className="flex items-center gap-2 overflow-x-auto pb-1 text-xs border-t border-athletica-subtle/50 pt-3">
            <span className="text-athletica-muted font-extrabold uppercase tracking-wider flex-shrink-0">
              Brand:
            </span>
            <Link
              href={buildFilterUrl({ brand: undefined, page: undefined })}
              className={`px-3 py-1 rounded-full text-xs font-bold tracking-wide uppercase transition-colors flex-shrink-0 ${
                !resolvedParams.brand
                  ? 'bg-white text-black'
                  : 'bg-athletica-surface text-athletica-secondary border border-athletica-border hover:text-white'
              }`}
            >
              All Brands
            </Link>
            {brands.map((b) => (
              <Link
                key={b.id}
                href={buildFilterUrl({ brand: b.slug, page: undefined })}
                className={`px-3 py-1 rounded-full text-xs font-bold tracking-wide uppercase transition-colors flex-shrink-0 ${
                  resolvedParams.brand === b.slug
                    ? 'bg-white text-black'
                    : 'bg-athletica-surface text-athletica-secondary border border-athletica-border hover:text-white'
                }`}
              >
                {b.name}
              </Link>
            ))}
          </div>
        )}

        {/* Size Pills */}
        {sizes.length > 0 && (
          <div className="flex items-center gap-2 overflow-x-auto pb-1 text-xs border-t border-athletica-subtle/50 pt-3">
            <span className="text-athletica-muted font-extrabold uppercase tracking-wider flex-shrink-0">
              Size:
            </span>
            <Link
              href={buildFilterUrl({ size: undefined, page: undefined })}
              className={`px-2.5 py-1 rounded text-xs font-bold uppercase transition-colors flex-shrink-0 ${
                !resolvedParams.size
                  ? 'bg-white text-black'
                  : 'bg-athletica-surface text-athletica-secondary border border-athletica-border hover:text-white'
              }`}
            >
              All
            </Link>
            {sizes.map((s) => (
              <Link
                key={s}
                href={buildFilterUrl({ size: s, page: undefined })}
                className={`px-2.5 py-1 rounded text-xs font-bold uppercase transition-colors flex-shrink-0 ${
                  resolvedParams.size === s
                    ? 'bg-white text-black'
                    : 'bg-athletica-surface text-athletica-secondary border border-athletica-border hover:text-white'
                }`}
              >
                {s}
              </Link>
            ))}
          </div>
        )}

        {/* Reset Active Filters */}
        {hasActiveFilters && (
          <div className="pt-2 border-t border-athletica-subtle flex items-center justify-between text-xs">
            <span className="text-athletica-muted font-medium">Filters active</span>
            <Link
              href="/shop"
              className="text-athletica-red font-bold hover:underline uppercase tracking-wider"
            >
              Reset All Filters ✕
            </Link>
          </div>
        )}
      </div>

      {/* Products Grid */}
      <ProductGrid products={products} emptyMessage="No products match your filter criteria." />

      {/* Pagination Controls */}
      {pagination && pagination.total_pages > 1 && (
        <div className="flex items-center justify-center gap-2 pt-8">
          {pagination.has_prev_page && (
            <Link
              href={buildFilterUrl({ page: (pageNum - 1).toString() })}
              className="px-4 py-2 rounded bg-athletica-surface border border-athletica-border text-xs font-bold uppercase text-white hover:bg-athletica-hover"
            >
              ← Previous
            </Link>
          )}

          <span className="px-4 py-2 text-xs font-bold text-athletica-muted uppercase tracking-wider">
            Page {pagination.current_page} of {pagination.total_pages}
          </span>

          {pagination.has_next_page && (
            <Link
              href={buildFilterUrl({ page: (pageNum + 1).toString() })}
              className="px-4 py-2 rounded bg-athletica-surface border border-athletica-border text-xs font-bold uppercase text-white hover:bg-athletica-hover"
            >
              Next →
            </Link>
          )}
        </div>
      )}
    </div>
  );
}
