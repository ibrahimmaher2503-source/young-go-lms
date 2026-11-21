const home = document.querySelector('[data-public-home]');
const motion = window.matchMedia('(prefers-reduced-motion: no-preference)');

if (home && motion.matches && 'IntersectionObserver' in window) {
    const observer = new IntersectionObserver((entries) => {
        for (const entry of entries) {
            if (entry.isIntersecting) {
                entry.target.classList.add('home-revealing');
                observer.unobserve(entry.target);
            }
        }
    }, { threshold: 0.12 });

    for (const section of home.querySelectorAll('[data-home-reveal]')) {
        observer.observe(section);
    }
}
