import type { Metadata } from 'next';
import { Nunito_Sans } from 'next/font/google';
import './globals.css';
import { AnnouncementBar } from '@/components/layout/AnnouncementBar';
import { Navbar } from '@/components/layout/Navbar';
import { Footer } from '@/components/layout/Footer';
import { WhatsAppFloat } from '@/components/layout/WhatsAppFloat';
import { CartDrawer } from '@/components/cart/CartDrawer';
import { fetchCategories, fetchSettings } from '@/lib/api';

const nunitoSans = Nunito_Sans({
  subsets: ['latin'],
  weight: ['400', '600', '700', '800', '900'],
  variable: '--font-nunito-sans',
  display: 'swap',
});

export const metadata: Metadata = {
  metadataBase: new URL(process.env.NEXT_PUBLIC_SITE_URL || 'https://athletica.pk'),
  title: {
    default: 'ATHLETICA — Premium Imported Footwear & Athletic Gear',
    template: '%s | ATHLETICA',
  },
  description:
    'Handpicked authentic imported athletic footwear, running shoes, and sportswear in Pakistan. Free nationwide delivery and Cash on Delivery.',
  keywords: [
    'athletic shoes Pakistan',
    'imported running shoes',
    'original sneakers Lahore',
    'Nike shoes Pakistan',
    'ASICS running shoes',
    'Cash on delivery footwear Pakistan',
    'ATHLETICA',
  ],
  authors: [{ name: 'ATHLETICA Pakistan' }],
  creator: 'ATHLETICA',
  publisher: 'ATHLETICA',
  alternates: {
    canonical: './',
  },
  robots: {
    index: true,
    follow: true,
    googleBot: {
      index: true,
      follow: true,
      'max-video-preview': -1,
      'max-image-preview': 'large',
      'max-snippet': -1,
    },
  },
  openGraph: {
    siteName: 'ATHLETICA',
    type: 'website',
    locale: 'en_PK',
    title: 'ATHLETICA — Premium Imported Footwear & Athletic Gear',
    description:
      'Handpicked authentic imported athletic footwear and sportswear in Pakistan. Free nationwide delivery and Cash on Delivery.',
    url: './',
  },
  twitter: {
    card: 'summary_large_image',
    title: 'ATHLETICA — Premium Imported Footwear & Athletic Gear',
    description:
      'Handpicked authentic imported athletic footwear and sportswear in Pakistan. Cash on Delivery nationwide.',
  },
};

export default async function RootLayout({
  children,
}: {
  children: React.ReactNode;
}) {
  let categories: import('@/lib/types').Category[] = [];
  let settings: import('@/lib/types').SiteSettings | null = null;

  try {
    const [catRes, setRes] = await Promise.all([
      fetchCategories().catch(() => []),
      fetchSettings().catch(() => null),
    ]);
    categories = catRes;
    settings = setRes;
  } catch (err) {
    console.error('Failed to load initial layout data:', err);
  }

  return (
    <html lang="en" className={nunitoSans.variable}>
      <body className="bg-[#f9fafb] text-gray-900 min-h-screen flex flex-col font-sans antialiased selection:bg-black selection:text-white">
        <AnnouncementBar />
        <Navbar categories={categories} settings={settings} />
        <main className="flex-1">{children}</main>
        <Footer categories={categories} settings={settings} />
        <CartDrawer />
        <WhatsAppFloat whatsappNumber={settings?.whatsapp_normalized || '923001234567'} />
      </body>
    </html>
  );
}
