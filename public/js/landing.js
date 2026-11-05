/* EDUZIYO sotuv sahifasi: skroll animatsiyalari va kichik effektlar (kutubxonasiz) */
(function () {
  'use strict';
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  // Elementlarni skroll bilan ko'rsatish
  var items = [].slice.call(document.querySelectorAll('.lp-reveal'));
  if (!('IntersectionObserver' in window) || reduce) {
    items.forEach(function (el) { el.classList.add('is-in'); });
  } else {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting) { e.target.classList.add('is-in'); io.unobserve(e.target); }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
    items.forEach(function (el) { io.observe(el); });
  }

  // Skroll chizig'i, navbar soyasi, "yuqoriga" tugmasi
  var bar = document.getElementById('lp-progress');
  var nav = document.getElementById('lp-nav');
  var top = document.getElementById('lp-top');
  var ticking = false;
  function onScroll() {
    var y = window.scrollY || document.documentElement.scrollTop;
    var h = document.documentElement.scrollHeight - window.innerHeight;
    if (bar) bar.style.width = (h > 0 ? (y / h) * 100 : 0) + '%';
    if (nav) nav.classList.toggle('is-scrolled', y > 12);
    if (top) top.classList.toggle('is-visible', y > 600);
    ticking = false;
  }
  window.addEventListener('scroll', function () { if (!ticking) { ticking = true; requestAnimationFrame(onScroll); } }, { passive: true });
  onScroll();
  if (top) top.addEventListener('click', function () { window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' }); });

  // Hero panel maketi: sichqoncha bilan yengil 3D qiyalash
  var tilt = document.querySelector('.lp-tilt');
  if (tilt && !reduce && window.matchMedia('(hover: hover)').matches) {
    var wrap = tilt.parentElement;
    wrap.addEventListener('mousemove', function (e) {
      var r = wrap.getBoundingClientRect();
      var x = (e.clientX - r.left) / r.width - 0.5;
      var y = (e.clientY - r.top) / r.height - 0.5;
      tilt.style.transform = 'perspective(1000px) rotateY(' + (x * 7) + 'deg) rotateX(' + (-y * 6) + 'deg)';
    });
    wrap.addEventListener('mouseleave', function () { tilt.style.transform = ''; });
  }

  // Hero sarlavhasida so'zlarni almashtirib turish
  var rot = document.getElementById('lp-rotate');
  if (rot && !reduce) {
    var words = JSON.parse(rot.getAttribute('data-words') || '[]');
    var i = 0;
    if (words.length > 1) {
      setInterval(function () {
        rot.style.transition = 'opacity .3s, transform .3s';
        rot.style.opacity = 0; rot.style.transform = 'translateY(8px)';
        setTimeout(function () {
          i = (i + 1) % words.length; rot.textContent = words[i];
          rot.style.opacity = 1; rot.style.transform = 'none';
        }, 320);
      }, 2600);
    }
  }
})();
