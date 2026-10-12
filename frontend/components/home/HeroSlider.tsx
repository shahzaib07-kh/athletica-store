'use client';

import React, { useState, useEffect, useCallback, useRef } from 'react';
import Image from 'next/image';
import Link from 'next/link';
import { HeroSlide } from '@/lib/types';
import { getMediaUrl } from '@/lib/utils';
import { ChevronLeft, ChevronRight, ArrowRight, Play, Pause } from 'lucide-react';

interface HeroSliderProps {
  slides: HeroSlide[];
}

export function HeroSlider({ slides }: HeroSliderProps) {
  const [currentIndex, setCurrentIndex] = useState(0);
  const [isPlaying, setIsPlaying] = useState(true);
  const timerRef = useRef<NodeJS.Timeout | null>(null);

  const totalSlides = slides.length;

  const nextSlide = useCallback(() => {
    setCurrentIndex((prev) => (prev + 1) % totalSlides);
  }, [totalSlides]);

  const prevSlide = useCallback(() => {
    setCurrentIndex((prev) => (prev - 1 + totalSlides) % totalSlides);
  }, [totalSlides]);

  // Handle auto-rotation
  useEffect(() => {
    if (!isPlaying || totalSlides <= 1) return;

    timerRef.current = setInterval(() => {
      nextSlide();
    }, 6000); // 6 seconds rotation

    return () => {
      if (timerRef.current) clearInterval(timerRef.current);
    };
  }, [isPlaying, nextSlide, totalSlides]);

  // Keyboard navigation
  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === 'ArrowLeft') {
      prevSlide();
    } else if (e.key === 'ArrowRight') {
      nextSlide();
    }
  };

  if (!slides || slides.length === 0) {
    return null;
  }

  const current = slides[currentIndex];

  return (
    <section
      aria-roledescription="carousel"
      aria-label="Homepage Featured Highlights"
      className="relative w-full overflow-hidden bg-black border-b border-athletica-subtle"
      onMouseEnter={() => setIsPlaying(false)}
      onMouseLeave={() => setIsPlaying(true)}
      onKeyDown={handleKeyDown}
      tabIndex={0}
    >
      <div className="relative min-h-[480px] sm:min-h-[580px] lg:min-h-[640px] flex items-center">
        {/* Background Media Container */}
        {slides.map((slide, idx) => {
          const isActive = idx === currentIndex;
          return (
            <div
              key={slide.id}
              role="group"
              aria-roledescription="slide"
              aria-label={`${idx + 1} of ${totalSlides}: ${slide.title}`}
              aria-hidden={!isActive}
              className={`absolute inset-0 transition-opacity duration-1000 ease-in-out ${
                isActive ? 'opacity-100 z-10' : 'opacity-0 z-0 pointer-events-none'
              }`}
            >
              {slide.media_type === 'video' ? (
                <video
                  src={getMediaUrl(slide.media_path)}
                  autoPlay
                  muted
                  loop
                  playsInline
                  className="w-full h-full object-cover opacity-40 filter brightness-90"
                />
              ) : (
                <Image
                  src={getMediaUrl(slide.media_path)}
                  alt={slide.title}
                  fill
                  priority={idx === 0}
                  className="object-cover opacity-45 filter brightness-90"
                  sizes="100vw"
                />
              )}
              {/* Gradient Dark Overlay */}
              <div className="absolute inset-0 bg-gradient-to-t from-black via-black/50 to-transparent" />
              <div className="absolute inset-0 bg-gradient-to-r from-black via-black/40 to-transparent" />
            </div>
          );
        })}

        {/* Foreground Content */}
        <div className="relative z-20 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 sm:py-24 w-full">
          <div className="max-w-2xl space-y-5">
            <div className="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 backdrop-blur-md border border-white/20 text-xs font-black uppercase tracking-widest text-white">
              <span className="text-athletica-red animate-pulse">●</span>
              <span>ATHLETICA EXCLUSIVE</span>
            </div>

            <h1 className="text-3xl sm:text-5xl lg:text-6xl font-black uppercase tracking-tight text-white leading-none">
              {current.title}
            </h1>

            {current.subtitle && (
              <p className="text-sm sm:text-base text-gray-300 font-medium leading-relaxed max-w-xl">
                {current.subtitle}
              </p>
            )}

            {current.cta_text && (
              <div className="pt-3">
                <Link
                  href={current.cta_url || '/shop'}
                  className="inline-flex items-center gap-2 px-8 py-3.5 bg-white text-black font-extrabold text-xs uppercase tracking-wider rounded-md hover:bg-gray-200 transition-all group"
                >
                  <span>{current.cta_text}</span>
                  <ArrowRight className="w-4 h-4 transition-transform group-hover:translate-x-1" />
                </Link>
              </div>
            )}
          </div>
        </div>

        {/* Navigation Arrows (if multiple slides) */}
        {totalSlides > 1 && (
          <>
            <button
              onClick={prevSlide}
              aria-label="Previous slide"
              className="absolute left-4 top-1/2 -translate-y-1/2 z-30 p-2.5 rounded-full bg-black/60 border border-white/20 text-white hover:bg-white hover:text-black transition-all"
            >
              <ChevronLeft className="w-5 h-5" />
            </button>
            <button
              onClick={nextSlide}
              aria-label="Next slide"
              className="absolute right-4 top-1/2 -translate-y-1/2 z-30 p-2.5 rounded-full bg-black/60 border border-white/20 text-white hover:bg-white hover:text-black transition-all"
            >
              <ChevronRight className="w-5 h-5" />
            </button>
          </>
        )}

        {/* Indicators & Play/Pause Controls */}
        {totalSlides > 1 && (
          <div className="absolute bottom-6 left-1/2 -translate-x-1/2 z-30 flex items-center gap-3 bg-black/60 backdrop-blur-md px-4 py-2 rounded-full border border-white/10">
            {slides.map((_, idx) => (
              <button
                key={idx}
                onClick={() => setCurrentIndex(idx)}
                aria-label={`Go to slide ${idx + 1}`}
                className={`h-2 rounded-full transition-all ${
                  idx === currentIndex ? 'w-6 bg-white' : 'w-2 bg-white/40 hover:bg-white/70'
                }`}
              />
            ))}
            <button
              onClick={() => setIsPlaying(!isPlaying)}
              aria-label={isPlaying ? 'Pause slideshow' : 'Play slideshow'}
              className="ml-2 text-white/70 hover:text-white transition-colors"
            >
              {isPlaying ? <Pause className="w-3.5 h-3.5" /> : <Play className="w-3.5 h-3.5" />}
            </button>
          </div>
        )}
      </div>
    </section>
  );
}
