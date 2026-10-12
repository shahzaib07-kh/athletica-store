import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}

export function formatPrice(amount: number | string | null | undefined, symbol = 'Rs.'): string {
  if (amount === null || amount === undefined || amount === '') {
    return `${symbol} 0.00`;
  }
  const numeric = typeof amount === 'string' ? parseFloat(amount) : amount;
  if (isNaN(numeric)) {
    return `${symbol} 0.00`;
  }
  return `${symbol} ${numeric.toLocaleString('en-PK', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`;
}

export function getMediaUrl(path: string | null | undefined): string {
  if (!path) {
    return '/placeholder.svg';
  }
  if (path.startsWith('http://') || path.startsWith('https://') || path.startsWith('data:')) {
    return path;
  }
  const mediaBase = process.env.NEXT_PUBLIC_MEDIA_URL || 'http://localhost/athletica';
  const cleanBase = mediaBase.endsWith('/') ? mediaBase.slice(0, -1) : mediaBase;
  const cleanPath = path.startsWith('/') ? path : `/${path}`;
  return `${cleanBase}${cleanPath}`;
}

export function normalizeWhatsAppNumber(phone: string, defaultCountryCode = '92'): string {
  const digits = phone.replace(/[^\d]/g, '');
  if (digits.startsWith(`00${defaultCountryCode}`)) {
    return digits.slice(2);
  }
  if (digits.startsWith('0') && digits.length === 11) {
    return `${defaultCountryCode}${digits.slice(1)}`;
  }
  if (digits.length === 10 && digits.startsWith('3')) {
    return `${defaultCountryCode}${digits}`;
  }
  return digits;
}

export function buildWhatsAppLink(phone: string, message: string): string {
  const normalized = normalizeWhatsAppNumber(phone);
  return `https://wa.me/${normalized}?text=${encodeURIComponent(message)}`;
}
