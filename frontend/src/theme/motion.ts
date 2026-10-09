/**
 * Motion started from script (design system: motion). CSS transitions and
 * animations are reduced in style.css; scrolling requested from code is
 * reduced here, because `behavior: 'smooth'` is not covered by CSS.
 */
export function prefersReducedMotion(): boolean {
  return typeof window !== 'undefined' && typeof window.matchMedia === 'function' && window.matchMedia('(prefers-reduced-motion: reduce)').matches
}

export function scrollBehavior(): ScrollBehavior {
  return prefersReducedMotion() ? 'auto' : 'smooth'
}
