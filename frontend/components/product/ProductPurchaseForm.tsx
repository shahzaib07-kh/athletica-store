'use client';

import React, { useState } from 'react';
import { useRouter } from 'next/navigation';
import { ProductDetail } from '@/lib/types';
import { SizeSelector } from './SizeSelector';
import { useCartStore } from '@/lib/cart-store';
import { buildWhatsAppLink, formatPrice } from '@/lib/utils';
import { ShoppingBag, Zap, MessageCircle, AlertTriangle } from 'lucide-react';

interface ProductPurchaseFormProps {
  product: ProductDetail;
  whatsappNumber: string;
}

export function ProductPurchaseForm({ product, whatsappNumber }: ProductPurchaseFormProps) {
  const router = useRouter();
  const { addItem } = useCartStore();

  const isUnique = product.product_type === 'unique';
  const isOutOfStock = product.status === 'out_of_stock';
  const availableSizes = product.sizes.filter((s) => s.is_available === 1);

  const defaultSize = availableSizes.length > 0 ? availableSizes[0].size : '';
  const [selectedSize, setSelectedSize] = useState(defaultSize);
  const [errorMsg, setErrorMsg] = useState('');

  const handleAddToCart = () => {
    if (!selectedSize) {
      setErrorMsg('Please select a size to proceed.');
      return;
    }
    setErrorMsg('');
    addItem(product.id, selectedSize, 1, isUnique);
  };

  const handleBuyNow = () => {
    if (!selectedSize) {
      setErrorMsg('Please select a size to proceed.');
      return;
    }
    setErrorMsg('');
    addItem(product.id, selectedSize, 1, isUnique);
    router.push('/checkout');
  };

  const waInquiryMsg = `Hello ATHLETICA, I want to order "${product.name}"${
    selectedSize ? ` (Size: ${selectedSize})` : ''
  } (Price: ${formatPrice(product.price)}) via Cash on Delivery.`;

  const waUrl = buildWhatsAppLink(whatsappNumber, waInquiryMsg);

  return (
    <div className="space-y-6">
      
      {/* Sizing Component */}
      {!isOutOfStock && availableSizes.length > 0 ? (
        <div className="space-y-3">
          <SizeSelector
            sizes={product.sizes}
            selectedSize={selectedSize}
            onSelectSize={(s) => {
              setSelectedSize(s);
              setErrorMsg('');
            }}
            isUnique={isUnique}
          />
          {errorMsg && (
            <p className="text-xs text-athletica-red font-bold animate-in fade-in flex items-center gap-1.5">
              <AlertTriangle className="w-3.5 h-3.5 flex-shrink-0" />
              <span>{errorMsg}</span>
            </p>
          )}
        </div>
      ) : (
        <div className="p-4 rounded-xl bg-red-950/40 border border-athletica-red/30 text-xs text-red-200">
          <p className="font-bold">Currently Out of Stock</p>
          <p className="mt-1 text-athletica-secondary">
            Inquire directly on WhatsApp to check restock availability or request custom sourcing.
          </p>
        </div>
      )}

      {/* Action Buttons */}
      <div className="space-y-3 pt-2">
        {!isOutOfStock && availableSizes.length > 0 ? (
          <>
            <div className="grid grid-cols-2 gap-3">
              <button
                type="button"
                onClick={handleAddToCart}
                className="py-3.5 px-4 rounded-lg bg-athletica-surface border border-athletica-border text-white hover:bg-athletica-hover font-extrabold text-xs uppercase tracking-wider transition-all flex items-center justify-center gap-2 group"
              >
                <ShoppingBag className="w-4 h-4 text-athletica-secondary group-hover:text-white" />
                <span>Add to Cart</span>
              </button>

              <button
                type="button"
                onClick={handleBuyNow}
                className="py-3.5 px-4 rounded-lg bg-white text-black hover:bg-gray-200 font-extrabold text-xs uppercase tracking-wider transition-all flex items-center justify-center gap-2 shadow-lg shadow-white/10"
              >
                <Zap className="w-4 h-4 fill-current" />
                <span>Buy Now</span>
              </button>
            </div>

            <a
              href={waUrl}
              target="_blank"
              rel="noopener noreferrer"
              className="w-full py-3.5 px-4 rounded-lg bg-athletica-whatsapp text-black hover:bg-emerald-400 font-black text-xs uppercase tracking-wider transition-all flex items-center justify-center gap-2 shadow-md shadow-athletica-whatsapp/20"
            >
              <MessageCircle className="w-4 h-4 fill-current" />
              <span>Fast Order via WhatsApp</span>
            </a>
          </>
        ) : (
          <a
            href={buildWhatsAppLink(
              whatsappNumber,
              `Hello ATHLETICA, when will "${product.name}" be back in stock?`
            )}
            target="_blank"
            rel="noopener noreferrer"
            className="w-full py-3.5 px-4 rounded-lg bg-athletica-whatsapp text-black hover:bg-emerald-400 font-black text-xs uppercase tracking-wider transition-all flex items-center justify-center gap-2"
          >
            <MessageCircle className="w-4 h-4 fill-current" />
            <span>Notify Me on WhatsApp</span>
          </a>
        )}
      </div>
    </div>
  );
}
