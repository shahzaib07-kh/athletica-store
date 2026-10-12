'use client';

import React from 'react';
import { MessageCircle } from 'lucide-react';
import { buildWhatsAppLink } from '@/lib/utils';

interface WhatsAppFloatProps {
  whatsappNumber?: string;
}

export function WhatsAppFloat({ whatsappNumber = '923001234567' }: WhatsAppFloatProps) {
  const url = buildWhatsAppLink(whatsappNumber, 'Hello ATHLETICA, I want to inquire about a product or order.');

  return (
    <a
      href={url}
      target="_blank"
      rel="noopener noreferrer"
      className="fixed bottom-6 right-6 z-40 w-14 h-14 rounded-full bg-athletica-whatsapp text-black shadow-lg shadow-athletica-whatsapp/30 flex items-center justify-center transition-transform hover:scale-110 active:scale-95 group"
      aria-label="Chat with ATHLETICA on WhatsApp"
      title="Chat on WhatsApp"
    >
      <MessageCircle className="w-7 h-7 fill-current" />
      <span className="sr-only">Chat on WhatsApp</span>
    </a>
  );
}
