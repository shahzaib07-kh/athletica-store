import React from 'react';
import Link from 'next/link';
import { fetchSettings } from '@/lib/api';
import { buildWhatsAppLink } from '@/lib/utils';
import { Metadata } from 'next';
import { MessageCircle, Phone, Mail, MapPin, Clock } from 'lucide-react';

export const metadata: Metadata = {
  title: 'Contact Us & Location — ATHLETICA',
  description:
    'Get in touch with ATHLETICA customer support via WhatsApp, phone, or email. We are here to assist with sizing, orders, and nationwide shipping inquiries.',
  alternates: {
    canonical: '/contact',
  },
  openGraph: {
    title: 'Contact ATHLETICA — Direct WhatsApp & Customer Support',
    description: 'Get immediate size advice and customer support for authentic imported footwear.',
    url: '/contact',
    type: 'website',
  },
  twitter: {
    card: 'summary_large_image',
    title: 'Contact Us — ATHLETICA',
    description: 'Customer support, location, and WhatsApp contact for ATHLETICA.',
  },
};

export default async function ContactPage() {
  const settings = await fetchSettings().catch(() => null);

  const waNumber = settings?.whatsapp_normalized || '923001234567';
  const waUrl = buildWhatsAppLink(
    waNumber,
    'Hello ATHLETICA, I need assistance with an inquiry or order.'
  );

  return (
    <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-16 space-y-10">
      {/* Breadcrumb */}
      <nav className="flex items-center gap-2 text-xs font-bold text-athletica-muted uppercase tracking-wider">
        <Link href="/" className="hover:text-white transition-colors">
          Home
        </Link>
        <span>/</span>
        <span className="text-white">Contact Us</span>
      </nav>

      {/* Header */}
      <div className="border-b border-athletica-subtle pb-6 space-y-2">
        <h1 className="text-3xl sm:text-4xl font-black uppercase tracking-tight text-white">
          Get In Touch
        </h1>
        <p className="text-xs text-athletica-secondary">
          We're here to help with sizing, product availability, and order status updates.
        </p>
      </div>

      <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
        
        {/* Contact Methods */}
        <div className="space-y-4">
          
          {/* WhatsApp Direct */}
          <div className="p-6 rounded-xl bg-gradient-to-br from-emerald-950/70 to-athletica-card border border-emerald-900/50 space-y-3">
            <div className="flex items-center gap-2 text-athletica-whatsapp font-bold text-xs uppercase tracking-wider">
              <MessageCircle className="w-4 h-4 fill-current" />
              <span>WhatsApp Support (Fastest)</span>
            </div>
            <p className="text-xs text-emerald-200/80">
              Immediate response for order confirmation, live photographs, and sizing guidance.
            </p>
            <a
              href={waUrl}
              target="_blank"
              rel="noopener noreferrer"
              className="inline-flex items-center gap-2 px-5 py-3 rounded-lg bg-athletica-whatsapp text-black font-black text-xs uppercase tracking-wider hover:bg-emerald-400 transition-colors shadow-md shadow-athletica-whatsapp/20"
            >
              <span>Chat on WhatsApp</span>
              <span>→</span>
            </a>
          </div>

          {/* Details Card */}
          <div className="p-6 rounded-xl bg-athletica-card border border-athletica-subtle space-y-4 text-xs">
            
            {/* Phone */}
            <div className="flex items-start gap-3">
              <Phone className="w-4 h-4 text-athletica-secondary mt-0.5" />
              <div>
                <span className="text-athletica-muted block text-[11px] uppercase tracking-wider font-bold">
                  Direct Phone:
                </span>
                <a
                  href={`tel:${settings?.phone || '+923001234567'}`}
                  className="text-white font-bold hover:underline"
                >
                  {settings?.phone || '+92 300 1234567'}
                </a>
              </div>
            </div>

            {/* Email */}
            <div className="flex items-start gap-3">
              <Mail className="w-4 h-4 text-athletica-secondary mt-0.5" />
              <div>
                <span className="text-athletica-muted block text-[11px] uppercase tracking-wider font-bold">
                  Email Inquiries:
                </span>
                <a
                  href={`mailto:${settings?.email || 'contact@athletica.pk'}`}
                  className="text-white font-bold hover:underline"
                >
                  {settings?.email || 'contact@athletica.pk'}
                </a>
              </div>
            </div>

            {/* Address */}
            <div className="flex items-start gap-3">
              <MapPin className="w-4 h-4 text-athletica-secondary mt-0.5" />
              <div>
                <span className="text-athletica-muted block text-[11px] uppercase tracking-wider font-bold">
                  Location:
                </span>
                <span className="text-white font-bold">
                  {settings?.address || 'Lahore, Pakistan'}
                </span>
              </div>
            </div>
          </div>
        </div>

        {/* Operating Hours & FAQs */}
        <div className="p-6 rounded-xl bg-athletica-card border border-athletica-subtle space-y-6 text-xs text-athletica-secondary">
          
          <div className="space-y-2 pb-4 border-b border-athletica-subtle">
            <div className="flex items-center gap-2 text-white font-bold text-xs uppercase tracking-wider">
              <Clock className="w-4 h-4 text-amber-400" />
              <span>Business & Dispatch Hours</span>
            </div>
            <p>Monday – Saturday: 10:00 AM – 9:00 PM (PKT)</p>
            <p>Sunday: 12:00 PM – 6:00 PM (PKT)</p>
          </div>

          <div className="space-y-4">
            <h3 className="font-extrabold text-white text-xs uppercase tracking-wider">
              Frequently Asked Questions
            </h3>

            <div>
              <h4 className="font-bold text-white text-xs mb-1">
                How does Cash on Delivery work?
              </h4>
              <p className="leading-relaxed">
                You place your order online or via WhatsApp, we dispatch your package via courier, and you pay the exact cash amount upon delivery.
              </p>
            </div>

            <div>
              <h4 className="font-bold text-white text-xs mb-1">
                How can I verify shoe sizing?
              </h4>
              <p className="leading-relaxed">
                Message us on WhatsApp with your exact foot length or existing athletic sneaker size (in EU/CM) for precision size recommendation.
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
