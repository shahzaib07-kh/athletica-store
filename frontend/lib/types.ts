/**
 * ATHLETICA — TypeScript Type Definitions
 * Directly matching the PHP REST API contracts
 */

export interface ApiResponse<T> {
  success: boolean;
  data: T;
  meta?: {
    timestamp: string;
    [key: string]: unknown;
  };
  error?: {
    code: string;
    message: string;
    fields?: Record<string, string>;
  };
}

export interface SiteSettings {
  site_name: string;
  tagline: string;
  logo: string | null;
  favicon: string | null;
  currency_symbol: string;
  delivery_fee_default: string;
  whatsapp_number: string;
  whatsapp_normalized: string;
  email: string;
  phone: string;
  address: string;
  footer_text: string;
  social_links: {
    facebook: string | null;
    instagram: string | null;
    tiktok: string | null;
  };
}

export interface Category {
  id: number;
  name: string;
  slug: string;
  description: string | null;
  image: string | null;
  sort_order: number;
  product_count: number;
}

export interface Brand {
  id: number;
  name: string;
  slug: string;
  logo: string | null;
  description: string | null;
  sort_order: number;
  product_count: number;
}

export interface ProductSize {
  id: number;
  size: string;
  is_available: number;
  sort_order: number;
}

export interface ProductImage {
  id: number;
  image_path: string;
  alt_text: string | null;
  is_primary: number;
  sort_order: number;
}

export interface Product {
  id: number;
  name: string;
  slug: string;
  sku: string | null;
  description?: string | null;
  price: string;
  old_price: string | null;
  discount_percent: number;
  color: string | null;
  condition: 'new' | 'used' | 'refurbished';
  product_type: 'unique' | 'regular';
  status: 'active' | 'inactive' | 'out_of_stock';
  is_featured: number;
  category: {
    id: number;
    name: string;
    slug: string;
  };
  brand?: {
    id: number;
    name: string;
    slug: string;
    logo?: string | null;
  } | null;
  primary_image: string | null;
  available_sizes: string[];
}

export interface ProductDetail extends Product {
  images: ProductImage[];
  sizes: ProductSize[];
  whatsapp_inquiry_url: string;
}

export interface PaginationMeta {
  current_page: number;
  per_page: number;
  total_items: number;
  total_pages: number;
  has_next_page: boolean;
  has_prev_page: boolean;
}

export interface ProductListResponse {
  products: Product[];
  pagination: PaginationMeta;
}

// Client-side cart item reference stored in localStorage
export interface CartItemRef {
  productId: number;
  size: string;
  quantity: number;
}

// Authoritative hydrated cart item returned by PHP backend
export interface HydratedCartItem {
  cart_key: string;
  product_id: number;
  name: string;
  slug: string;
  color: string | null;
  condition: string;
  product_type: 'unique' | 'regular';
  size: string;
  size_id: number | null;
  quantity: number;
  unit_price: string;
  regular_price?: string;
  sale_price?: string | null;
  is_flash_sale?: boolean;
  discount_percent?: number;
  line_total: string;
  primary_image: string | null;
  category_name?: string | null;
  brand_name?: string | null;
  is_available: boolean;
  unavailable_reason: string | null;
}

export interface HeroSlide {
  id: number;
  title: string;
  subtitle: string | null;
  media_path: string;
  media_type: 'image' | 'video';
  cta_text: string | null;
  cta_url: string | null;
  sort_order: number;
}

export interface FlashSaleProduct {
  id: number;
  name: string;
  slug: string;
  sku: string;
  regular_price: string;
  sale_price: string;
  discount_percent: number;
  primary_image: string | null;
  available_sizes: string[];
}

export interface FlashSale {
  id: number;
  title: string;
  starts_at: string;
  ends_at: string;
  remaining_seconds: number;
  products: FlashSaleProduct[];
}

export interface HydratedCartResponse {
  items: HydratedCartItem[];
  subtotal: string;
  delivery_fee: string;
  total_amount: string;
  total_quantity: number;
  has_unavailable_items: boolean;
  can_checkout: boolean;
}

export interface CheckoutCustomer {
  name: string;
  phone: string;
  email?: string;
  city: string;
  delivery_address: string;
}

export interface CheckoutPayload {
  customer: CheckoutCustomer;
  items: Array<{
    product_id: number;
    size: string;
    quantity: number;
  }>;
}

export interface CheckoutResponse {
  order_number: string;
  customer: CheckoutCustomer;
  subtotal: string;
  delivery_fee: string;
  total_amount: string;
  payment_method: string;
  items: Array<{
    product_id: number;
    name: string;
    size: string;
    color: string | null;
    unit_price: string;
    quantity: number;
    line_total: string;
  }>;
  whatsapp: {
    number: string;
    direct_url: string;
    prefilled_message: string;
  };
}

export interface OrderTrackResponse {
  order_number: string;
  status: 'pending' | 'confirmed' | 'processing' | 'shipped' | 'delivered' | 'cancelled';
  created_at: string;
  customer: {
    name: string;
    phone?: string;
    city: string;
    delivery_address: string;
  };
  subtotal: string;
  delivery_fee: string;
  total_amount: string;
  payment_method: string;
  items: Array<{
    id: number;
    product_id: number | null;
    name: string;
    size: string | null;
    color: string | null;
    price: string;
    quantity: number;
    line_total: string;
  }>;
  whatsapp_support_url: string;
}
