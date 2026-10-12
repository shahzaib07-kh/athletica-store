import React from 'react';
import Link from 'next/link';
import { notFound } from 'next/navigation';
import { fetchProductDetail, fetchRelatedProducts, fetchSettings } from '@/lib/api';
import { ProductGallery } from '@/components/product/ProductGallery';
import { ProductPurchaseForm } from '@/components/product/ProductPurchaseForm';
import { ProductGrid } from '@/components/product/ProductGrid';
import { formatPrice } from '@/lib/utils';
import { Metadata } from 'next';
import { ShieldCheck, Truck, Clock } from 'lucide-react';

export const revalidate = 0; // Dynamic server rendering for stock freshness

interface ProductPageProps {
  params: Promise<{ slug: string }>;
}

export async function generateMetadata({ params }: ProductPageProps): Promise<Metadata> {
  const { slug } = await params;
  const product = await fetchProductDetail(slug).catch(() => null);

  if (!product) {
    return { title: 'Product Not Found' };
  }

  const title = `${product.name} — ${product.brand?.name || 'ATHLETICA'} | Buy in Pakistan`;
  const description =
    product.description ||
    `Buy authentic imported ${product.name} at ATHLETICA. 100% Genuine original pairs with Free Cash on Delivery across Pakistan.`;

  const imageUrl = product.primary_image ? product.primary_image : '/placeholder.svg';

  return {
    title,
    description,
    alternates: {
      canonical: `/product/${slug}`,
    },
    openGraph: {
      title,
      description,
      url: `/product/${slug}`,
      type: 'article',
      images: [
        {
          url: imageUrl,
          alt: product.name,
        },
      ],
    },
    twitter: {
      card: 'summary_large_image',
      title,
      description,
      images: [imageUrl],
    },
  };
}

export default async function ProductPage({ params }: ProductPageProps) {
  const { slug } = await params;

  const [product, settings] = await Promise.all([
    fetchProductDetail(slug).catch(() => null),
    fetchSettings().catch(() => null),
  ]);

  if (!product) {
    notFound();
  }

  const relatedProducts = await fetchRelatedProducts(
    product.id,
    product.category.id,
    product.brand?.id,
    4
  ).catch(() => []);

  const whatsappNumber = settings?.whatsapp_normalized || '923001234567';
  const hasDiscount = product.discount_percent > 0;

  // Schema.org JSON-LD Product Schema for Google Search Rich Results
  const jsonLd = {
    '@context': 'https://schema.org',
    '@type': 'Product',
    name: product.name,
    description: product.description || `Original imported ${product.name}`,
    image: product.images.map((img) => img.image_path).filter(Boolean),
    sku: product.sku || `ATH-${product.id}`,
    brand: {
      '@type': 'Brand',
      name: product.brand?.name || 'ATHLETICA',
    },
    offers: {
      '@type': 'Offer',
      price: product.price,
      priceCurrency: 'PKR',
      priceValidUntil: '2027-12-31',
      availability:
        product.status === 'active' && product.sizes.some((s) => s.is_available === 1)
          ? 'https://schema.org/InStock'
          : 'https://schema.org/OutOfStock',
      itemCondition:
        product.condition === 'new'
          ? 'https://schema.org/NewCondition'
          : 'https://schema.org/UsedCondition',
      seller: {
        '@type': 'Organization',
        name: 'ATHLETICA',
      },
    },
  };

  return (
    <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12 space-y-16">
      {/* Schema.org Product Structured Data */}
      <script
        type="application/ld+json"
        dangerouslySetInnerHTML={{ __html: JSON.stringify(jsonLd) }}
      />
      
      {/* Breadcrumb Navigation */}
      <nav className="flex items-center gap-2 text-xs font-bold text-athletica-muted uppercase tracking-wider">
        <Link href="/" className="hover:text-white transition-colors">
          Home
        </Link>
        <span>/</span>
        <Link href={`/category/${product.category.slug}`} className="hover:text-white transition-colors">
          {product.category.name}
        </Link>
        <span>/</span>
        <span className="text-white truncate max-w-xs">{product.name}</span>
      </nav>

      {/* Main Product Layout Grid */}
      <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 lg:gap-12">
        
        {/* Left Column: Gallery */}
        <div className="lg:col-span-7">
          <ProductGallery images={product.images} productName={product.name} />
        </div>

        {/* Right Column: Information & Purchase Controls */}
        <div className="lg:col-span-5 space-y-6">
          
          {/* Brand & Category Label */}
          <div className="space-y-2">
            <div className="flex items-center justify-between text-xs font-extrabold uppercase tracking-widest text-athletica-muted">
              <span>{product.brand?.name || product.category.name}</span>
              {product.product_type === 'unique' && (
                <span className="text-amber-500 font-black">★ Single-Pair Exclusive</span>
              )}
            </div>

            <h1 className="text-2xl sm:text-3xl font-black text-white leading-tight">
              {product.name}
            </h1>
          </div>

          {/* Pricing Row */}
          <div className="flex items-baseline gap-3 pb-4 border-b border-athletica-subtle">
            <span className="text-2xl sm:text-3xl font-black text-white">
              {formatPrice(product.price)}
            </span>
            {product.old_price && (
              <span className="text-base text-athletica-muted line-through font-bold">
                {formatPrice(product.old_price)}
              </span>
            )}
            {hasDiscount && (
              <span className="px-2 py-0.5 rounded text-xs font-black uppercase bg-athletica-red text-white">
                SAVE {product.discount_percent}%
              </span>
            )}
          </div>

          {/* Attributes Grid */}
          <div className="grid grid-cols-2 gap-2 text-xs text-athletica-secondary bg-athletica-card p-3 rounded-lg border border-athletica-subtle">
            {product.color && (
              <div>
                <span className="text-athletica-muted block text-[11px]">Color:</span>
                <span className="text-white font-semibold">{product.color}</span>
              </div>
            )}
            {product.condition && (
              <div>
                <span className="text-athletica-muted block text-[11px]">Condition:</span>
                <span className="text-white font-semibold uppercase">{product.condition}</span>
              </div>
            )}
            {product.sku && (
              <div>
                <span className="text-athletica-muted block text-[11px]">SKU:</span>
                <span className="text-white font-semibold">{product.sku}</span>
              </div>
            )}
            <div>
              <span className="text-athletica-muted block text-[11px]">Delivery:</span>
              <span className="text-athletica-green font-bold uppercase">FREE (Rs. 0.00)</span>
            </div>
          </div>

          {/* Interactive Size Selection & Purchase Form */}
          <ProductPurchaseForm product={product} whatsappNumber={whatsappNumber} />

          {/* Trust Value Badges */}
          <div className="p-4 rounded-xl bg-athletica-card border border-athletica-subtle space-y-3 text-xs text-athletica-secondary">
            <div className="flex items-center gap-3">
              <Truck className="w-4 h-4 text-athletica-green flex-shrink-0" />
              <span>
                <strong className="text-white">Cash on Delivery:</strong> Inspect parcel at your doorstep anywhere in Pakistan.
              </span>
            </div>
            <div className="flex items-center gap-3">
              <ShieldCheck className="w-4 h-4 text-athletica-blue flex-shrink-0" />
              <span>
                <strong className="text-white">100% Genuine:</strong> Authentic imported brand stock guaranteed.
              </span>
            </div>
            <div className="flex items-center gap-3">
              <Clock className="w-4 h-4 text-amber-400 flex-shrink-0" />
              <span>
                <strong className="text-white">Swift Dispatch:</strong> Verified and shipped within 24 hours.
              </span>
            </div>
          </div>

          {/* Description */}
          {product.description && (
            <div className="space-y-2 pt-4 border-t border-athletica-subtle">
              <h3 className="text-xs font-black uppercase tracking-widest text-white">
                Product Details
              </h3>
              <div className="text-xs text-athletica-secondary leading-relaxed whitespace-pre-line">
                {product.description}
              </div>
            </div>
          )}
        </div>
      </div>

      {/* Related Products Showcase */}
      {relatedProducts.length > 0 && (
        <div className="space-y-6 pt-12 border-t border-athletica-subtle">
          <div className="flex items-end justify-between">
            <div>
              <span className="text-xs font-black uppercase tracking-widest text-athletica-muted">
                Recommendations
              </span>
              <h2 className="text-xl sm:text-2xl font-black uppercase tracking-tight text-white mt-0.5">
                You May Also Like
              </h2>
            </div>
            <Link
              href={`/category/${product.category.slug}`}
              className="text-xs font-extrabold uppercase tracking-wider text-athletica-secondary hover:text-white"
            >
              More from {product.category.name} →
            </Link>
          </div>

          <ProductGrid products={relatedProducts} />
        </div>
      )}
    </div>
  );
}
