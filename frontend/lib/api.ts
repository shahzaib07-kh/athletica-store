/**
 * ATHLETICA — Typed API Client
 *
 * Automatically routes requests to the PHP REST API via direct URL
 * during SSR and via reverse-proxy in the browser.
 */

import {
  ApiResponse,
  Brand,
  Category,
  CheckoutPayload,
  CheckoutResponse,
  FlashSale,
  HeroSlide,
  HydratedCartResponse,
  OrderTrackResponse,
  Product,
  ProductDetail,
  ProductListResponse,
  SiteSettings,
} from './types';

function getApiBaseUrl(): string {
  // If running on server (SSR/ISR), use direct PHP_API_URL or localhost
  if (typeof window === 'undefined') {
    return process.env.PHP_API_URL || 'http://localhost/athletica/api';
  }
  // If running in browser, use relative reverse-proxy rewrite /api/backend
  return '/api/backend';
}

async function request<T>(endpoint: string, options?: RequestInit): Promise<T> {
  const baseUrl = getApiBaseUrl();
  const cleanBase = baseUrl.endsWith('/') ? baseUrl.slice(0, -1) : baseUrl;
  const cleanEndpoint = endpoint.startsWith('/') ? endpoint : `/${endpoint}`;
  const url = `${cleanBase}${cleanEndpoint}`;

  const defaultHeaders: HeadersInit = {
    'Accept': 'application/json',
  };

  if (options?.body) {
    defaultHeaders['Content-Type'] = 'application/json';
  }

  const res = await fetch(url, {
    ...options,
    headers: {
      ...defaultHeaders,
      ...options?.headers,
    },
    // Prevent aggressive browser caching on POST
    cache: options?.method === 'POST' ? 'no-store' : (options?.cache ?? 'default'),
  });

  const json: ApiResponse<T> = await res.json();

  if (!res.ok || !json.success) {
    const errorMsg = json.error?.message || `Request failed with status ${res.status}`;
    const err = new Error(errorMsg) as Error & { code?: string; fields?: Record<string, string>; status?: number };
    err.code = json.error?.code;
    err.fields = json.error?.fields;
    err.status = res.status;
    throw err;
  }

  return json.data;
}

export async function fetchSettings(): Promise<SiteSettings> {
  return request<SiteSettings>('/settings.php', { next: { revalidate: 300 } });
}

export async function fetchCategories(): Promise<Category[]> {
  return request<Category[]>('/categories.php', { next: { revalidate: 120 } });
}

export async function fetchBrands(): Promise<Brand[]> {
  return request<Brand[]>('/brands.php', { next: { revalidate: 120 } });
}

export async function fetchSizes(): Promise<string[]> {
  return request<string[]>('/sizes.php', { next: { revalidate: 60 } });
}

export interface ProductFilters {
  q?: string;
  category?: string;
  brand?: string;
  size?: string;
  type?: 'unique' | 'regular';
  condition?: 'new' | 'used' | 'refurbished';
  sort?: 'newest' | 'price_asc' | 'price_desc';
  page?: number;
  limit?: number;
}

export async function fetchProducts(filters: ProductFilters = {}): Promise<ProductListResponse> {
  const params = new URLSearchParams();
  if (filters.q) params.set('q', filters.q);
  if (filters.category) params.set('category', filters.category);
  if (filters.brand) params.set('brand', filters.brand);
  if (filters.size) params.set('size', filters.size);
  if (filters.type) params.set('type', filters.type);
  if (filters.condition) params.set('condition', filters.condition);
  if (filters.sort) params.set('sort', filters.sort);
  if (filters.page) params.set('page', filters.page.toString());
  if (filters.limit) params.set('limit', filters.limit.toString());

  const qs = params.toString();
  const endpoint = `/products/index.php${qs ? `?${qs}` : ''}`;
  return request<ProductListResponse>(endpoint, { cache: 'no-store' });
}

export async function fetchFeaturedProducts(limit = 8): Promise<Product[]> {
  return request<Product[]>(`/products/featured.php?limit=${limit}`, { next: { revalidate: 60 } });
}

export async function fetchProductDetail(slugOrId: string | number): Promise<ProductDetail> {
  const param = typeof slugOrId === 'number' ? `id=${slugOrId}` : `slug=${encodeURIComponent(slugOrId)}`;
  return request<ProductDetail>(`/products/detail.php?${param}`, { cache: 'no-store' });
}

export async function fetchRelatedProducts(
  currentId: number,
  categoryId: number,
  brandId?: number | null,
  limit = 4
): Promise<Product[]> {
  const params = new URLSearchParams({
    id: currentId.toString(),
    category_id: categoryId.toString(),
    limit: limit.toString(),
  });
  if (brandId) {
    params.set('brand_id', brandId.toString());
  }
  return request<Product[]>(`/products/related.php?${params.toString()}`, { next: { revalidate: 60 } });
}

export async function hydrateCart(
  items: Array<{ product_id: number; size: string; quantity: number }>
): Promise<HydratedCartResponse> {
  return request<HydratedCartResponse>('/cart/hydrate.php', {
    method: 'POST',
    body: JSON.stringify({ items }),
  });
}

export async function submitCheckout(payload: CheckoutPayload): Promise<CheckoutResponse> {
  return request<CheckoutResponse>('/checkout.php', {
    method: 'POST',
    body: JSON.stringify(payload),
  });
}

export async function trackOrder(orderNumber: string, phone?: string): Promise<OrderTrackResponse> {
  const params = new URLSearchParams({ order_number: orderNumber });
  if (phone) params.set('phone', phone);
  return request<OrderTrackResponse>(`/orders/track.php?${params.toString()}`, { cache: 'no-store' });
}

export async function fetchHeroSlides(): Promise<HeroSlide[]> {
  try {
    return await request<HeroSlide[]>('/slides.php', { cache: 'no-store' });
  } catch {
    return [];
  }
}

export async function fetchFlashSale(): Promise<FlashSale | null> {
  try {
    return await request<FlashSale | null>('/flash-sale.php', { cache: 'no-store' });
  } catch {
    return null;
  }
}
