(function ($) {
  "use strict";

  // ==========================================
  //      Start Document Ready function
  // ==========================================
  $(document).ready(function () {
    // ============== Mobile Nav Menu Dropdown Js Start =======================
    function toggleSubMenu() {
      if ($(window).width() <= 991) {
        $(".has-submenu")
          .off("click")
          .on("click", function () {
            $(this)
              .toggleClass("active")
              .siblings(".has-submenu")
              .removeClass("active")
              .find(".nav-submenu")
              .slideUp(300);
            $(this).find(".nav-submenu").stop(true, true).slideToggle(300);
          });
      } else {
        $(".has-submenu").off("click");
      }
    }

    toggleSubMenu();
    $(window).resize(toggleSubMenu);
    // ============== Mobile Nav Menu Dropdown Js End =======================

    // ===================== Scroll Back to Top Js Start ======================
    var progressPath = document.querySelector(".progress-wrap path");
    var pathLength = progressPath.getTotalLength();
    progressPath.style.transition = progressPath.style.WebkitTransition =
      "none";
    progressPath.style.strokeDasharray = pathLength + " " + pathLength;
    progressPath.style.strokeDashoffset = pathLength;
    progressPath.getBoundingClientRect();
    progressPath.style.transition = progressPath.style.WebkitTransition =
      "stroke-dashoffset 10ms linear";
    var updateProgress = function () {
      var scroll = $(window).scrollTop();
      var height = $(document).height() - $(window).height();
      var progress = pathLength - (scroll * pathLength) / height;
      progressPath.style.strokeDashoffset = progress;
    };
    updateProgress();
    $(window).scroll(updateProgress);
    var offset = 50;
    var duration = 550;
    jQuery(window).on("scroll", function () {
      if (jQuery(this).scrollTop() > offset) {
        jQuery(".progress-wrap").addClass("active-progress");
      } else {
        jQuery(".progress-wrap").removeClass("active-progress");
      }
    });
    jQuery(".progress-wrap").on("click", function (event) {
      event.preventDefault();
      jQuery("html, body").animate({ scrollTop: 0 }, duration);
      return false;
    });
    // ===================== Scroll Back to Top Js End ======================

    // ========================== add active class to navbar menu current page Js Start =====================
    function dynamicActiveMenuClass(selector) {
      let FileName = window.location.pathname.split("/").reverse()[0];

      // If we are at the root path ("/" or no file name), keep the activePage class on the Home item
      if (FileName === "" || FileName === "index.html") {
        // Keep the activePage class on the Home link
        selector
          .find("li.nav-menu__item.has-submenu")
          .eq(0)
          .addClass("activePage");
      } else {
        // Remove activePage class from all items first
        selector.find("li").removeClass("activePage");

        // Add activePage class to the correct li based on the current URL
        selector.find("li").each(function () {
          let anchor = $(this).find("a");
          if ($(anchor).attr("href") == FileName) {
            $(this).addClass("activePage");
          }
        });

        // If any li has activePage element, add class to its parent li
        selector.children("li").each(function () {
          if ($(this).find(".activePage").length) {
            $(this).addClass("activePage");
          }
        });
      }
    }

    if ($("ul").length) {
      dynamicActiveMenuClass($("ul"));
    }
    // ========================== add active class to navbar menu current page Js End =====================

    // ========================== Settings Panel Js Start =====================
    $(".settings-button").on("click", function () {
      $(".settings-panel").toggleClass("active");
      $(this).toggleClass("active");
    });

    $(document).on(
      "click",
      ".settings-panel__buttons .settings-panel__button",
      function () {
        $(this).siblings().removeClass("active");
        $(this).addClass("active");
      }
    );

    // Cursor start
    $(".cursor-animate").on("click", function () {
      $("body").removeClass("remove-animate-cursor");
    });

    $(".cursor-default").on("click", function () {
      $("body").addClass("remove-animate-cursor");
    });
    // Cursor end

    // Direction start
    $(".direction-ltr").on("click", function () {
      $("html").attr("dir", "ltr");
    });

    $(".direction-rtl").on("click", function () {
      $("html").attr("dir", "rtl");
    });
    // Direction end
    // ========================== Settings Panel Js End =====================

    // ********************* Toast Notification Js start *********************
    function toastMessage(messageType, messageTitle, messageText, messageIcon) {
      let $toastContainer = $("#toast-container");

      let $toast = $("<div>", {
        class: `toast-message ${messageType}`,
        html: `
      <div class="toast-message__content">
        <span class="toast-message__icon">
          <i class="${messageIcon}"></i>
        </span>
        <div class="flex-grow-1">
          <div class="d-flex align-items-start justify-content-between mb-1">
            <h6 class="toast-message__title">${messageTitle}</h6>
            <button type="button" class="toast-message__close">
              <i class="ph-bold ph-x"></i>
            </button>
          </div>
          <span class="toast-message__text">${messageText}</span>
        </div>
      </div>
      <div class="progress__bar"></div>
    `,
      });

      $toastContainer.append($toast);

      setTimeout(() => {
        $toast.addClass("active");
      }, 50);

      let totalDuration = 3500;
      let startTime = Date.now();
      let remainingTime = totalDuration;
      let toastTimeout = setTimeout(hideToast, remainingTime);

      function hideToast() {
        $toast.removeClass("active");
        setTimeout(() => {
          $toast.remove();
        }, 500);
      }

      // Remove Toast on Close Button Click
      $toast.find(".toast-message__close").on("click", function () {
        $toast.removeClass("active");
        setTimeout(() => {
          $toast.remove();
        }, 500);
      });

      // Pause Timeout on Hover
      $toast.on("mouseenter", function () {
        remainingTime -= Date.now() - startTime;
        clearTimeout(toastTimeout);
      });

      // Resume Timeout on Mouse Leave
      $toast.on("mouseleave", function () {
        startTime = Date.now();
        toastTimeout = setTimeout(hideToast, remainingTime);
      });
    }
    // ********************* Toast Notification Js End *********************

    // ========================= Form Submit Js Start ===================
    $(document).on("submit", ".form-submit", function (e) {
      e.preventDefault();

      $("input").val("");

      $("textarea").val("");

      toastMessage(
        "success",
        "Success",
        "Form submitted successfully!",
        "ph-fill ph-check-circle"
      );
    });
    // ========================= Form Submit Js End ===================

    // ========================= Search Popup Js Start ===================
    $(".search-popup__button").on("click", function () {
      $(".search-popup").addClass("active");
      $(".overlay").addClass("show-overlay");
    });
    $(".search-popup__close, .overlay").on("click", function () {
      $(".search-popup").removeClass("active");
      $(".overlay").removeClass("show-overlay");
    });
    // ========================= Search Popup Js End ===================

    // ========================= AOS Js Start ===========================
    AOS.init({
      once: false, // animation will happen every time you scroll
      offset: 0, // start animation when element enters the viewport
      anchorPlacement: "top-bottom", // when the bottom of the element hits the bottom of the screen
    });
    // ========================= AOS Js End ===========================

    // ========================= FlowMap Effect Js Start ===========================
    if($('.flowmap-deformation-wrapper').length > 0 ) {
      function flowmap_deformation(selector) {
        document.querySelectorAll(selector).forEach((box) => {
          const imgSize = [
            parseFloat(box.getAttribute("data-bg-width")),
            parseFloat(box.getAttribute("data-bg-height")),
          ];
  
          setTimeout(() => box.classList.add("active"), 300);
  
          const renderer = new ogl.Renderer({ dpr: 2 });
          const gl = renderer.gl;
          box.appendChild(gl.canvas);
  
          const vertex = `
            attribute vec2 uv;
            attribute vec2 position;
            varying vec2 vUv;
            void main() {
              vUv = uv;
              gl_Position = vec4(position, 0, 1);
            }
          `;
  
          const fragment = `
            precision highp float;
            precision highp int;
            uniform sampler2D tWater;
            uniform sampler2D tFlow;
            uniform float uTime;
            varying vec2 vUv;
            uniform vec4 res;
  
            void main() {
              vec3 flow = texture2D(tFlow, vUv).rgb;
              vec2 uv = .5 * gl_FragCoord.xy / res.xy;
              vec2 myUV = (uv - vec2(0.5)) * res.zw + vec2(0.5);
              myUV -= flow.xy * 0.105;
              vec3 tex = texture2D(tWater, myUV).rgb;
              gl_FragColor = vec4(tex, 1.0);
            }
          `;
  
          const flowmap = new ogl.Flowmap(gl, { falloff: 0.6 });
          const geometry = new ogl.Geometry(gl, {
            position: { size: 2, data: new Float32Array([-1, -1, 3, -1, -1, 3]) },
            uv: { size: 2, data: new Float32Array([0, 0, 2, 0, 0, 2]) },
          });
  
          const texture = new ogl.Texture(gl, {
            minFilter: gl.LINEAR,
            magFilter: gl.LINEAR,
          });
  
          const img = new Image();
          img.crossOrigin = "Anonymous";
          img.src = box.getAttribute("data-bg");
          img.onload = () => (texture.image = img);
  
          const program = new ogl.Program(gl, {
            vertex,
            fragment,
            uniforms: {
              uTime: { value: 0 },
              tWater: { value: texture },
              res: { value: new ogl.Vec4() },
              tFlow: flowmap.uniform,
            },
          });
  
          const mesh = new ogl.Mesh(gl, { geometry, program });
  
          let aspect = 1;
          const mouse = new ogl.Vec2(-1);
          const velocity = new ogl.Vec2();
          const lastMouse = new ogl.Vec2();
          let lastTime;
  
          function resize() {
            let a1, a2;
            const imageAspect = imgSize[1] / imgSize[0];
            const boxW = box.offsetWidth;
            const boxH = box.offsetHeight;
  
            if (boxH / boxW < imageAspect) {
              a1 = 1;
              a2 = boxH / boxW / imageAspect;
            } else {
              a1 = (boxW / boxH) * imageAspect;
              a2 = 1;
            }
  
            program.uniforms.res.value.set(boxW, boxH, a1, a2);
            renderer.setSize(boxW, boxH);
            aspect = boxW / boxH;
          }
  
          window.addEventListener("resize", resize);
          resize();
  
          const isTouchCapable = "ontouchstart" in window;
          const updateMouse = (e) => {
            if (e.changedTouches && e.changedTouches.length) {
              e = e.changedTouches[0];
            }
  
            const rect = box.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
  
            mouse.set(x / box.offsetWidth, 1 - y / box.offsetHeight);
  
            if (!lastTime) {
              lastTime = performance.now();
              lastMouse.set(x, y);
            }
  
            const deltaX = x - lastMouse.x;
            const deltaY = y - lastMouse.y;
            lastMouse.set(x, y);
  
            const time = performance.now();
            const delta = Math.max(14, time - lastTime);
            lastTime = time;
  
            velocity.x = deltaX / delta;
            velocity.y = deltaY / delta;
            velocity.needsUpdate = true;
          };
  
          if (isTouchCapable) {
            box.addEventListener("touchstart", updateMouse, false);
            box.addEventListener("touchmove", updateMouse, { passive: false });
          } else {
            box.addEventListener("mousemove", updateMouse, false);
          }
  
          requestAnimationFrame(function update(t) {
            requestAnimationFrame(update);
  
            if (!velocity.needsUpdate) {
              mouse.set(-1);
              velocity.set(0);
            }
  
            velocity.needsUpdate = false;
  
            flowmap.aspect = aspect;
            flowmap.mouse.copy(mouse);
            flowmap.velocity.lerp(velocity, velocity.len() ? 0.15 : 0.1);
            flowmap.update();
  
            program.uniforms.uTime.value = t * 0.01;
            renderer.render({ scene: mesh });
          });
        });
      }
  
      flowmap_deformation(".flowmap-deformation-wrapper");
    }
    // ========================= FlowMap Effect Js End ===========================

    // ========================= Counter Up Js End ===================
    const counterUp = window.counterUp.default;

    const callback = (entries) => {
      entries.forEach((entry) => {
        const el = entry.target;
        if (entry.isIntersecting && !el.classList.contains("is-visible")) {
          counterUp(el, {
            duration: 1500,
            delay: 16,
          });
          el.classList.add("is-visible");
        }
      });
    };
    const IO = new IntersectionObserver(callback, { threshold: 1 });

    // Banner statistics Counter
    const statisticsCounter = document.querySelectorAll(".counter");
    if (statisticsCounter.length > 0) {
      statisticsCounter.forEach((counterNumber) => {
        IO.observe(counterNumber);
      });
    }

    // performance Count
    const performanceCount = document.querySelectorAll(".counter");
    if (performanceCount.length > 0) {
      performanceCount.forEach((counterNumber) => {
        IO.observe(counterNumber);
      });
    }
    // ========================= Counter Up Js End ===================

    // ================================= Brand slider Start =========================
    var brandSlider = new Swiper(".brand-slider", {
      autoplay: {
        delay: 2000,
        disableOnInteraction: false,
      },
      autoplay: true,
      speed: 1500,
      grabCursor: true,
      loop: true,
      slidesPerView: 6,
      breakpoints: {
        300: {
          slidesPerView: 2,
        },
        575: {
          slidesPerView: 3,
        },
        768: {
          slidesPerView: 4,
        },
        992: {
          slidesPerView: 5,
        },
        1200: {
          slidesPerView: 6,
        },
      },
    });
    // ================================= Brand slider End =========================

    // ========================== Add Attribute For Bg Image Js Start ====================
    $(".background-img").css("background", function () {
      var bg = "url(" + $(this).data("background-image") + ")";
      return bg;
    });
    // ========================== Add Attribute For Bg Image Js End =====================

    // ====================== Marquee Js Start ========================
    if ($(".marquee_left").length) {
      $(".marquee_left").marquee({
        speed: 300,
        gap: 0,
        delayBeforeStart: 0,
        direction: $("html").attr("dir") === "rtl" ? "right" : "left",
        duplicated: true,
        pauseOnHover: true,
        startVisible: true,
        direction: "left",
      });
    }

    if ($(".marquee_right").length) {
      $(".marquee_right").marquee({
        speed: 300,
        gap: 0,
        delayBeforeStart: 0,
        direction: $("html").attr("dir") === "rtl" ? "right" : "left",
        duplicated: true,
        pauseOnHover: true,
        startVisible: true,
        direction: "right",
      });
    }
    // ====================== Marquee Js End ========================

    // ================================ Floating Progress js start =================================
    const progressContainers = document.querySelectorAll(".progress-container");

    function setPercentage(progressContainer) {
      const percentage =
        progressContainer.getAttribute("data-percentage") + "%";

      const progressEl = progressContainer.querySelector(".progress");
      const percentageEl = progressContainer.querySelector(".percentage");

      progressEl.style.width = percentage;
      percentageEl.innerText = percentage;
      percentageEl.style.insetInlineStart = percentage;
    }

    // Intersection Observer to trigger progress animation when section is in view
    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            // Element is in view, start the progress animation
            const progressContainer = entry.target;
            setPercentage(progressContainer);
            progressContainer
              .querySelector(".progress")
              .classList.remove("active");
            progressContainer
              .querySelector(".percentage")
              .classList.remove("active");
            observer.unobserve(progressContainer); // Stop observing once animation is triggered
          }
        });
      },
      {
        threshold: 0.5, // Adjust this value as needed (0.5 means half the section needs to be visible)
      }
    );

    // Start observing all progress containers
    progressContainers.forEach((progressContainer) => {
      observer.observe(progressContainer);
    });
    // ================================ Floating Progress js End =================================

    // ================================ Project slider js Start =================================
    var projectSlider = new Swiper(".project-slider", {
      slidesPerView: 2,
      spaceBetween: 24,
      centeredSlides: true,
      grabCursor: true,
      loop: true,
      speed: 1000,
      pagination: {
        el: ".swiper-pagination",
        clickable: true,
      },
      breakpoints: {
        0: {
          centeredSlides: false,
          slidesPerView: 1,
        },
        576: {
          centeredSlides: true,
        },
      },
    });
    // ================================ Project slider js End =================================

    // ========================= Team Section Social icons Js Start =====================
    $(document).on("click", ".show-social-icon-button", function () {
      $(this).toggleClass("active");
      $(this).closest("div").find(".show-socials-icons").toggleClass("active");
    });
    // ========================= Team Section Social icons Js End =====================

    // ================================= Brand slider Start =========================
    var testimonialsSlider = new Swiper(".testimonials-slider", {
      autoplay: {
        delay: 2000,
        disableOnInteraction: false,
      },
      autoplay: true,
      speed: 1500,
      grabCursor: true,
      loop: true,
      slidesPerView: 1,
      navigation: {
        nextEl: ".testi-button-next",
        prevEl: ".testi-button-prev",
      },
      effect: "cube",
      grabCursor: true,
      cubeEffect: {
        shadow: true,
        slideShadows: true,
        shadowOffset: 20,
        shadowScale: 0.94,
      },
    });
    // ================================= Brand slider End =========================

    // ========================= magnific Popup Js Start =====================
    $(".play-button").magnificPopup({
      type: "iframe",
      removalDelay: 300,
      mainClass: "mfp-fade",
    });
    // ========================= magnific Popup Js End =====================

    // ********************* Animate Background *********************
    function moveBorder(wrapper) {
      var $activeTab = $(wrapper).find(".active").parent("li");
      var position = $activeTab.position();
      var width = $activeTab.width();

      $(wrapper)
        .find(".animate-background")
        .css({
          left: position.left + "px",
          width: width + "px",
        });
    }

    // Move Background on page load for each tab group
    $(".animate-background-wrapper").each(function () {
      moveBorder(this);
    });

    // Move Background on tab click
    $(".animate-background-wrapper .nav-link").on("click", function () {
      var wrapper = $(this).closest(".animate-background-wrapper");
      wrapper.find(".nav-link").removeClass("active");
      $(this).addClass("active");
      moveBorder(wrapper);
    });
    // ========================= Active Tab Background animation Js End ===================

    // ================================ Project Two slider js Start =================================
    var projectTwoSlider = new Swiper(".project-two-slider", {
      slidesPerView: 4,
      spaceBetween: 24,
      centeredSlides: true,
      grabCursor: true,
      loop: true,
      speed: 1000,
      pagination: {
        el: ".swiper-pagination",
        clickable: true,
      },
      breakpoints: {
        0: {
          centeredSlides: false,
          slidesPerView: 1,
        },
        576: {
          centeredSlides: true,
          slidesPerView: 2,
        },
        991: {
          slidesPerView: 2,
        },
        1200: {
          slidesPerView: 3,
        },
        1365: {
          slidesPerView: 4,
        },
      },
    });
    // ================================ Project Two slider js End =================================

    // ================================ Testimonials Two slider js Start =================================
    var testimonialsTwoSlider = new Swiper(".testimonials-two-slider", {
      slidesPerView: 1,
      spaceBetween: 24,
      centeredSlides: true,
      grabCursor: true,
      loop: true,
      speed: 1000,
      pagination: {
        el: ".swiper-pagination",
        clickable: true,
      },
      navigation: {
        nextEl: ".testimonials-two-button-next",
        prevEl: ".testimonials-two-button-prev",
      },
    });
    // ================================ Testimonials Two slider js End =================================

    // ================================ Parallax js Start =================================
    $(".parallax-window").parallax();
    // ================================ Parallax js End =================================

    // ======================= Statistics Three Js Start ==================

    // ================================= Brand slider Start =========================
    var statisticsBrandSlider = new Swiper(".statistics-brand-slider", {
      autoplay: {
        delay: 2000,
        disableOnInteraction: false,
      },
      autoplay: true,
      speed: 1500,
      grabCursor: true,
      loop: true,
      slidesPerView: 4,
      breakpoints: {
        425: {
          slidesPerView: 3,
        },
        575: {
          slidesPerView: 4,
        },
      },
    });
    // ================================= Brand slider End =========================

    // ================================ Testimonials Two slider js Start =================================
    var testimonialsThreeSlider = new Swiper(".testimonials-three-slider", {
      slidesPerView: 1,
      spaceBetween: 24,
      centeredSlides: true,
      grabCursor: true,
      loop: true,
      speed: 1000,
      pagination: {
        el: ".swiper-pagination",
        clickable: true,
      },
      effect: "cube",
      cubeEffect: {
        shadow: true,
        slideShadows: true,
        shadowOffset: 20,
        shadowScale: 0.94,
      },
      navigation: {
        nextEl: ".testimonials-three-next",
        prevEl: ".testimonials-three-prev",
      },
    });
    // ================================ Testimonials Two slider js End =================================

    // ================================ Service Four slider js Start =================================
    var serviceFourThumbsSlider = new Swiper(".service-four-thumbs-slider", {
      slidesPerView: 1,
      effect: "fade",
      loop: true,
      watchSlidesProgress: true,
    });

    var serviceFourItemSlider = new Swiper(".service-four-item-slider", {
      spaceBetween: 10,
      slidesPerView: 1,
      loop: true,
      freeMode: true,
      watchSlidesProgress: true,
      pagination: {
        el: ".service-four-pagination",
        type: "fraction",
      },
      navigation: {
        nextEl: ".service-four-next",
        prevEl: ".service-four-prev",
      },
      thumbs: {
        swiper: serviceFourThumbsSlider,
      },
    });
    // ================================ Service Four slider js End =================================

    // ================================ Testimonials four slider js Start =================================
    var testimonialsFourSlider = new Swiper(".testimonials-four-slider", {
      slidesPerView: 2,
      spaceBetween: 24,
      grabCursor: true,
      loop: true,
      speed: 1000,
      pagination: {
        el: ".swiper-pagination",
        clickable: true,
      },
      navigation: {
        nextEl: ".testimonials-four-next",
        prevEl: ".testimonials-four-prev",
      },
      breakpoints: {
        300: {
          slidesPerView: 1,
        },
        1199: {
          slidesPerView: 2,
        },
      },
    });
    // ================================ Testimonials four slider js End =================================

    // ================================ Testimonials new slider js Start =================================
    var testimonialsNewSlider = new Swiper(".testimonials-new-slider", {
      slidesPerView: 1,
      spaceBetween: 24,
      grabCursor: true,
      loop: true,
      speed: 1000,
      pagination: {
        el: ".swiper-pagination",
        clickable: true,
      },
      navigation: {
        nextEl: ".testimonials-new-next",
        prevEl: ".testimonials-new-prev",
      },
      breakpoints: {
        992: {
          slidesPerView: 1,
        },
        1300: {
          slidesPerView: 2,
        },
      },
    });
    // ================================ Testimonials new slider js End =================================

    var testiNewTwoThumbsSlider = new Swiper(
      ".testimonials-new-two-thumbs-slider",
      {
        spaceBetween: 0,
        slidesPerView: 3,
        watchSlidesProgress: true,
        centeredSlides: true,
        grabCursor: true,
        loop: true,
        speed: 1000,
      }
    );
    var testiNewTwoContentSlider = new Swiper(
      ".testimonials-new-two-content-slider",
      {
        spaceBetween: 0,
        loop: true,
        grabCursor: true,
        navigation: {
          nextEl: ".testimonials-new-two-content-next",
          prevEl: ".testimonials-new-two-content-prev",
        },
        thumbs: {
          swiper: testiNewTwoThumbsSlider,
        },
      }
    );
  });
  // ==========================================
  //      End Document Ready function
  // ==========================================

  // ========================= Preloader Js Start =====================
  $(window).on("load", function () {
    $(".loader-mask").fadeOut();
  });
  // ========================= Preloader Js End=====================

  // ========================= Header Sticky Js Start ==============
  $(window).on("scroll", function () {
    if ($(window).scrollTop() >= 260) {
      $(".header").addClass("fixed-header");
    } else {
      $(".header").removeClass("fixed-header");
    }
  });
  // ========================= Header Sticky Js End===================
})(jQuery);
