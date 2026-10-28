/* Global News — Main JS */

// ============================================
// HERO SLIDER
// ============================================
(function() {
  const container = document.getElementById('slidesContainer');
  if (!container) return;

  const slides = container.querySelectorAll('.slide');
  const dots   = document.querySelectorAll('.dot');
  let current  = 0;
  let timer;

  function goTo(idx) {
    slides[current].classList.remove('active');
    dots[current] && dots[current].classList.remove('active');
    current = (idx + slides.length) % slides.length;
    slides[current].classList.add('active');
    dots[current] && dots[current].classList.add('active');
  }

  function next() { goTo(current + 1); }
  function prev() { goTo(current - 1); }

  function startAuto() {
    stopAuto();
    timer = setInterval(next, 5000);
  }
  function stopAuto() { clearInterval(timer); }

  document.getElementById('nextBtn')?.addEventListener('click', () => { next(); startAuto(); });
  document.getElementById('prevBtn')?.addEventListener('click', () => { prev(); startAuto(); });

  dots.forEach(dot => {
    dot.addEventListener('click', () => {
      goTo(parseInt(dot.dataset.i));
      startAuto();
    });
  });

  // Touch swipe
  let touchX = 0;
  container.addEventListener('touchstart', e => { touchX = e.changedTouches[0].clientX; });
  container.addEventListener('touchend', e => {
    const dx = e.changedTouches[0].clientX - touchX;
    if (Math.abs(dx) > 40) { dx < 0 ? next() : prev(); startAuto(); }
  });

  startAuto();
})();

// ============================================
// HAMBURGER MOBILE NAV
// ============================================
(function() {
  const btn = document.getElementById('hamburger');
  const nav = document.getElementById('mainNav');
  if (!btn || !nav) return;
  btn.addEventListener('click', () => {
    nav.classList.toggle('open');
    btn.classList.toggle('open');
  });
})();

// ============================================
// AD CLICK TRACKER
// ============================================
function adClick(id) {
  fetch('ad-click.php?id=' + id);
}

// ============================================
// SMOOTH SCROLL for anchor links
// ============================================
document.querySelectorAll('a[href^="#"]').forEach(a => {
  a.addEventListener('click', e => {
    const target = document.querySelector(a.getAttribute('href'));
    if (target) { e.preventDefault(); target.scrollIntoView({ behavior: 'smooth' }); }
  });
});

// ============================================
// Scroll-triggered animated advertisements
// Ads play their animation when they enter the viewport
// ============================================
(function() {
  function initAdAnimations() {
    const ads = document.querySelectorAll('.ad-animate-on-scroll');
    if (!ads.length) return;

    if ('IntersectionObserver' in window) {
      const observer = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
          if (entry.isIntersecting) {
            const el = entry.target;
            el.classList.add('ad-visible');
            // If ad is marked noncontinuous, play now and schedule repeats
            if (el.dataset && el.dataset.noncontinuous == '1') {
              playAdAnimation(el);
            } else {
              // otherwise play once
              el.classList.add('animated');
            }
            observer.unobserve(entry.target);
          }
        });
      }, { threshold: 0.15 });
      ads.forEach(function(ad) { observer.observe(ad); });
    } else {
      // Fallback: show all immediately and start animations
      ads.forEach(function(ad) { ad.classList.add('ad-visible'); if (ad.dataset && ad.dataset.noncontinuous == '1') playAdAnimation(ad); else ad.classList.add('animated'); });
    }

    function playAdAnimation(el) {
      // If an interval is already set, don't create another
      if (el.__adAnimInterval) return;
      const baseDelay = parseInt(el.dataset.animInterval) || 12; // seconds
      const run = function() {
        // trigger animation by toggling 'animated'
        el.classList.remove('animated');
        // force reflow so animation restarts
        void el.offsetWidth;
        el.classList.add('animated');
        // after animation completes, remove 'animated' so next toggle will replay it
        const computed = window.getComputedStyle(el);
        // try to read animation-duration (may be like "1.5s")
        let adur = 1500;
        try {
          const ad = computed.animationDuration || computed.getPropertyValue('animation-duration');
          if (ad) {
            // takes first duration if multiple
            adur = Math.round(parseFloat(ad.split(',')[0]) * 1000);
          }
        } catch (e) {}
        setTimeout(function() { el.classList.remove('animated'); }, adur + 60);
      };

      // initial run immediately
      run();
      // schedule repeats at baseDelay +/- 30%
      const jitter = Math.max(4, Math.round(baseDelay * 0.3));
      el.__adAnimInterval = setInterval(function() {
        const nextDelay = baseDelay * 1000 + (Math.floor(Math.random() * (jitter*2)) - jitter) * 1000;
        setTimeout(run, 0);
      }, (baseDelay + jitter) * 1000);
    }
  }

  function initLazyImageFadeIn() {
    const lazyImages = document.querySelectorAll('img[loading="lazy"]');
    if (!lazyImages.length) return;

    lazyImages.forEach(function(img) {
      if (img.complete && img.naturalWidth !== 0) {
        img.classList.add('loaded');
      } else {
        img.addEventListener('load', function() {
          img.classList.add('loaded');
        });
      }
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function() {
      initAdAnimations();
      initLazyImageFadeIn();
    });
  } else {
    initAdAnimations();
    initLazyImageFadeIn();
  }
})();
