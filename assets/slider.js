document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.kwwd-slider-wrap .swiper').forEach(function (el) {
        var slidesVisible = parseInt(el.dataset.slidesVisible) || 5;
        var autoplay      = el.dataset.autoplay === '1';
        var delay         = parseInt(el.dataset.autoplayDelay) || 3000;
        var loop          = el.dataset.loop === '1';

        new Swiper(el, {
            slidesPerView:  slidesVisible,
            spaceBetween:   12,
            loop:           loop,
            grabCursor:     true,
            pagination: {
                el:        '.swiper-pagination',
                clickable: true,
            },
            navigation: {
                nextEl: '.swiper-button-next',
                prevEl: '.swiper-button-prev',
            },
            autoplay: autoplay ? {
                delay:                delay,
                disableOnInteraction: false,
                pauseOnMouseEnter:    true,
            } : false,
            breakpoints: {
                0: {
                    slidesPerView: Math.min(2, slidesVisible),
                    spaceBetween:  8,
                },
                480: {
                    slidesPerView: Math.min(3, slidesVisible),
                    spaceBetween:  10,
                },
                768: {
                    slidesPerView: Math.min(4, slidesVisible),
                    spaceBetween:  12,
                },
                1024: {
                    slidesPerView: slidesVisible,
                    spaceBetween:  12,
                },
            },
        });
    });
});
