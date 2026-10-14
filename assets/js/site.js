(function () {
  'use strict';

  var desktop = window.matchMedia('(min-width: 900px)');
  var header = document.getElementById('site-header');

  /* ---------- header shadow on scroll ---------- */
  function onScroll() {
    if (header) header.classList.toggle('scrolled', window.scrollY > 8);
  }
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  /* ---------- mobile menu ---------- */
  var menuBtn = document.querySelector('.menu-toggle');
  var nav = document.getElementById('primary-nav');

  function setMenu(open) {
    if (!menuBtn || !nav) return;
    nav.classList.toggle('open', open);
    menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    menuBtn.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
  }
  if (menuBtn && nav) {
    menuBtn.addEventListener('click', function () { setMenu(!nav.classList.contains('open')); });
    nav.addEventListener('click', function (e) { if (e.target.closest('a')) setMenu(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') setMenu(false); });
    document.addEventListener('click', function (e) {
      if (!nav.classList.contains('open')) return;
      if (!e.target.closest('#primary-nav') && !e.target.closest('.menu-toggle')) setMenu(false);
    });
  }

  /* ---------- folds: About / Services (home page) ----------
     On phones they stay closed until tapped (one open at a time).
     On desktop CSS shows everything and these buttons are hidden. */
  var folds = Array.prototype.slice.call(document.querySelectorAll('[data-fold]'));

  function setFold(fold, open) {
    var btn = fold.querySelector('.fold-btn');
    fold.classList.toggle('is-open', open);
    if (btn) btn.setAttribute('aria-expanded', open ? 'true' : 'false');
  }

  function openFold(id, scroll) {
    var target = document.getElementById(id);
    if (!target || folds.indexOf(target) === -1) return false;
    if (!desktop.matches) {
      folds.forEach(function (f) { setFold(f, f === target); });
    }
    if (scroll) {
      // wait a frame so the panel has its real height before we scroll
      requestAnimationFrame(function () {
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      });
    }
    return true;
  }

  folds.forEach(function (fold) {
    var btn = fold.querySelector('.fold-btn');
    if (!btn) return;
    btn.addEventListener('click', function () {
      var willOpen = !fold.classList.contains('is-open');
      folds.forEach(function (f) { setFold(f, false); });
      setFold(fold, willOpen);
      if (willOpen) {
        requestAnimationFrame(function () { fold.scrollIntoView({ behavior: 'smooth', block: 'start' }); });
      }
    });
  });

  // Links that should open a fold (hero buttons, nav links)
  document.addEventListener('click', function (e) {
    var link = e.target.closest('a[data-open]');
    if (!link) return;
    var id = link.getAttribute('data-open');
    // only intercept when we're already on the home page
    if (folds.length && document.getElementById(id)) {
      e.preventDefault();
      openFold(id, true);
      if (history.replaceState) history.replaceState(null, '', '#' + id);
    }
  });

  // Arriving with #about / #services in the address (e.g. from a service page)
  function openFromHash() {
    var id = (location.hash || '').replace('#', '');
    if (id && openFold(id, false)) {
      setTimeout(function () {
        var el = document.getElementById(id);
        if (el) el.scrollIntoView({ block: 'start' });
      }, 60);
    }
  }
  if (folds.length) {
    openFromHash();
    window.addEventListener('hashchange', openFromHash);
  }

  /* ---------- <details> in About: open on desktop, closed on phones ---------- */
  var detailEls = document.querySelectorAll('details[data-desk-open]');
  function syncDetails() {
    detailEls.forEach(function (d) { d.open = desktop.matches; });
  }
  if (detailEls.length) {
    syncDetails();
    if (desktop.addEventListener) desktop.addEventListener('change', syncDetails);
  }

  /* ---------- tabs (service page: Experience / Work) ---------- */
  document.querySelectorAll('[data-tabs]').forEach(function (wrap) {
    var tabs = Array.prototype.slice.call(wrap.querySelectorAll('[role="tab"]'));
    var panels = tabs.map(function (t) { return document.getElementById(t.getAttribute('aria-controls')); });

    function select(index, focus) {
      tabs.forEach(function (t, i) {
        var on = i === index;
        t.setAttribute('aria-selected', on ? 'true' : 'false');
        t.tabIndex = on ? 0 : -1;
        if (panels[i]) panels[i].hidden = !on;
      });
      if (focus) tabs[index].focus();
    }

    tabs.forEach(function (t, i) {
      t.addEventListener('click', function () {
        select(i, false);
        if (history.replaceState) history.replaceState(null, '', '#' + t.id.replace('tab-', ''));
        // on phones, bring the list into view so the person sees the content they asked for
        if (!desktop.matches && wrap.getBoundingClientRect().top > 120) {
          wrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      });
      t.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowRight') { e.preventDefault(); select((i + 1) % tabs.length, true); }
        if (e.key === 'ArrowLeft') { e.preventDefault(); select((i - 1 + tabs.length) % tabs.length, true); }
      });
    });

    // deep link: service.php?id=3#work
    var h = (location.hash || '').replace('#', '');
    var start = 0;
    tabs.forEach(function (t, i) { if (t.id === 'tab-' + h) start = i; });
    select(start, false);
  });

  /* ---------- photo viewer ---------- */
  var box = document.getElementById('lightbox');
  if (box) {
    var boxImg = box.querySelector('img');
    var closeBtn = box.querySelector('.lightbox-close');

    document.addEventListener('click', function (e) {
      var trigger = e.target.closest('[data-lightbox]');
      if (!trigger) return;
      boxImg.src = trigger.getAttribute('data-lightbox');
      boxImg.alt = trigger.getAttribute('data-alt') || '';
      if (typeof box.showModal === 'function') box.showModal();
      else box.setAttribute('open', '');
    });
    var closeBox = function () {
      if (typeof box.close === 'function') box.close(); else box.removeAttribute('open');
    };
    closeBtn.addEventListener('click', closeBox);
    box.addEventListener('click', function (e) { if (e.target === box) closeBox(); }); // click outside the photo
  }

  /* ---------- reveal on scroll ---------- */
  var reveals = document.querySelectorAll('.reveal');
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (en) {
        if (en.isIntersecting) { en.target.classList.add('in'); io.unobserve(en.target); }
      });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });
    reveals.forEach(function (el) { io.observe(el); });
  } else {
    reveals.forEach(function (el) { el.classList.add('in'); });
  }
  // safety net: never leave content invisible if the observer is slow or blocked
  setTimeout(function () { reveals.forEach(function (el) { el.classList.add('in'); }); }, 4000);
})();
