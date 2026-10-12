import React from 'react';

export function AnnouncementBar() {
  const tickerItems = [
    '🚚 CASH ON DELIVERY NATIONWIDE',
    '📦 OPEN & CHECK PARCEL BEFORE PAYMENT',
    '👟 100% ORIGINAL IMPORTED SHOES & SNEAKERS',
    '🩴 PREMIUM ATHLETIC SLIDERS',
    '👕 ORIGINAL IMPORTED SHIRTS & TEES',
    '👖 ATHLETIC TROUSERS & TRACK PANTS',
    '🧢 STYLISH IMPORTED CAPS',
    '⚡ FAST 2-4 DAYS DISPATCH ACROSS PAKISTAN',
  ];

  return (
    <div className="bg-black text-white text-[11px] sm:text-xs py-2 overflow-hidden border-b border-gray-800 select-none">
      <div className="flex whitespace-nowrap animate-marquee">
        <div className="flex items-center gap-8 px-4 font-bold tracking-wider uppercase">
          {tickerItems.map((item, i) => (
            <span key={i} className="inline-flex items-center gap-2">
              <span>{item}</span>
              <span className="text-gray-600">•</span>
            </span>
          ))}
        </div>
        <div className="flex items-center gap-8 px-4 font-bold tracking-wider uppercase" aria-hidden="true">
          {tickerItems.map((item, i) => (
            <span key={`clone-${i}`} className="inline-flex items-center gap-2">
              <span>{item}</span>
              <span className="text-gray-600">•</span>
            </span>
          ))}
        </div>
      </div>
    </div>
  );
}
