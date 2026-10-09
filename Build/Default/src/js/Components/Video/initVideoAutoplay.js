/**
 * Activates autoplay for video elements that use data-js="videoAutoplay".
 *
 * Flow per video container:
 *  1. IntersectionObserver fires when ≥25% of the container enters the viewport
 *  2. prefers-reduced-motion check — skip autoplay if the user has requested less motion
 *  3. is-loading class added → spinner becomes visible
 *  4. preload="auto" set → browser begins buffering
 *  5. canplay event fires → video.play() called
 *  6. On successful play: is-loading removed, is-playing added → poster fades out
 *  7. Click on container toggles pause/resume (WCAG 2.2.2 — Pause, Stop, Hide)
 */

const prefersReducedMotion = () =>
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const initVideoAutoplay = () => {
    const videoContainers = document.querySelectorAll('[data-js="videoAutoplay"]');
    if (!videoContainers.length) {
        return;
    }

    const observer = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (!entry.isIntersecting) {
                    return;
                }

                const container = entry.target;
                const videoElement = container.querySelector('video');

                // No video element found (e.g. videoMissing state with poster only)
                if (!videoElement) {
                    observer.unobserve(container);
                    return;
                }

                // Respect the OS-level "reduce motion" preference
                if (prefersReducedMotion()) {
                    observer.unobserve(container);
                    return;
                }

                container.classList.add('is-loading');
                videoElement.preload = 'auto';

                videoElement.addEventListener(
                    'canplay',
                    () => {
                        videoElement.play().then(() => {
                            container.classList.remove('is-loading');
                            container.classList.add('is-playing');
                        }).catch(() => {
                            // Autoplay blocked by the browser (e.g. missing muted attribute)
                            container.classList.remove('is-loading');
                        });
                    },
                    { once: true }
                );

                // Catch load errors (e.g. 404, codec not supported)
                videoElement.addEventListener(
                    'error',
                    () => { container.classList.remove('is-loading'); },
                    { once: true }
                );

                // Each container is only activated once
                observer.unobserve(container);
            });
        },
        { threshold: 0.25 }
    );

    videoContainers.forEach((container) => {
        observer.observe(container);

        // Click toggles pause / resume — satisfies WCAG 2.2.2 (Pause, Stop, Hide)
        container.addEventListener('click', () => {
            const videoElement = container.querySelector('video');
            if (!videoElement) {
                return;
            }

            if (videoElement.paused) {
                videoElement.play().then(() => {
                    container.classList.add('is-playing');
                }).catch(() => {});
            } else {
                videoElement.pause();
                container.classList.remove('is-playing');
            }
        });
    });
};

export { initVideoAutoplay };