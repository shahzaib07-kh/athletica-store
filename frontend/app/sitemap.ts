import { MetadataRoute } from 'next';
import { fetchBrands, fetchCategories, fetchProducts, fetchSizes } from '@/lib/api';

export const revalidate = 3600; // Revalidate sitemap every hour

export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
  const siteUrl = process.env.NEXT_PUBLIC_SITE_URL || 'https://athletica.pk';
  const cleanSiteUrl = siteUrl.endsWith('/') ? siteUrl.slice(0, -1) : siteUrl;

  const [productsRes, categories, brands, sizes] = await Promise.all([
    fetchProducts({ limit: 50 }).catch(() => ({ products: [], pagination: {} as any })),
    fetchCategories().catch(() => []),
    fetchBrands().catch(() => []),
    fetchSizes().catch(() => []),
  ]);

  const now = new Date();

  // 1. Static Core Routes
  const staticRoutes: MetadataRoute.Sitemap = [
    {
      url: `${cleanSiteUrl}`,
      lastModified: now,
      changeFrequency: 'daily',
      priority: 1.0,
    },
    {
      url: `${cleanSiteUrl}/shop`,
      lastModified: now,
      changeFrequency: 'daily',
      priority: 0.9,
    },
    {
      url: `${cleanSiteUrl}/about`,
      lastModified: now,
      changeFrequency: 'monthly',
      priority: 0.5,
    },
    {
      url: `${cleanSiteUrl}/contact`,
      lastModified: now,
      changeFrequency: 'monthly',
      priority: 0.5,
    },
  ];

  // 2. Dynamic Product Routes
  const productRoutes: MetadataRoute.Sitemap = (productsRes.products || []).map((p) => ({
    url: `${cleanSiteUrl}/product/${p.slug}`,
    lastModified: now,
    changeFrequency: 'weekly',
    priority: 0.8,
  }));

  // 3. Dynamic Category Routes
  const categoryRoutes: MetadataRoute.Sitemap = categories.map((c) => ({
    url: `${cleanSiteUrl}/category/${c.slug}`,
    lastModified: now,
    changeFrequency: 'weekly',
    priority: 0.7,
  }));

  // 4. Dynamic Brand Routes
  const brandRoutes: MetadataRoute.Sitemap = brands.map((b) => ({
    url: `${cleanSiteUrl}/brand/${b.slug}`,
    lastModified: now,
    changeFrequency: 'weekly',
    priority: 0.7,
  }));

  // 5. Dynamic Size Filter Routes
  const sizeRoutes: MetadataRoute.Sitemap = sizes.map((s) => ({
    url: `${cleanSiteUrl}/size/${encodeURIComponent(s)}`,
    lastModified: now,
    changeFrequency: 'weekly',
    priority: 0.6,
  }));

  return [
    ...staticRoutes,
    ...productRoutes,
    ...categoryRoutes,
    ...brandRoutes,
    ...sizeRoutes,
  ];
}
