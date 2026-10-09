export function AnchorForStickyNavbar() {
  const anchors = document.querySelectorAll('.anchor[id]');
  if (anchors.length === 0) {
    // Nothing to do if there are no anchors
    return;
  }

  // Accept multiple possible selectors (Bootstrap utility + custom)
  const nav =
    document.querySelector('.sticky-lg-top') ||
    document.querySelector('.sticky-nav') ||
    document.querySelector('[data-sticky-nav]');

  // Media query: only sticky >= lg (992px). If du nutzt andere Breakpoints, bitte hier + im CSS anpassen.
  const mql = window.matchMedia('(min-width: 992px)');

  const setVar = (valuePx) => {
    // set on :root
    document.documentElement.style.setProperty('--scroll-offset', valuePx + 'px');
  };

  const measureNav = () => {
    if (!nav) {
      // No sticky nav found: ensure offset = 0
      setVar(0);
      return 0;
    }
    // If sticky only on lg+, otherwise 0
    const h = mql.matches ? nav.offsetHeight || 0 : 0;
    setVar(h);
    return h;
  };

  // Ensure we measure at safe times
  const updateScrollOffset = () => {
    // Two rafs to wait for layout/TTF/CLS changes
    requestAnimationFrame(() => {
      requestAnimationFrame(() => {
        measureNav();
      });
    });
  };

  // Safari compatibility for matchMedia change
  const listenMq = () => {
    if (typeof mql.addEventListener === 'function') {
      mql.addEventListener('change', updateScrollOffset);
    } else if (typeof mql.addListener === 'function') {
      mql.addListener(updateScrollOffset);
    }
  };

  // Optional: observe nav resizing (logo swap, sticky class toggles, etc.)
  let resizeObserver;
  if (window.ResizeObserver && nav) {
    resizeObserver = new ResizeObserver(updateScrollOffset);
    resizeObserver.observe(nav);
  }

  // Initial set (after DOM ready)
  updateScrollOffset();

  // Also correct after full load (fonts/images can change height)
  window.addEventListener('load', updateScrollOffset);
  window.addEventListener('resize', updateScrollOffset);
  listenMq();

  // Helper to scroll to hash respecting current offset (only when sticky applies)
  const scrollToHash = (hash) => {
    if (!hash) return;
    const target = document.querySelector(hash);
    if (!target || !target.classList.contains('anchor')) return;

    const prevBehavior = document.documentElement.style.scrollBehavior;
    document.documentElement.style.scrollBehavior = 'auto';
    // Ensure offset is set just before scrolling
    updateScrollOffset();
    requestAnimationFrame(() => {
      target.scrollIntoView({ block: 'start' });
      document.documentElement.style.scrollBehavior = prevBehavior;
    });
  };

  // Correct position if page loads with a hash
  if (window.location.hash) {
    // run twice to be extra safe after layout settles
    scrollToHash(window.location.hash);
    setTimeout(() => scrollToHash(window.location.hash), 0);
  }

  // Internal anchor navigation
  window.addEventListener('hashchange', () => {
    scrollToHash(window.location.hash);
  });

  // Return a small API for debugging if needed
  return {
    debugMeasure() {
      const h = measureNav();
      // eslint-disable-next-line no-console
      console.info('[AnchorForStickyNavbar] navHeight:', h, 'mq>=lg:', mql.matches);
      return h;
    }
  };
}
