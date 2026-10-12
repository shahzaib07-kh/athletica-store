'use client';

import React, { useState, useEffect } from 'react';

interface FlashSaleCountdownProps {
  initialSeconds: number;
}

export function FlashSaleCountdown({ initialSeconds }: FlashSaleCountdownProps) {
  const [timeLeft, setTimeLeft] = useState(initialSeconds);

  useEffect(() => {
    setTimeLeft(initialSeconds);
  }, [initialSeconds]);

  useEffect(() => {
    if (timeLeft <= 0) return;

    const timer = setInterval(() => {
      setTimeLeft((prev) => Math.max(0, prev - 1));
    }, 1000);

    return () => clearInterval(timer);
  }, [timeLeft]);

  if (timeLeft <= 0) {
    return (
      <div className="inline-flex items-center px-3 py-1 rounded bg-red-950/60 border border-red-800 text-red-400 text-xs font-bold uppercase">
        Sale Concluded
      </div>
    );
  }

  const days = Math.floor(timeLeft / (3600 * 24));
  const hours = Math.floor((timeLeft % (3600 * 24)) / 3600);
  const minutes = Math.floor((timeLeft % 3600) / 60);
  const seconds = timeLeft % 60;

  const pad = (n: number) => n.toString().padStart(2, '0');

  return (
    <div className="flex items-center gap-1.5 sm:gap-2 text-white font-mono" role="timer" aria-live="off">
      <div className="flex flex-col items-center justify-center bg-black/70 border border-athletica-subtle px-2 sm:px-2.5 py-1 rounded">
        <span className="text-sm sm:text-base font-black text-red-500">{pad(days)}</span>
        <span className="text-[9px] uppercase tracking-wider text-athletica-muted">Days</span>
      </div>
      <span className="text-red-500 font-bold">:</span>
      <div className="flex flex-col items-center justify-center bg-black/70 border border-athletica-subtle px-2 sm:px-2.5 py-1 rounded">
        <span className="text-sm sm:text-base font-black text-red-500">{pad(hours)}</span>
        <span className="text-[9px] uppercase tracking-wider text-athletica-muted">Hrs</span>
      </div>
      <span className="text-red-500 font-bold">:</span>
      <div className="flex flex-col items-center justify-center bg-black/70 border border-athletica-subtle px-2 sm:px-2.5 py-1 rounded">
        <span className="text-sm sm:text-base font-black text-red-500">{pad(minutes)}</span>
        <span className="text-[9px] uppercase tracking-wider text-athletica-muted">Min</span>
      </div>
      <span className="text-red-500 font-bold">:</span>
      <div className="flex flex-col items-center justify-center bg-black/70 border border-athletica-subtle px-2 sm:px-2.5 py-1 rounded">
        <span className="text-sm sm:text-base font-black text-red-500">{pad(seconds)}</span>
        <span className="text-[9px] uppercase tracking-wider text-athletica-muted">Sec</span>
      </div>
    </div>
  );
}
