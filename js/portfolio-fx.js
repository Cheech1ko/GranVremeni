// =========================================================
// PORTFOLIO-FX — доп. слой поведения поверх main.js/header.js
// Ничего не переопределяет, только добавляет.
// =========================================================
(function () {
    'use strict';

    var reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // ---------- Прелоадер ----------
    function setupPreloader() {
        var pl = document.getElementById('fxPreloader');
        if (!pl) return;

        if (reduceMotion) {
            pl.classList.add('fx-pl-hide');
            return;
        }

        var minVisible = 1600; // мс — чтобы анимация сборки лого успела доиграть
        var startTime = Date.now();

        function hide() {
            var elapsed = Date.now() - startTime;
            var wait = Math.max(minVisible - elapsed, 0);
            setTimeout(function () {
                pl.classList.add('fx-pl-hide');
                document.body.style.overflow = '';
            }, wait);
        }

        document.body.style.overflow = 'hidden';
        if (document.readyState === 'complete') {
            hide();
        } else {
            window.addEventListener('load', hide);
            // Подстраховка — не держим прелоадер вечно, если что-то долго грузится
            setTimeout(hide, 4000);
        }
    }

    // ---------- Бегущая строка ----------
    function buildMarquee() {
        var mount = document.getElementById('fxMarqueeMount');
        if (!mount) return;

        var messages = [
            'Гарантия до 50 лет',
            'Собственное производство',
            'Установка по Москве и МО',
            '800+ реализованных проектов',
            'Выезд замерщика бесплатно',
            'Работаем без выходных'
        ];

        function buildTrack() {
            var html = '';
            messages.forEach(function (m) {
                html += '<span class="fx-marquee-item"><i class="fas fa-gem"></i>' + m + '</span>';
            });
            return html;
        }

        var section = document.createElement('div');
        section.className = 'fx-marquee';
        var track = document.createElement('div');
        track.className = 'fx-marquee-track';
        // Дублируем контент дважды подряд — так бесшовно закольцовывается translateX(-50%)
        track.innerHTML = buildTrack() + buildTrack();
        section.appendChild(track);
        mount.appendChild(section);
    }

    // ---------- Scroll reveal ----------
    function setupReveal() {
        var targets = document.querySelectorAll('.fx-reveal');
        if (!targets.length) return;

        if (reduceMotion || !('IntersectionObserver' in window)) {
            targets.forEach(function (el) { el.classList.add('fx-in'); });
            return;
        }

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('fx-in');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.15, rootMargin: '0px 0px -40px 0px' });

        targets.forEach(function (el) { observer.observe(el); });
    }

    // ---------- Счётчики в hero-stats ----------
    function setupCountUp() {
        var nums = document.querySelectorAll('.hero-stat-num[data-count]');
        if (!nums.length) return;

        function animateNum(el) {
            var target = parseInt(el.getAttribute('data-count'), 10);
            var prefix = el.getAttribute('data-prefix') || '';
            var suffix = el.getAttribute('data-suffix') || '';
            if (reduceMotion || isNaN(target)) {
                el.textContent = prefix + target + suffix;
                return;
            }
            var start = 0;
            var duration = 1200;
            var startTime = null;

            function step(ts) {
                if (!startTime) startTime = ts;
                var progress = Math.min((ts - startTime) / duration, 1);
                var eased = 1 - Math.pow(1 - progress, 3);
                var value = Math.round(start + (target - start) * eased);
                el.textContent = prefix + value + suffix;
                if (progress < 1) requestAnimationFrame(step);
            }
            requestAnimationFrame(step);
        }

        if (!('IntersectionObserver' in window)) {
            nums.forEach(animateNum);
            return;
        }

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    animateNum(entry.target);
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.4 });

        nums.forEach(function (el) { observer.observe(el); });
    }

    document.addEventListener('DOMContentLoaded', function () {
        setupPreloader();
        buildMarquee();
        setupReveal();
        setupCountUp();
    });
})();
