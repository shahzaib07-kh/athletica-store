import React from 'react';
import Link from 'next/link';

export default function NotFound() {
  return (
    <div className="max-w-xl mx-auto px-4 py-24 text-center space-y-6">
      <div className="text-6xl sm:text-7xl font-black text-athletica-red tracking-tighter">
        404
      </div>
      <div className="space-y-2">
        <h1 className="text-2xl font-black uppercase text-white tracking-tight">
          Page Not Found
        </h1>
        <p className="text-xs sm:text-sm text-athletica-secondary">
          The product, category, or page you are looking for does not exist or has been relocated.
        </p>
      </div>
      <div>
        <Link
          href="/"
          className="inline-block px-8 py-3.5 rounded bg-white text-black font-extrabold text-xs uppercase tracking-wider hover:bg-gray-200 transition-colors"
        >
          Return to Homepage
        </Link>
      </div>
    </div>
  );
}
