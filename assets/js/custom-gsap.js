/* **************************************************************************** 
                          Custom GSAP js start 
****************************************************************************  */

var tl = gsap.timeline();
gsap.registerPlugin(ScrollTrigger, SplitText);

// **************************** Nav Menu js Start ****************************
// let mm = gsap.matchMedia();

// mm.add("(min-width: 992px)", () => {
//   gsap.from('.nav-menu__item', {
//     opacity: 0,
//     duration: .4,
//     y: -20,
//     stagger: .12,
//   });
// });
// **************************** Nav Menu js End ****************************

// **************************** ScrollSmoother js start ****************************
gsap.registerPlugin(ScrollTrigger, ScrollSmoother);

const smoother = ScrollSmoother.create({
  wrapper: "#smooth-wrapper",
  content: "#smooth-content",
  smooth: 1.35,
  effects: true,
  smoothTouch: 0.1,
  ignoreMobileResize: false,
});
// **************************** ScrollSmoother js End ****************************

// **************************** Custom Cursor Js Start ****************************
var body = document.body;
var cursor = document.querySelector(".cursor");
var dot = document.querySelector(".dot");
var cursorSmalls = document.querySelectorAll(".cursor-small");
var cursorBigs = document.querySelectorAll(".cursor-big");

body.addEventListener("mousemove", function (event) {
  gsap.to(cursor, {
    x: event.x,
    y: event.y,
    duration: 2,
    delay: 0.1,
    visibility: "visible",
    ease: "expo.out",
  });
});

body.addEventListener("mousemove", function (event) {
  gsap.to(dot, {
    x: event.x,
    y: event.y,
    duration: 1,
    visibility: "visible",
    ease: "expo.out",
  });
});

// Small Cursor
cursorSmalls.forEach((cursorSmall) => {
  cursorSmall.addEventListener("mouseenter", function () {
    gsap.to(dot, {
      scale: 8,
      backgroundColor: "#fff",
    });
    gsap.to(cursor, {
      visibility: "hidden",
      opacity: 0,
    });
  });

  cursorSmall.addEventListener("mouseleave", function () {
    gsap.to(dot, {
      scale: 1,
      backgroundColor: "#fff",
    });
    gsap.to(cursor, {
      visibility: "visible",
      opacity: 1,
    });
  });
});

// Big Cursor
cursorBigs.forEach((cursorBig) => {
  cursorBig.addEventListener("mouseenter", function () {
    gsap.to(dot, {
      scale: 30,
      backgroundColor: "#fff",
    });
    gsap.to(cursor, {
      visibility: "hidden",
      opacity: 0,
    });
  });

  cursorBig.addEventListener("mouseleave", function () {
    gsap.to(dot, {
      scale: 1,
      backgroundColor: "#fff",
    });
    gsap.to(cursor, {
      visibility: "visible",
      opacity: 1,
    });
  });
});
// **************************** Custom Cursor Js End ****************************

// **************************** Hover Cursor Js Start ****************************
const viewCursor = document.querySelector(".view-cursor");
const viewCursorShows = document.querySelectorAll(".view-cursor-show");

// Move .view-cursor with mouse
document.body.addEventListener("mousemove", function (event) {
  gsap.to(viewCursor, {
    x: event.clientX + 20, // offset right
    y: event.clientY + 20, // offset down
    duration: 0.3,
    ease: "expo.out",
  });
});

// Loop through all .view-cursor-show
viewCursorShows.forEach((item) => {
  item.addEventListener("mouseenter", function () {
    gsap.to(viewCursor, {
      autoAlpha: 1, // opacity + visibility
      scale: 1,
    });
    gsap.to(dot, {
      scale: 0,
    });
    gsap.to(cursor, {
      scale: 0,
    });
  });

  item.addEventListener("mouseleave", function () {
    gsap.to(viewCursor, {
      autoAlpha: 0,
      scale: 0,
    });
    gsap.to(dot, {
      scale: 1,
    });
    gsap.to(cursor, {
      scale: 1,
    });
  });
});
// **************************** Hover Cursor Js End ****************************

// **************************** Mobile Menu js Start ****************************
var mmm = gsap.matchMedia();
var mtl = gsap.timeline({ paused: true });

const toggleMobileMenu = document.querySelector(".toggle-mobileMenu");
const closeButton = document.querySelector(".close-button");
const mobileSideOverlay = document.querySelector(".side-overlay");

mmm.add("(max-width: 991px)", () => {
  mtl.to(".side-overlay", {
    opacity: 1,
    visibility: "visible",
    duration: 0.15,
  });

  mtl.to(".mobile-menu", {
    x: 0,
    delay: 0.2,
    duration: 0.2,
  });

  mtl.from(".nav-menu__item", {
    opacity: 0,
    duration: 0.2,
    y: -60,
    stagger: 0.08,
  });

  toggleMobileMenu.addEventListener("click", function () {
    mtl.play();
    document.body.style.overflow = "hidden";
  });

  closeButton.addEventListener("click", function () {
    mtl.reverse();
    document.body.style.overflow = "";
  });

  mobileSideOverlay.addEventListener("click", function () {
    mtl.reverse();
    document.body.style.overflow = "";
  });
});
// **************************** Mobile Menu js End ****************************

// **************************** Custom Split text Js Start ****************************
if ($(".splitTextStyleOne").length) {
  let staggerAmount = 0.03,
    translateXValue = 20,
    delayValue = 0.1,
    easeType = "power2.out",
    animatedTextElements = document.querySelectorAll(".splitTextStyleOne");

  animatedTextElements.forEach((element) => {
    let animationSplitText = new SplitText(element, { type: "chars, words" });
    gsap.from(animationSplitText.chars, {
      duration: 1,
      delay: delayValue,
      x: translateXValue,
      autoAlpha: 0,
      stagger: staggerAmount,
      ease: easeType,
      scrollTrigger: { trigger: element, start: "top 95%" },
    });
  });
}

if ($(".splitTextStyleTwo").length) {
  let animatedTextElements = document.querySelectorAll(".splitTextStyleTwo");

  animatedTextElements.forEach((element) => {
    //Reset if needed
    if (element.animation) {
      element.animation.progress(1).kill();
      element.split.revert();
    }

    element.split = new SplitText(element, {
      type: "lines,words,chars",
      linesClass: "split-line",
    });
    gsap.set(element, { perspective: 400 });

    gsap.set(element.split.chars, {
      opacity: 0,
      x: "50",
    });

    element.animation = gsap.to(element.split.chars, {
      scrollTrigger: { trigger: element, start: "top 90%" },
      x: "0",
      y: "0",
      rotateX: "0",
      opacity: 1,
      duration: 1,
      ease: Back.easeOut,
      stagger: 0.02,
    });
  });
}

if ($(".splitTextStyleThree").length) {
  let staggerAmount = 0.05,
    translateXValue = 0,
    delayValue = 0.5,
    animatedTextElements = document.querySelectorAll(".splitTextStyleThree");

  animatedTextElements.forEach((element) => {
    let animationSplitText = new SplitText(element, { type: "chars, words" });
    gsap.from(animationSplitText.words, {
      duration: 1,
      delay: delayValue,
      x: 20,
      autoAlpha: 0,
      stagger: staggerAmount,
      scrollTrigger: { trigger: element, start: "top 85%" },
    });
  });
}
// **************************** Custom Split text Js End ****************************

// **************************** Position Aware button hover js start ****************************
class Button {
  constructor(buttonElement) {
    this.block = buttonElement;
    this.init();
    this.initEvents();
  }

  init() {
    const el = gsap.utils.selector(this.block);

    this.DOM = {
      button: this.block,
      flair: el(".button__flair"),
    };

    this.xSet = gsap.quickSetter(this.DOM.flair, "xPercent");
    this.ySet = gsap.quickSetter(this.DOM.flair, "yPercent");
  }

  getXY(e) {
    const { left, top, width, height } =
      this.DOM.button.getBoundingClientRect();

    const xTransformer = gsap.utils.pipe(
      gsap.utils.mapRange(0, width, 0, 100),
      gsap.utils.clamp(0, 100)
    );

    const yTransformer = gsap.utils.pipe(
      gsap.utils.mapRange(0, height, 0, 100),
      gsap.utils.clamp(0, 100)
    );

    return {
      x: xTransformer(e.clientX - left),
      y: yTransformer(e.clientY - top),
    };
  }

  initEvents() {
    this.DOM.button.addEventListener("mouseenter", (e) => {
      const { x, y } = this.getXY(e);

      this.xSet(x);
      this.ySet(y);

      gsap.to(this.DOM.flair, {
        scale: 1,
        duration: 0.9,
        ease: "power2.out",
      });
    });

    this.DOM.button.addEventListener("mouseleave", (e) => {
      const { x, y } = this.getXY(e);

      gsap.killTweensOf(this.DOM.flair);

      gsap.to(this.DOM.flair, {
        xPercent: x > 90 ? x + 20 : x < 10 ? x - 20 : x,
        yPercent: y > 90 ? y + 20 : y < 10 ? y - 20 : y,
        scale: 0,
        duration: 0.9,
        ease: "power2.out",
      });
    });

    this.DOM.button.addEventListener("mousemove", (e) => {
      const { x, y } = this.getXY(e);

      gsap.to(this.DOM.flair, {
        xPercent: x,
        yPercent: y,
        duration: 0.9,
        ease: "power2",
      });
    });
  }
}

const buttonElements = document.querySelectorAll('[data-block="button"]');

buttonElements.forEach((buttonElement) => {
  new Button(buttonElement);
});
// **************************** Position Aware button hover js End ****************************

// **************************** split Reveal js Start ****************************
if ($(".split-reveal").length) {
  let revealContainers = document.querySelectorAll(".split-reveal");

  revealContainers.forEach((container) => {
    let splitElement = container.querySelector(".split-reveal-element");

    let tl = gsap.timeline({
      scrollTrigger: {
        trigger: container,
        toggleActions: "play none none none",
      },
    });

    tl.set(container, {
      autoAlpha: 1,
    });

    tl.from(container, {
      duration: 1,
      xPercent: -100,
      ease: Power2.out,
    });

    tl.from(splitElement, {
      duration: 1,
      xPercent: 100,
      scale: 1,
      delay: -1,
      ease: Power2.out,
    });
  });
}
// **************************** split Reveal js End ****************************

// **************************** Hover Parallax animation js Start ****************************
var hoverBtns = gsap.utils.toArray(".hover-parallax-wrapper");
const hoverBtnItem = gsap.utils.toArray(".hover-parallax-item");
hoverBtns.forEach((btn, i) => {
  $(btn).mousemove(function (e) {
    callParallax(e);
  });

  function callParallax(e) {
    parallaxIt(e, hoverBtnItem[i], 60);
  }

  function parallaxIt(e, target, movement) {
    var $this = $(btn);
    var relX = e.pageX - $this.offset().left;
    var relY = e.pageY - $this.offset().top;

    gsap.to(target, 1, {
      x: ((relX - $this.width() / 2) / $this.width()) * movement,
      y: ((relY - $this.height() / 2) / $this.height()) * movement,
      ease: Power2.easeOut,
    });
  }
  $(btn).mouseleave(function (e) {
    gsap.to(hoverBtnItem[i], 1, {
      x: 0,
      y: 0,
      ease: Power2.easeOut,
    });
  });
});
// **************************** Hover Parallax animation js End ****************************

// **************************** Team Item animation start ****************************
if ($(".team-item").length) {
  window.addEventListener("load", function () {
    let tlTwo = gsap.timeline({
      scrollTrigger: {
        trigger: ".team-item-wrapper",
        start: "top 80%",
        toggleActions: "play none none reverse",
        ease: "power3.out",
        stagger: 0.2,
      },
    });

    tlTwo.addLabel("start").from(".team-item", {
      scale: 0.8,
      rotation: 20,
      autoAlpha: 0,
      duration: 1,
    });
  });
}
// **************************** Team Item animation end ****************************

// **************************** Choose Us Two Image reveal js start ****************************
if ($(".choose-us-big-image").length) {
  gsap.registerPlugin(ScrollTrigger);

  window.addEventListener("load", () => {
    gsap
      .timeline({
        scrollTrigger: {
          trigger: ".choose-us-two-big-image-wrapper",
          start: "top 20%",
          end: "+=150%",
          pin: true,
          scrub: 0.5,
          markers: false,
        },
      })
      .fromTo(
        ".choose-us-big-image",
        {
          clipPath: "inset(0% 25% 0% 25%)", // Start with center 50%
        },
        {
          clipPath: "inset(0% 0% 0% 0%)", // Full reveal
        }
      );
  });
}
// **************************** Choose Us Two Image reveal js end ****************************

// **************************** Service Two js start ****************************
document.addEventListener("DOMContentLoaded", function () {
  if ($(".service-two-item").length) {
    if (window.innerWidth > 1200) {
      const items = document.querySelectorAll(".service-two-item");

      const advanced = gsap.timeline({
        scrollTrigger: {
          trigger: ".service-two-item-wrapper",
          start: "top 60%",
          toggleActions: "play none none reverse",
          markers: false,
        },
        defaults: {
          ease: "ease1",
          duration: 1,
        },
      });
      advanced
        .from(items[0], {
          xPercent: 100,
          rotate: 8,
        })
        .from(
          items[1],
          {
            xPercent: 30,
            rotate: 4.13,
          },
          "<"
        )
        .from(
          items[2],
          {
            xPercent: -30,
            rotate: -6.42,
          },
          "<"
        )
        .from(
          items[3],
          {
            xPercent: -60,
            rotate: -12.15,
          },
          "<"
        );
    }
  }
});
// **************************** Service Two js end ****************************

// **************************** Pricing plan js start ****************************
document.addEventListener("DOMContentLoaded", function () {
  if ($(".pricing-plan-two-item").length) {
    if (window.innerWidth > 1200) {
      const items = document.querySelectorAll(".pricing-plan-two-item");

      const advanced = gsap.timeline({
        scrollTrigger: {
          trigger: ".pricing-plan-two-wrapper",
          start: "top 60%",
          toggleActions: "play none none reverse",
          markers: false,
        },
        defaults: {
          ease: "ease1",
          duration: 1,
        },
      });

      advanced
        .from(items[0], {
          xPercent: 100,
          rotate: 2,
        })
        .from(
          items[1],
          {
            xPercent: 30,
            zIndex: 2,
            rotate: 1.13,
          },
          "<"
        )
        .from(
          items[2],
          {
            xPercent: -30,
            rotate: -2.42,
          },
          "<"
        );
    }
  }
});
// **************************** Pricing plan js end ****************************

// **************************** Banner Two js Start ****************************
gsap.registerPlugin(ScrollTrigger);

document.addEventListener("DOMContentLoaded", function () {
  if ($(".animated-element").length) {
    if (window.innerWidth > 575) {
      const items = document.querySelectorAll(".animated-element");

      const elements = gsap.timeline({
        scrollTrigger: {
          trigger: ".animated-element-wrapper",
          start: "top 60%",
          toggleActions: "play none none reverse",
          markers: false,
        },
        defaults: {
          ease: "ease1",
          duration: 1.5,
        },
      });
      elements
        .from(items[0], {
          xPercent: 500,
          yPercent: 500,
          scale: 0.6,
          filter: "blur(10px)",
          rotate: 8,
        })
        .from(
          items[1],
          {
            xPercent: -500,
            yPercent: 500,
            scale: 0.6,
            filter: "blur(10px)",
            rotate: 4.13,
          },
          "<"
        )
        .from(
          items[2],
          {
            xPercent: 500,
            yPercent: -500,
            scale: 0.6,
            filter: "blur(10px)",
          },
          "<"
        )
        .from(
          items[3],
          {
            xPercent: -500,
            yPercent: -500,
            scale: 0.6,
            filter: "blur(10px)",
            rotate: -12.15,
          },
          "<"
        );
    }
  }

  // Banner Two image
  if ($(".banner-two-image").length) {
    const blurImage = gsap.timeline({
      scrollTrigger: {
        trigger: ".banner-two",
        start: "top 60%",
        toggleActions: "play none none reverse",
        markers: false,
      },
      defaults: {
        ease: "ease1",
        duration: 3,
      },
    });

    blurImage.from(".banner-two-image", {
      scale: 0.85,
      filter: "blur(10px)",
    });
  }
});
// **************************** Banner Two js End ****************************

// **************************** Blog Three js Start ****************************
gsap.utils.toArray(".side-sticky").forEach((sticky) => {
  if (window.innerWidth > 991) {
    ScrollTrigger.create({
      trigger: sticky,
      start: "top top+=160",
      end: "53%",
      pin: true,
      scrub: true,
    });
  }
});
// **************************** Blog Three js End ****************************

// **************************** Text hover animation js End ****************************
const headings = document.querySelectorAll(".text-hover-animation-scale");
headings.forEach((heading) => {
  const textNodes = [];

  heading.childNodes.forEach((node) => {
    if (node.nodeType === Node.TEXT_NODE) {
      node.textContent.split(" ").forEach((word, index, array) => {
        const wordSpan = document.createElement("span");
        wordSpan.classList.add("top-word-span");
        word.split("").forEach((letter) => {
          const letterSpan = document.createElement("span");
          letterSpan.classList.add("top-text-span");
          letterSpan.textContent = letter;
          wordSpan.appendChild(letterSpan);
        });
        textNodes.push(wordSpan);
        if (index < array.length - 1) {
          textNodes.push(document.createTextNode(" "));
        }
      });
    } else if (node.nodeType === Node.ELEMENT_NODE) {
      textNodes.push(node.cloneNode(true));
    }
  });

  heading.innerHTML = "";
  textNodes.forEach((node) => heading.appendChild(node));

  const letters = heading.querySelectorAll(".top-text-span");
  letters.forEach((letter) => {
    $(letter).on("mouseenter", () => {
      gsap.to(letter, {
        scaleY: 1.3,
        y: "-14%",
        duration: 0.2,
        ease: "sine",
      });
    });

    $(letter).on("mouseleave", () => {
      gsap.to(letter, {
        scaleY: 1,
        y: "0%",
        duration: 0.2,
        ease: "sine",
      });
    });
  });
});
// **************************** Text hover animation js End ****************************

// **************************** Scale Image up scale js End ****************************
if ($(".scale-up-wrapper").length > 0) {
  document.querySelectorAll(".scale-up-wrapper").forEach((section) => {
    const img = section.querySelector(".scale-up__img");
    if (!img) return; // skip if no target found

    let tl = gsap.timeline({
      scrollTrigger: {
        trigger: section,
        start: "top 95%",
        end: "bottom 10%",
        scrub: 1,
        markers: false,
      },
    });

    tl.to(section.querySelector(".scale-up__img"), {
      scale: 1.1,
    }).to(section.querySelector(".scale-up__img"), {
      scale: 1.15,
    });
  });
}
// **************************** Scale Image up scale js End ****************************

// **************************** Text To right animation js start ****************************
if ($(".text-to-left-top").length > 0) {
  gsap
    .timeline({
      scrollTrigger: {
        trigger: ".text-to-left-top",
        start: "top 40%",
        end: "bottom 20%",
        scrub: true,
        invalidateOnRefresh: true,
        markers: false,
      },
    })
    .to(".text-to-left-top", {
      x: "-10%",
    });
}

if ($(".text-to-left").length > 0) {
  gsap
    .timeline({
      scrollTrigger: {
        trigger: ".text-to-left",
        start: "top 95%",
        end: "bottom 5%",
        scrub: true,
        invalidateOnRefresh: true,
      },
    })
    .to(".text-to-left", {
      x: "-35%",
    });
}

if ($(".text-to-right").length > 0) {
  gsap
    .timeline({
      scrollTrigger: {
        trigger: ".text-to-right",
        start: "top 95%",
        end: "bottom 5%",
        scrub: true,
        invalidateOnRefresh: true,
      },
    })
    .to(".text-to-right", {
      x: "35%",
    });
}

if ($(".text-to-bottom-bottom").length > 0) {
  gsap
    .timeline({
      scrollTrigger: {
        trigger: ".text-to-bottom-bottom",
        start: "top 20%",
        end: "bottom 5%",
        scrub: true,
        invalidateOnRefresh: true,
        markers: false,
      },
    })
    .to(".text-to-bottom-bottom", {
      y: "80%",
      x: "-100px",
      rotate: -8,
      scale: 1.2,
    });
}
// **************************** Text To right animation js End ****************************

// **************************** Sticky sidebar js start ****************************
let mmSidebar = gsap.matchMedia();
const metaElement = document.querySelector(".sidebar-sticky");

mmSidebar.add("(min-width: 1401px)", () => {
  if (!metaElement) return; // safety check

  gsap.to(metaElement, {
    scrollTrigger: {
      trigger: metaElement,
      start: "top 240px",
      endTrigger: ".sidebar-sticky-wrapper",
      end: "bottom 60%",
      pin: true,
      pinSpacing: false,
      markers: false,
      invalidateOnRefresh: true,
    },
  });
});
// **************************** Sticky sidebar js End ****************************

// **************************** Work Process js Start ****************************
if ($(".working-process-new-two").length > 0) {
  gsap
    .timeline({
      scrollTrigger: {
        trigger: ".working-process-new-two",
        pin: true,
        start: "top 0%",
        end: "bottom 0%",
        scrub: 1.5,
        invalidateOnRefresh: true,
        markers: false,
      },
    })
    .from(".card-one", {
      rotate: 10,
      zIndex: 3,
    })
    .to(".card-one", {
      y: "10%",
      scale: 1.2,
    })
    .to(".card-one", {
      scale: 1.4,
    })
    .to(".card-one", {
      opacity: 0,
    })
    .from(".card-two", {
      rotate: -10,
      zIndex: 2,
    })
    .to(".card-two", {
      scale: 1.1,
      y: 100,
    })
    .to(".card-two", {
      scale: 1.3,
    })
    .to(".card-two", {
      scale: 1.3,
    })
    .to(".card-two", {
      opacity: 0,
    })
    .to(".card-three", {
      y: 60,
    })
    .to(".card-three", {
      scale: 1.2,
    });
}
// **************************** Work Process js End ****************************

// **************************** Item Animation Up Down js Start ****************************
mmm.add("(min-width: 576px)", () => {
  if ($(".item-animation-down").length > 0) {
    gsap
      .timeline({
        scrollTrigger: {
          trigger: ".item-animation-section",
          start: "top 95%",
          end: "bottom 5%",
          scrub: true,
          invalidateOnRefresh: true,
          markers: false,
        },
      })
      .to(".item-animation-down", {
        y: "120px",
      });
  }
});
// **************************** Item Animation Up Down js End ****************************


// **************************** Title Animation js start ****************************
 if (document.querySelector('.animated-title')) {
    gsap.set('.animated-title', {
        opacity: 0
    });
    gsap.to('.animated-title', {
        opacity: 1,
        duration: 1,
        ease: 'power1.out',
        scrollTrigger: {
            trigger: '.animated-title',
            start: 'top 80%',
            toggleActions: 'play none none none',
            once: true
        },
        onComplete: runAnimation
    });
    function runAnimation() {
        const mySplitText = new SplitText(".animated-title", {
            type: "words,chars"
        });
        const chars = mySplitText.chars;
        const cta = gsap.timeline({ repeat: -1, delay: 0.5 });
        cta.to(chars, {
            duration: 0.5,
            scaleY: 0.6,
            ease: "power1.out",
            stagger: 0.04,
            transformOrigin: 'center bottom'
        });
        cta.to(chars, {
            yPercent: -20,
            ease: "elastic.out(1, 0.3)",
            stagger: 0.03,
            duration: 0.8
        }, 0.5);
        cta.to(chars, {
            scaleY: 1,
            ease: "elastic.out(1, 0.3)",
            stagger: 0.03,
            duration: 1.5
        }, 0.5);
        cta.to(chars, {
            onStart: () => {
                chars.forEach(char => char.classList.add('char-animated'));
            }
        }, 0.5);
        cta.to(chars, {
            yPercent: 0,
            ease: "back.out(1.7)",
            stagger: 0.03,
            duration: 0.8
        }, 0.7);
        cta.to(chars, {
            onStart: () => {
                chars.forEach(char => char.classList.remove('char-animated'));
            }
        });
    }
}
// **************************** Title Animation js End ****************************




// /* ****************************************************************************
//                           Custom GSAP js start
// ****************************************************************************  */
