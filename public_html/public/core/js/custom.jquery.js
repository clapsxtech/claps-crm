$( document ).ready(function() {
        $('.navbar-toggler').click(function(e){
        var target = $(this).attr('data-target');
        $(target).toggleClass('show in');
    });
    

    $('.popup-youtube, .popup-vimeo, .popup-gmaps').magnificPopup({
      type: 'iframe',
      mainClass: 'mfp-fade',
      removalDelay: 160,
      preloader: true,
      fixedContentPos: true
	  });

    $('.list-gallery').slick({
        dots: false,
        infinite: false,
        speed: 300,
        slidesToShow: 4,
        slidesToScroll: 4,
        responsive: [
          {
            breakpoint: 1024,
            settings: "unslick",
          },
          {
            breakpoint: 600,
            settings: {
              slidesToShow: 2,
              slidesToScroll: 2
            }
          },
          {
            breakpoint: 480,
            settings: {
              slidesToShow: 1,
              slidesToScroll: 1
            }
          }
          // You can unslick at a given breakpoint now by adding:
          // settings: "unslick"
          // instead of a settings object
        ]
    });
  if($('.popup-gallery').length > 0) {
        $('.popup-gallery').magnificPopup({
            delegate: 'a',
            type: 'image',
            tLoading: 'Loading image #%curr%...',
            mainClass: 'mfp-img-mobile',
            gallery: {
                enabled: true,
                navigateByImgClick: true,
                preload: [0,1] // Will preload 0 - before current, and 1 after the current image
            },
            image: {
                tError: '<a href="%url%">The image #%curr%</a> could not be loaded.',
                titleSrc: function(item) {
                    return item.el.attr('title');
                }
            }
        });
    }
    $(window).scroll(function () {
      var footer = $("footer").offset().top;
      var footerBottom = $("footer").offset().top;
      var bottom_of_screen = $(window).scrollTop() + $(window).innerHeight();
      var top_of_screen = $(window).scrollTop();
      var footerIsVisible =
        bottom_of_screen > footer && top_of_screen < footerBottom;
      if (footerIsVisible) {
        $(".youtube_holder").addClass("youtube-bottom-fix");
      } else {
        $(".youtube_holder").removeClass("youtube-bottom-fix");
      }
    });
    
});


