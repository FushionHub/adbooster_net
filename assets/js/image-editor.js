/* ═══════════════════════════════════════════════════════════════════
   Canva-style design editor — Adsbooster Image Studio.

   Loaded by ugc-generator.php on the image_editor tab. Reads its
   configuration from the #cvEditor data attributes so this file can stay
   static and cacheable.

   Model: design elements live in ED.els and are rendered as absolutely
   positioned DOM nodes (easy hit-testing, native text editing). Export
   redraws the same model onto a canvas at full resolution, so what you
   download always matches what you see.
   ═══════════════════════════════════════════════════════════════════ */
(function () {
  'use strict';

  var root = document.getElementById('cvEditor');
  if (!root) return;

  var SEED_HEADLINE = root.dataset.seedHeadline || '';
  var SEED_BADGE    = root.dataset.seedBadge || '';

  var ED = {
    W: 1024, H: 1024,
    zoom: 1,
    bg: { filters: { brightness: 100, contrast: 100, saturation: 100, sepia: 0, grayscale: 0, blur: 0 } },
    els: [],
    sel: null,
    seq: 0,
    undo: [],
    redo: []
  };

  var $ = function (id) { return document.getElementById(id); };
  var artboard  = $('cvArtboard');
  var viewport  = $('cvViewport');
  var drawer    = $('cvDrawer');
  var inspector = $('cvInspector');

  function uid() { return 'e' + (++ED.seq) + '_' + Date.now().toString(36); }
  function getEl(id) {
    for (var i = 0; i < ED.els.length; i++) if (ED.els[i].id === id) return ED.els[i];
    return null;
  }
  function selEl() { return ED.sel ? getEl(ED.sel) : null; }

  /* ── Undo / redo ───────────────────────────────────────────── */
  function pack() { return JSON.stringify({ els: ED.els, bg: ED.bg, sel: ED.sel }); }
  function snapshot() {
    ED.undo.push(pack());
    if (ED.undo.length > 60) ED.undo.shift();
    ED.redo.length = 0;
    refreshHistoryButtons();
  }
  function restore(str) {
    var s = JSON.parse(str);
    ED.els = s.els; ED.bg = s.bg; ED.sel = s.sel;
    render(); refreshLayers(); refreshInspector();
  }
  function doUndo() {
    if (!ED.undo.length) return;
    ED.redo.push(pack());
    restore(ED.undo.pop());
    refreshHistoryButtons();
  }
  function doRedo() {
    if (!ED.redo.length) return;
    ED.undo.push(pack());
    restore(ED.redo.pop());
    refreshHistoryButtons();
  }
  function refreshHistoryButtons() {
    $('cvUndo').disabled     = !ED.undo.length;
    $('cvRedo').disabled     = !ED.redo.length;
    $('cvDelete').disabled   = !ED.sel;
    $('cvDuplicate').disabled= !ED.sel;
  }

  /* ═══════════════════════════════════════════════════════════
     Templates
     ═══════════════════════════════════════════════════════════ */
  var TEMPLATES = [
    { id: 'promo', name: 'Bold promo', swatch: 'linear-gradient(135deg,#c8ff00,#7ed957)',
      build: function (W, H) {
        var s = Math.min(W, H);
        return [
          { type: 'rect',  x: 0, y: H - s * 0.30, w: W, h: s * 0.30, color: 'rgba(5,8,15,0.78)', radius: 0 },
          { type: 'badge', x: s * 0.05, y: s * 0.05, w: Math.round(s * 0.46), h: Math.round(s * 0.085),
            text: '🔥 LIMITED TIME', color: '#c8ff00', textColor: '#0b111c', size: Math.round(s * 0.032) },
          { type: 'text',  x: s * 0.05, y: H - s * 0.255, w: W - s * 0.10, text: 'Your headline here',
            font: 'Inter', size: Math.round(s * 0.075), weight: 800, color: '#ffffff', align: 'left' }
        ];
      } },
    { id: 'sale', name: 'Big sale', swatch: 'linear-gradient(135deg,#f43f5e,#fb923c)',
      build: function (W, H) {
        var s = Math.min(W, H);
        return [
          { type: 'rect', x: W * 0.06, y: H * 0.06, w: W * 0.44, h: H * 0.26,
            color: '#f43f5e', radius: Math.round(s * 0.04), opacity: 94 },
          { type: 'text', x: W * 0.09, y: H * 0.085, w: W * 0.38, text: '50% OFF',
            font: 'Inter', size: Math.round(s * 0.095), weight: 800, color: '#ffffff', align: 'left' },
          { type: 'text', x: W * 0.06, y: H - s * 0.17, w: W * 0.88, text: 'Sale ends tonight',
            font: 'Inter', size: Math.round(s * 0.048), weight: 700, color: '#ffffff', align: 'center' }
        ];
      } },
    { id: 'review', name: 'Social proof', swatch: 'linear-gradient(135deg,#38bdf8,#c084fc)',
      build: function (W, H) {
        var s = Math.min(W, H);
        return [
          { type: 'rect', x: W * 0.05, y: H * 0.05, w: W * 0.90, h: s * 0.13,
            color: 'rgba(5,8,15,0.72)', radius: Math.round(s * 0.03) },
          { type: 'text', x: W * 0.09, y: H * 0.05 + s * 0.034, w: W * 0.82,
            text: '⭐⭐⭐⭐⭐  4.9 from 1,240 reviews', font: 'Inter',
            size: Math.round(s * 0.038), weight: 700, color: '#ffffff', align: 'left' },
          { type: 'text', x: W * 0.05, y: H - s * 0.15, w: W * 0.90,
            text: '“Best purchase I made this year.”', font: 'Inter',
            size: Math.round(s * 0.042), weight: 600, color: '#ffffff', align: 'center' }
        ];
      } },
    { id: 'minimal', name: 'Clean caption', swatch: 'linear-gradient(135deg,#e2e8f0,#94a3b8)',
      build: function (W, H) {
        var s = Math.min(W, H);
        return [
          { type: 'rect', x: W * 0.08, y: H - s * 0.21, w: W * 0.84, h: s * 0.13,
            color: '#ffffff', radius: Math.round(s * 0.025) },
          { type: 'text', x: W * 0.11, y: H - s * 0.185, w: W * 0.78, text: 'Add your caption',
            font: 'Inter', size: Math.round(s * 0.042), weight: 700, color: '#0b111c', align: 'left' }
        ];
      } }
  ];

  function applyTemplate(id) {
    var tpl = null;
    for (var i = 0; i < TEMPLATES.length; i++) if (TEMPLATES[i].id === id) tpl = TEMPLATES[i];
    if (!tpl) return;
    snapshot();
    var added = tpl.build(ED.W, ED.H);
    for (var j = 0; j < added.length; j++) {
      var e = added[j];
      e.id = uid();
      e.opacity = (e.opacity == null) ? 100 : e.opacity;
      e.rotate = 0;
      e.visible = true;
      if (e.type === 'text') e.h = autoTextHeight(e);
      ED.els.push(e);
    }
    ED.sel = ED.els.length ? ED.els[ED.els.length - 1].id : null;
    render(); refreshLayers(); refreshInspector();
    toast('Template applied — double-click the text to rewrite it');
  }

  /* ═══════════════════════════════════════════════════════════
     Element factories
     ═══════════════════════════════════════════════════════════ */
  function addText(opts) {
    opts = opts || {};
    snapshot();
    var s = Math.min(ED.W, ED.H);
    var e = {
      id: uid(), type: 'text',
      x: Math.round(ED.W * 0.10), y: Math.round(ED.H * 0.42),
      w: Math.round(ED.W * 0.80), h: Math.round(s * 0.10),
      text: opts.text || 'Double-click to edit',
      font: opts.font || 'Inter',
      size: opts.size || Math.round(s * 0.055),
      weight: opts.weight || 700,
      color: opts.color || '#ffffff',
      align: opts.align || 'left',
      bg: opts.bg || '',
      radius: opts.radius || 0,
      opacity: 100, rotate: 0, visible: true
    };
    e.h = autoTextHeight(e);
    ED.els.push(e);
    ED.sel = e.id;
    render(); refreshLayers(); refreshInspector();
    return e;
  }

  function addShape(kind) {
    snapshot();
    var s = Math.min(ED.W, ED.H);
    var base = {
      id: uid(), x: Math.round(ED.W * 0.30), y: Math.round(ED.H * 0.35),
      w: Math.round(s * 0.40), h: Math.round(s * 0.40),
      color: '#c8ff00', opacity: 100, rotate: 0, visible: true, radius: 0
    };
    if (kind === 'circle') {
      base.type = 'ellipse';
    } else if (kind === 'bar') {
      base.type = 'rect'; base.w = Math.round(ED.W * 0.5); base.h = Math.round(s * 0.06); base.radius = 999;
    } else if (kind === 'pill') {
      base.type = 'badge'; base.text = 'NEW'; base.textColor = '#0b111c';
      base.size = Math.round(s * 0.038); base.w = Math.round(s * 0.24); base.h = Math.round(s * 0.085);
    } else {
      base.type = 'rect'; base.radius = Math.round(s * 0.03);
    }
    ED.els.push(base);
    ED.sel = base.id;
    render(); refreshLayers(); refreshInspector();
  }

  /* ── Text measuring (mirrors the 1.25 line-height in the CSS) ─ */
  var measureCtx = document.createElement('canvas').getContext('2d');
  function fontFor(e) {
    return (e.weight || 700) + ' ' + (e.size || 40) + 'px ' + (e.font || 'Inter') + ', system-ui, sans-serif';
  }
  function wrapLines(text, font, maxW) {
    measureCtx.font = font;
    var paragraphs = String(text == null ? '' : text).split('\n');
    var out = [];
    for (var p = 0; p < paragraphs.length; p++) {
      var words = paragraphs[p].split(/\s+/).filter(function (w) { return w !== ''; });
      if (!words.length) { out.push(''); continue; }
      var line = '';
      for (var i = 0; i < words.length; i++) {
        var test = line ? line + ' ' + words[i] : words[i];
        if (measureCtx.measureText(test).width > maxW && line) { out.push(line); line = words[i]; }
        else { line = test; }
      }
      out.push(line);
    }
    return out;
  }
  function autoTextHeight(e) {
    var lines = wrapLines(e.text, fontFor(e), Math.max(10, e.w - 16));
    return Math.max(Math.round((e.size || 40) * 1.25) * lines.length + 12, Math.round((e.size || 40) * 1.5));
  }

  /* ═══════════════════════════════════════════════════════════
     Rendering
     ═══════════════════════════════════════════════════════════ */
  function filterString() {
    var f = ED.bg.filters;
    return 'brightness(' + f.brightness + '%) contrast(' + f.contrast + '%) saturate(' +
           f.saturation + '%) sepia(' + f.sepia + '%) grayscale(' + f.grayscale + '%)' +
           (f.blur ? ' blur(' + f.blur + 'px)' : '');
  }

  function render() {
    artboard.style.width  = ED.W + 'px';
    artboard.style.height = ED.H + 'px';
    artboard.style.transform = 'scale(' + ED.zoom + ')';
    $('cvZoomLbl').textContent = Math.round(ED.zoom * 100) + '%';

    var bg = $('cvBg');
    if (bg) bg.style.filter = filterString();

    var old = artboard.querySelectorAll('.cv-el');
    for (var i = 0; i < old.length; i++) old[i].remove();

    for (var j = 0; j < ED.els.length; j++) {
      if (ED.els[j].visible === false) continue;
      artboard.appendChild(buildNode(ED.els[j], j));
    }
  }

  function buildNode(e, index) {
    var n = document.createElement('div');
    n.className = 'cv-el' + (ED.sel === e.id ? ' selected' : '');
    n.dataset.id = e.id;
    n.style.left = e.x + 'px';
    n.style.top = e.y + 'px';
    n.style.width = e.w + 'px';
    if (e.h != null) n.style.height = e.h + 'px';
    n.style.opacity = ((e.opacity == null ? 100 : e.opacity) / 100);
    n.style.transform = 'rotate(' + (e.rotate || 0) + 'deg)';
    n.style.zIndex = String(10 + index);

    if (e.type === 'text') {
      if (e.bg) {
        n.style.background = e.bg;
        n.style.borderRadius = (e.radius || 0) + 'px';
        n.style.padding = '8px';
        n.style.boxSizing = 'border-box';
      }
      var t = document.createElement('div');
      t.className = 'txt';
      t.style.font = fontFor(e);
      t.style.color = e.color;
      t.style.textAlign = e.align || 'left';
      t.textContent = e.text;
      n.appendChild(t);
    } else if (e.type === 'rect') {
      n.style.background = e.color;
      n.style.borderRadius = (e.radius || 0) + 'px';
    } else if (e.type === 'ellipse') {
      n.style.background = e.color;
      n.style.borderRadius = '50%';
    } else if (e.type === 'badge') {
      n.style.background = e.color;
      n.style.borderRadius = '999px';
      n.style.display = 'flex';
      n.style.alignItems = 'center';
      n.style.justifyContent = 'center';
      n.style.padding = '0 14px';
      n.style.boxSizing = 'border-box';
      var bt = document.createElement('div');
      bt.className = 'txt';
      bt.style.font = '800 ' + e.size + 'px Inter, system-ui, sans-serif';
      bt.style.color = e.textColor || '#0b111c';
      bt.style.whiteSpace = 'nowrap';
      bt.textContent = e.text || '';
      n.appendChild(bt);
    }

    if (ED.sel === e.id) {
      var h = document.createElement('div');
      h.className = 'cv-handles';
      h.innerHTML = '<i class="cv-h nw"></i><i class="cv-h ne"></i><i class="cv-h sw"></i><i class="cv-h se"></i>';
      n.appendChild(h);
    }
    return n;
  }

  /* ═══════════════════════════════════════════════════════════
     Selection, drag & resize
     ═══════════════════════════════════════════════════════════ */
  var drag = null;

  function toArtboard(clientX, clientY) {
    var r = artboard.getBoundingClientRect();
    return { x: (clientX - r.left) / ED.zoom, y: (clientY - r.top) / ED.zoom };
  }

  artboard.addEventListener('pointerdown', function (ev) {
    var handle = ev.target.closest('.cv-h');
    var node   = ev.target.closest('.cv-el');
    if (!node) { select(null); return; }
    ev.preventDefault();

    var id = node.dataset.id;
    if (ED.sel !== id) {
      ED.sel = id;
      render(); refreshInspector(); refreshLayers(); refreshHistoryButtons();
    }
    var e = getEl(id);
    if (!e) return;

    var p = toArtboard(ev.clientX, ev.clientY);
    snapshot();
    drag = {
      mode: handle ? handle.className.replace('cv-h ', '') : 'move',
      id: id, sx: p.x, sy: p.y,
      ox: e.x, oy: e.y, ow: e.w, oh: e.h, moved: false
    };
    try { artboard.setPointerCapture(ev.pointerId); } catch (err) {}
  });

  artboard.addEventListener('pointermove', function (ev) {
    if (!drag) return;
    var e = getEl(drag.id);
    if (!e) return;
    var p = toArtboard(ev.clientX, ev.clientY);
    var dx = p.x - drag.sx, dy = p.y - drag.sy;
    if (Math.abs(dx) > 1 || Math.abs(dy) > 1) drag.moved = true;

    if (drag.mode === 'move') {
      e.x = Math.round(drag.ox + dx);
      e.y = Math.round(drag.oy + dy);
    } else {
      var min = 24;
      if (drag.mode === 'se') {
        e.w = Math.max(min, drag.ow + dx);
        if (e.h != null) e.h = Math.max(min, drag.oh + dy);
      } else if (drag.mode === 'nw') {
        e.w = Math.max(min, drag.ow - dx);
        e.x = Math.round(drag.ox + (drag.ow - e.w));
        if (e.h != null) { e.h = Math.max(min, drag.oh - dy); e.y = Math.round(drag.oy + (drag.oh - e.h)); }
      } else if (drag.mode === 'ne') {
        e.w = Math.max(min, drag.ow + dx);
        if (e.h != null) { e.h = Math.max(min, drag.oh - dy); e.y = Math.round(drag.oy + (drag.oh - e.h)); }
      } else if (drag.mode === 'sw') {
        e.w = Math.max(min, drag.ow - dx);
        e.x = Math.round(drag.ox + (drag.ow - e.w));
        if (e.h != null) e.h = Math.max(min, drag.oh + dy);
      }
      if (e.type === 'text') e.h = autoTextHeight(e);
    }
    render();
  });

  function endDrag() {
    if (!drag) return;
    if (!drag.moved) ED.undo.pop();
    refreshHistoryButtons();
    drag = null;
    refreshInspector();
  }
  artboard.addEventListener('pointerup', endDrag);
  artboard.addEventListener('pointercancel', endDrag);

  function select(id) {
    ED.sel = id;
    render(); refreshLayers(); refreshInspector(); refreshHistoryButtons();
  }

  /* double-click a text element to edit it in place */
  artboard.addEventListener('dblclick', function (ev) {
    var node = ev.target.closest('.cv-el');
    if (!node) return;
    var e = getEl(node.dataset.id);
    if (!e || (e.type !== 'text' && e.type !== 'badge')) return;

    var input = document.createElement('textarea');
    input.value = e.text || '';
    input.style.cssText = 'position:absolute;inset:0;width:100%;height:100%;box-sizing:border-box;' +
      'background:rgba(0,0,0,.5);color:#fff;border:0;outline:2px solid #c8ff00;padding:8px;' +
      'font:' + (e.type === 'badge' ? '800 ' + e.size + 'px Inter, system-ui, sans-serif' : fontFor(e)) +
      ';resize:none;z-index:9999;';
    node.appendChild(input);
    input.focus(); input.select();

    var done = false;
    function commit() {
      if (done) return;
      done = true;
      snapshot();
      e.text = input.value;
      if (e.type === 'text') e.h = autoTextHeight(e);
      input.remove(); render(); refreshInspector(); refreshLayers();
    }
    input.addEventListener('blur', commit);
    input.addEventListener('keydown', function (k) {
      k.stopPropagation();
      if (k.key === 'Escape') { done = true; input.remove(); }
      if (k.key === 'Enter' && (k.metaKey || k.ctrlKey)) commit();
    });
  });

  /* ═══════════════════════════════════════════════════════════
     Drawers
     ═══════════════════════════════════════════════════════════ */
  var TEXT_PRESETS = [
    { cls: 't1', label: 'Add a heading',    size: 0.075, weight: 800 },
    { cls: 't2', label: 'Add a subheading', size: 0.052, weight: 700 },
    { cls: 't3', label: 'Add body text',    size: 0.036, weight: 500 },
    { cls: 't4', label: 'Add a badge',      size: 0.032, weight: 800, badge: true }
  ];
  var SHAPES = [
    { k: 'rect',   n: 'Rectangle', i: 'ph-square' },
    { k: 'circle', n: 'Circle',    i: 'ph-circle' },
    { k: 'bar',    n: 'Bar',       i: 'ph-minus'  },
    { k: 'pill',   n: 'Badge',     i: 'ph-tag'    }
  ];
  var PALETTE = ['#c8ff00','#ffffff','#0b111c','#f43f5e','#fb923c','#facc15','#22c55e',
                 '#38bdf8','#6366f1','#c084fc','#ec4899','#14b8a6','#94a3b8','#334155',
                 'rgba(5,8,15,0.72)','rgba(255,255,255,0.88)'];

  function rangeRow(label, key, val, min, max, suffix) {
    return '<div class="fl-range"><div class="hd"><span>' + label + '</span>' +
           '<output data-out="' + key + '">' + val + (suffix || '%') + '</output></div>' +
           '<input type="range" data-key="' + key + '" min="' + min + '" max="' + max +
           '" step="1" value="' + val + '"></div>';
  }

  function showTool(name) {
    var btns = document.querySelectorAll('#cvRail .cv-tool');
    for (var i = 0; i < btns.length; i++) {
      btns[i].classList.toggle('on', btns[i].dataset.tool === name);
    }
    drawer.onclick = null;
    drawer.oninput = null;

    if (name === 'templates') {
      var h = '';
      for (var t = 0; t < TEMPLATES.length; t++) {
        h += '<button type="button" class="cv-tile" data-tpl="' + TEMPLATES[t].id + '">' +
               '<div class="thumb" style="background:' + TEMPLATES[t].swatch + '">' + TEMPLATES[t].name + '</div>' +
               '<div class="cap">' + TEMPLATES[t].name + '</div></button>';
      }
      drawer.innerHTML = '<h6><i class="ph ph-layout"></i> Templates</h6>' +
        '<p class="fl-help" style="margin:0">One click lays out a headline, badge and panel sized to your canvas.</p>' +
        '<div class="cv-tiles">' + h + '</div>';
      drawer.onclick = function (ev) {
        var b = ev.target.closest('[data-tpl]');
        if (b) applyTemplate(b.dataset.tpl);
      };

    } else if (name === 'text') {
      var th = '';
      for (var p = 0; p < TEXT_PRESETS.length; p++) {
        th += '<button type="button" class="cv-textbtn ' + TEXT_PRESETS[p].cls + '" data-preset="' + p + '">' +
                '<span class="spec">' + TEXT_PRESETS[p].label + '</span></button>';
      }
      drawer.innerHTML = '<h6><i class="ph ph-text-aa"></i> Text</h6>' +
        '<div style="display:grid;gap:7px">' + th + '</div>' +
        '<p class="fl-help">Double-click any text on the canvas to rewrite it. Drag to move, use the corner handles to resize.</p>';
      drawer.onclick = function (ev) {
        var b = ev.target.closest('[data-preset]');
        if (!b) return;
        var pr = TEXT_PRESETS[+b.dataset.preset];
        var s = Math.min(ED.W, ED.H);
        if (pr.badge) {
          addShape('pill');
          var le = selEl();
          if (le) { le.size = Math.round(s * pr.size); le.text = '⭐ 4.9 RATED'; le.h = Math.round(s * 0.085); render(); refreshInspector(); }
        } else {
          addText({ size: Math.round(s * pr.size), weight: pr.weight, text: pr.label.replace('Add a ', '').replace('Add ', '') });
        }
      };

    } else if (name === 'elements') {
      var eh = '';
      for (var s2 = 0; s2 < SHAPES.length; s2++) {
        eh += '<button type="button" class="cv-tile shape" data-shape="' + SHAPES[s2].k + '">' +
                '<div class="thumb"><i class="ph ' + SHAPES[s2].i + '"></i></div>' +
                '<div class="cap">' + SHAPES[s2].n + '</div></button>';
      }
      drawer.innerHTML = '<h6><i class="ph ph-shapes"></i> Elements</h6><div class="cv-tiles">' + eh + '</div>';
      drawer.onclick = function (ev) {
        var b = ev.target.closest('[data-shape]');
        if (b) addShape(b.dataset.shape);
      };

    } else if (name === 'adjust') {
      var f = ED.bg.filters;
      drawer.innerHTML = '<h6><i class="ph ph-sliders-horizontal"></i> Adjust photo</h6>' +
        rangeRow('Brightness', 'brightness', f.brightness, 40, 180) +
        rangeRow('Contrast',   'contrast',   f.contrast,   40, 180) +
        rangeRow('Saturation', 'saturation', f.saturation, 0, 220) +
        rangeRow('Warmth',     'sepia',      f.sepia,      0, 100) +
        rangeRow('Grayscale',  'grayscale',  f.grayscale,  0, 100) +
        rangeRow('Blur',       'blur',       f.blur,       0, 12, 'px') +
        '<button type="button" class="cv-tbtn" data-reset="1" style="width:100%;justify-content:center"><i class="ph ph-arrow-u-left"></i> Reset adjustments</button>' +
        '<div class="fl-lbl">Quick looks</div><div class="fl-chips">' +
          '<button type="button" class="fl-chip" data-look="original">Original</button>' +
          '<button type="button" class="fl-chip" data-look="vibrant">Vibrant</button>' +
          '<button type="button" class="fl-chip" data-look="warm">Warm</button>' +
          '<button type="button" class="fl-chip" data-look="bw">B&amp;W</button>' +
          '<button type="button" class="fl-chip" data-look="punch">Punchy</button>' +
        '</div>';
      drawer.oninput = function (ev) {
        var key = ev.target.dataset.key;
        if (!key) return;
        ED.bg.filters[key] = parseFloat(ev.target.value);
        var out = drawer.querySelector('[data-out="' + key + '"]');
        if (out) out.textContent = ev.target.value + (key === 'blur' ? 'px' : '%');
        render();
      };
      drawer.onclick = function (ev) {
        var look = ev.target.closest('[data-look]');
        if (look) {
          snapshot();
          var m = {
            original: { brightness:100, contrast:100, saturation:100, sepia:0,  grayscale:0,   blur:0 },
            vibrant:  { brightness:104, contrast:114, saturation:145, sepia:0,  grayscale:0,   blur:0 },
            warm:     { brightness:102, contrast:106, saturation:112, sepia:26, grayscale:0,   blur:0 },
            bw:       { brightness:104, contrast:120, saturation:0,   sepia:0,  grayscale:100, blur:0 },
            punch:    { brightness:98,  contrast:126, saturation:124, sepia:8,  grayscale:0,   blur:0 }
          };
          ED.bg.filters = m[look.dataset.look] || m.original;
          render(); showTool('adjust');
        }
        if (ev.target.closest('[data-reset]')) {
          snapshot();
          ED.bg.filters = { brightness:100, contrast:100, saturation:100, sepia:0, grayscale:0, blur:0 };
          render(); showTool('adjust');
        }
      };

    } else if (name === 'photo') {
      drawer.innerHTML = '<h6><i class="ph ph-image"></i> Photo</h6>' +
        '<p class="fl-help">Swap the background for one of your saved creatives, or upload your own file.</p>' +
        '<button type="button" class="cv-tbtn" data-pick="1" style="width:100%;justify-content:center"><i class="ph ph-upload-simple"></i> Upload an image</button>' +
        '<input type="file" id="cvFile" accept="image/*" class="d-none">' +
        '<a class="cv-tbtn" href="?tab=creative_gallery" style="width:100%;justify-content:center;text-decoration:none"><i class="ph ph-images"></i> My images</a>' +
        '<div class="fl-lbl">Tint behind the photo</div><div class="cv-swatches" id="cvBgSwatches"></div>';
      var sw = drawer.querySelector('#cvBgSwatches');
      for (var c = 0; c < PALETTE.length; c++) {
        var b2 = document.createElement('button');
        b2.type = 'button'; b2.className = 'cv-sw';
        b2.style.background = PALETTE[c]; b2.dataset.bg = PALETTE[c];
        sw.appendChild(b2);
      }
      drawer.onclick = function (ev) {
        if (ev.target.closest('[data-pick]')) { drawer.querySelector('#cvFile').click(); return; }
        var s3 = ev.target.closest('[data-bg]');
        if (!s3) return;
        snapshot();
        artboard.style.background = s3.dataset.bg;
        toast('Background colour set');
      };
      drawer.querySelector('#cvFile').onchange = function (ev) {
        var file = ev.target.files && ev.target.files[0];
        if (!file) return;
        if (file.size > 12 * 1024 * 1024) { toast('Please pick an image under 12 MB.', true); return; }
        var fr = new FileReader();
        fr.onload = function () {
          var img = new Image();
          img.onload = function () {
            setCanvasSize(img.naturalWidth, img.naturalHeight);
            var bg = $('cvBg');
            if (!bg) {
              bg = document.createElement('img');
              bg.id = 'cvBg'; bg.className = 'cv-bg';
              bg.crossOrigin = 'anonymous';
              artboard.insertBefore(bg, artboard.firstChild);
            }
            bg.src = fr.result;
            fitZoom(); render();
            toast('Background replaced');
          };
          img.onerror = function () { toast('That file could not be read as an image.', true); };
          img.src = fr.result;
        };
        fr.readAsDataURL(file);
      };

    } else if (name === 'layers') {
      drawer.innerHTML = '<h6><i class="ph ph-stack"></i> Layers</h6>' +
        '<p class="fl-help">Top of the list draws on top. Click to select, use the eye to hide.</p>' +
        '<div class="cv-layers" id="cvLayerList"></div>';
      refreshLayers();
    }
  }

  function refreshLayers() {
    var host = document.getElementById('cvLayerList');
    if (!host) return;
    if (!ED.els.length) {
      host.innerHTML = '<div class="cv-noop"><i class="ph ph-stack"></i>No layers yet.<br>Add text, a shape or a template to begin.</div>';
      return;
    }
    var h = '';
    for (var i = ED.els.length - 1; i >= 0; i--) {
      var e = ED.els[i];
      var icon = e.type === 'text' ? 'ph-text-aa'
               : (e.type === 'badge' ? 'ph-tag' : (e.type === 'ellipse' ? 'ph-circle' : 'ph-square'));
      var nm = (e.type === 'text' || e.type === 'badge') ? (e.text || e.type) : e.type;
      h += '<div class="cv-layer' + (ED.sel === e.id ? ' on' : '') + '" data-id="' + e.id + '">' +
             '<i class="ico ph ' + icon + '"></i>' +
             '<span class="nm">' + esc(String(nm).slice(0, 26)) + '</span>' +
             '<button type="button" class="vis" data-vis="' + e.id + '" title="Show / hide">' +
               '<i class="ph ' + (e.visible === false ? 'ph-eye-slash' : 'ph-eye') + '"></i></button>' +
           '</div>';
    }
    host.innerHTML = h;
    host.onclick = function (ev) {
      var v = ev.target.closest('[data-vis]');
      if (v) {
        snapshot();
        var el = getEl(v.dataset.vis);
        if (el) { el.visible = (el.visible === false); render(); refreshLayers(); }
        return;
      }
      var row = ev.target.closest('[data-id]');
      if (row) select(row.dataset.id);
    };
  }

  /* ═══════════════════════════════════════════════════════════
     Inspector
     ═══════════════════════════════════════════════════════════ */
  function numRow(label, k, v) {
    return '<div class="cv-row"><label class="cv-label" style="flex:0 0 auto;width:14px">' + label + '</label>' +
           '<input class="cv-in" data-k="' + k + '" type="number" value="' + Math.round(v || 0) + '"></div>';
  }

  function refreshInspector() {
    var e = selEl();
    if (!e) {
      inspector.innerHTML = '<div class="cv-noop"><i class="ph ph-cursor-click"></i>' +
        'Select an element on the canvas to edit it — or pick a tool on the left to add something new.</div>';
      refreshHistoryButtons();
      return;
    }

    var kindLabel = e.type === 'text' ? 'Text' : (e.type === 'badge' ? 'Badge' : 'Shape');
    var h = '<div class="grp"><div class="h">' + kindLabel + '</div>';

    if (e.type === 'text' || e.type === 'badge') {
      h += '<textarea class="cv-in" data-k="text" rows="3" style="min-height:66px">' + esc(e.text || '') + '</textarea>';
    }
    if (e.type === 'text') {
      h += '<div class="cv-row">' +
             '<input class="cv-in" data-k="font" list="cvFonts" value="' + esc(e.font || 'Inter') + '" title="Font">' +
             '<input class="cv-in" data-k="size" type="number" min="8" max="400" value="' + Math.round(e.size || 40) + '" title="Size" style="max-width:82px">' +
           '</div>' +
           '<datalist id="cvFonts"><option>Inter</option><option>Arial</option><option>Georgia</option>' +
           '<option>Impact</option><option>Courier New</option><option>Trebuchet MS</option></datalist>' +
           '<div class="cv-row">' +
             '<button type="button" class="cv-tbtn' + ((e.weight || 700) >= 700 ? ' on' : '') + '" data-w="1" style="flex:1"><b>B</b></button>' +
             '<button type="button" class="cv-tbtn' + (e.align === 'left' ? ' on' : '') + '" data-align="left" style="flex:1"><i class="ph ph-text-align-left"></i></button>' +
             '<button type="button" class="cv-tbtn' + (e.align === 'center' ? ' on' : '') + '" data-align="center" style="flex:1"><i class="ph ph-text-align-center"></i></button>' +
             '<button type="button" class="cv-tbtn' + (e.align === 'right' ? ' on' : '') + '" data-align="right" style="flex:1"><i class="ph ph-text-align-right"></i></button>' +
           '</div>';
    }

    if (e.type === 'badge') {
      h += '<div class="cv-row"><label class="cv-label" style="flex:0 0 auto">Fill</label>' +
           '<input class="cv-in" data-k="color" type="color" value="' + toHex(e.color || '#c8ff00') + '" style="max-width:48px">' +
           '<input class="cv-in" data-k="size" type="number" min="8" max="200" value="' + Math.round(e.size || 32) + '" style="max-width:78px" title="Text size"></div>' +
           '<div class="cv-row"><label class="cv-label" style="flex:0 0 auto">Text</label>' +
           '<input class="cv-in" data-k="textColor" type="color" value="' + toHex(e.textColor || '#0b111c') + '" style="max-width:48px"></div>';
    } else {
      h += '<div class="cv-row"><label class="cv-label" style="flex:0 0 auto">Colour</label>' +
           '<input class="cv-in" data-k="color" type="color" value="' + toHex(e.color || '#c8ff00') + '" style="max-width:48px"></div>';
    }
    if (e.type === 'rect') {
      h += '<div class="cv-row"><label class="cv-label" style="flex:0 0 auto">Corner</label>' +
           '<input class="cv-in" data-k="radius" type="range" min="0" max="200" value="' + (e.radius || 0) + '"></div>';
    }

    h += '</div><div class="grp"><div class="h">Position &amp; size</div>' +
         numRow('X', 'x', e.x) + numRow('Y', 'y', e.y) +
         numRow('W', 'w', e.w) + (e.h != null ? numRow('H', 'h', e.h) : '') +
         '<div class="cv-row"><label class="cv-label" style="flex:0 0 auto">Rotate</label>' +
         '<input class="cv-in" data-k="rotate" type="range" min="-180" max="180" value="' + (e.rotate || 0) + '"></div>' +
         '<div class="cv-row"><label class="cv-label" style="flex:0 0 auto">Opacity</label>' +
         '<input class="cv-in" data-k="opacity" type="range" min="5" max="100" value="' + (e.opacity == null ? 100 : e.opacity) + '"></div>' +
         '</div>' +
         '<button type="button" class="cv-tbtn" data-move="up" style="width:100%;justify-content:center"><i class="ph ph-arrow-up"></i> Bring forward</button>' +
         '<button type="button" class="cv-tbtn" data-move="down" style="width:100%;justify-content:center"><i class="ph ph-arrow-down"></i> Send back</button>';

    inspector.innerHTML = h;
    refreshHistoryButtons();

    inspector.oninput = function (ev) {
      var k = ev.target.dataset.k;
      if (!k) return;
      var val = ev.target.value;
      if (['x', 'y', 'w', 'h', 'size', 'radius', 'rotate', 'opacity'].indexOf(k) > -1) {
        val = parseFloat(val);
        if (isNaN(val)) return;
      }
      if (k === 'w' || k === 'h') val = Math.max(10, val);
      e[k] = val;
      if (e.type === 'text' && ['text', 'size', 'w', 'font', 'weight'].indexOf(k) > -1) e.h = autoTextHeight(e);
      render(); refreshLayers();
    };
    inspector.onchange = function () { snapshot(); };
    inspector.onclick = function (ev) {
      if (ev.target.closest('[data-w]')) {
        snapshot();
        e.weight = (e.weight >= 700) ? 500 : 800;
        if (e.type === 'text') e.h = autoTextHeight(e);
        render(); refreshInspector(); return;
      }
      var a = ev.target.closest('[data-align]');
      if (a) { snapshot(); e.align = a.dataset.align; render(); refreshInspector(); return; }

      var mv = ev.target.closest('[data-move]');
      if (mv) {
        var idx = ED.els.indexOf(e);
        if (mv.dataset.move === 'up' && idx < ED.els.length - 1) {
          snapshot(); ED.els.splice(idx + 1, 0, ED.els.splice(idx, 1)[0]); render(); refreshLayers();
        } else if (mv.dataset.move === 'down' && idx > 0) {
          snapshot(); ED.els.splice(idx - 1, 0, ED.els.splice(idx, 1)[0]); render(); refreshLayers();
        }
      }
    };
  }

  /* ═══════════════════════════════════════════════════════════
     Export — redraw the model onto a canvas at full resolution
     ═══════════════════════════════════════════════════════════ */
  function loadBg() {
    return new Promise(function (resolve) {
      var src = $('cvBg') ? $('cvBg').getAttribute('src') : '';
      if (!src) { resolve(null); return; }
      var img = new Image();
      img.crossOrigin = 'anonymous';
      img.referrerPolicy = 'no-referrer';
      img.onload  = function () { resolve(img); };
      img.onerror = function () { resolve(null); };
      img.src = src;
    });
  }

  function drawRounded(ctx, x, y, w, h, r) {
    r = Math.min(r || 0, w / 2, h / 2);
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.arcTo(x + w, y, x + w, y + h, r);
    ctx.arcTo(x + w, y + h, x, y + h, r);
    ctx.arcTo(x, y + h, x, y, r);
    ctx.arcTo(x, y, x + w, y, r);
    ctx.closePath();
  }

  function drawText(ctx, e, color) {
    var font = fontFor(e);
    ctx.font = font;
    ctx.fillStyle = color;
    ctx.textBaseline = 'top';
    ctx.textAlign = e.align || 'left';
    var pad = e.bg ? 8 : 0;
    var maxW = Math.max(10, e.w - pad * 2);
    var lines = wrapLines(e.text, font, maxW);
    var lh = Math.round((e.size || 40) * 1.25);
    var x = (e.align === 'center') ? e.x + e.w / 2
          : (e.align === 'right' ? e.x + e.w - pad : e.x + pad);
    var y = e.y + pad + 6;
    for (var i = 0; i < lines.length; i++) ctx.fillText(lines[i], x, y + i * lh);
  }

  function exportCanvas(bgImg) {
    var cv = document.createElement('canvas');
    cv.width = ED.W; cv.height = ED.H;
    var ctx = cv.getContext('2d');

    ctx.fillStyle = artboard.style.background || '#0b111c';
    ctx.fillRect(0, 0, ED.W, ED.H);

    if (bgImg) {
      ctx.save();
      ctx.filter = filterString();
      var scale = Math.max(ED.W / bgImg.naturalWidth, ED.H / bgImg.naturalHeight);
      var dw = bgImg.naturalWidth * scale, dh = bgImg.naturalHeight * scale;
      ctx.drawImage(bgImg, (ED.W - dw) / 2, (ED.H - dh) / 2, dw, dh);
      ctx.restore();
    }

    for (var i = 0; i < ED.els.length; i++) {
      var e = ED.els[i];
      if (e.visible === false) continue;
      ctx.save();
      ctx.globalAlpha = (e.opacity == null ? 100 : e.opacity) / 100;
      if (e.rotate) {
        var cx = e.x + e.w / 2, cy = e.y + (e.h || 0) / 2;
        ctx.translate(cx, cy);
        ctx.rotate((e.rotate * Math.PI) / 180);
        ctx.translate(-cx, -cy);
      }

      if (e.type === 'rect') {
        ctx.fillStyle = e.color || '#c8ff00';
        drawRounded(ctx, e.x, e.y, e.w, e.h || e.w, e.radius || 0);
        ctx.fill();
      } else if (e.type === 'ellipse') {
        ctx.fillStyle = e.color || '#c8ff00';
        ctx.beginPath();
        ctx.ellipse(e.x + e.w / 2, e.y + (e.h || e.w) / 2, e.w / 2, (e.h || e.w) / 2, 0, 0, Math.PI * 2);
        ctx.fill();
      } else if (e.type === 'text') {
        if (e.bg) {
          ctx.fillStyle = e.bg;
          drawRounded(ctx, e.x, e.y, e.w, e.h || e.w, e.radius || 0);
          ctx.fill();
        }
        drawText(ctx, e, e.color || '#ffffff');
      } else if (e.type === 'badge') {
        ctx.fillStyle = e.color || '#c8ff00';
        drawRounded(ctx, e.x, e.y, e.w, e.h || e.w, (e.h || e.w) / 2);
        ctx.fill();
        ctx.font = '800 ' + (e.size || 32) + 'px Inter, system-ui, sans-serif';
        ctx.fillStyle = e.textColor || '#0b111c';
        ctx.textBaseline = 'middle';
        ctx.textAlign = 'center';
        ctx.fillText(e.text || '', e.x + e.w / 2, e.y + (e.h || e.w) / 2);
      }
      ctx.restore();
    }
    return cv;
  }

  function overlay(show, t, s, pct) {
    var o = $('cvOverlay');
    o.classList.toggle('show', !!show);
    if (show) {
      if (t) $('cvOverlayT').textContent = t;
      if (s) $('cvOverlayS').textContent = s;
      if (pct != null) $('cvOverlayBar').style.width = Math.max(4, Math.min(100, pct)) + '%';
    }
  }

  function download() {
    overlay(true, 'Rendering your design…', 'Loading the photo at full size.', 25);
    loadBg().then(function (img) {
      overlay(true, 'Rendering your design…', 'Compositing layers and photo.', 70);
      var cv = exportCanvas(img);
      overlay(true, 'Rendering your design…', 'Encoding PNG.', 92);
      cv.toBlob(function (blob) {
        overlay(false);
        if (!blob) { toast('Could not encode the PNG. Please try again.', true); return; }
        var a = document.createElement('a');
        var href = URL.createObjectURL(blob);
        a.href = href;
        a.download = 'adsbooster-design-' + Date.now() + '.png';
        document.body.appendChild(a); a.click(); a.remove();
        setTimeout(function () { URL.revokeObjectURL(href); }, 4000);
        toast('Downloaded ' + Math.round(blob.size / 1024) + ' KB PNG');
      }, 'image/png');
    });
  }

  /* ═══════════════════════════════════════════════════════════
     Zoom / sizing / helpers
     ═══════════════════════════════════════════════════════════ */
  function setCanvasSize(w, h) {
    ED.W = Math.max(200, Math.min(2400, Math.round(w)));
    ED.H = Math.max(200, Math.min(2400, Math.round(h)));
    artboard.style.width = ED.W + 'px';
    artboard.style.height = ED.H + 'px';
  }
  function fitZoom() {
    var r = viewport.getBoundingClientRect();
    var z = Math.min((r.width - 56) / ED.W, (r.height - 56) / ED.H, 1.5);
    ED.zoom = Math.max(0.1, z);
    render();
  }
  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
    });
  }
  function toHex(c) {
    if (/^#[0-9a-f]{6}$/i.test(c)) return c;
    if (/^#[0-9a-f]{3}$/i.test(c)) return '#' + c[1] + c[1] + c[2] + c[2] + c[3] + c[3];
    var m = /rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/.exec(c);
    if (!m) return '#c8ff00';
    return '#' + [1, 2, 3].map(function (i) {
      return ('0' + parseInt(m[i], 10).toString(16)).slice(-2);
    }).join('');
  }

  var toastEl = null;
  function toast(msg, bad) {
    if (!toastEl) {
      toastEl = document.createElement('div');
      toastEl.className = 'copy-toast';
      document.body.appendChild(toastEl);
    }
    toastEl.textContent = msg;
    toastEl.style.background = bad ? '#f43f5e' : '';
    toastEl.style.color = bad ? '#fff' : '';
    toastEl.classList.add('show');
    clearTimeout(toastEl._t);
    toastEl._t = setTimeout(function () { toastEl.classList.remove('show'); }, 2600);
  }

  /* ═══════════════════════════════════════════════════════════
     Wiring
     ═══════════════════════════════════════════════════════════ */
  document.getElementById('cvRail').addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-tool]');
    if (b) showTool(b.dataset.tool);
  });
  $('cvUndo').onclick       = doUndo;
  $('cvRedo').onclick       = doRedo;
  $('cvDownload').onclick   = download;
  $('cvZoomIn').onclick     = function () { ED.zoom = Math.min(3, ED.zoom + 0.15); render(); };
  $('cvZoomOut').onclick    = function () { ED.zoom = Math.max(0.1, ED.zoom - 0.15); render(); };
  $('cvZoomFit').onclick    = fitZoom;

  $('cvDelete').onclick = function () {
    if (!ED.sel) return;
    snapshot();
    ED.els = ED.els.filter(function (x) { return x.id !== ED.sel; });
    ED.sel = null;
    render(); refreshLayers(); refreshInspector();
  };
  $('cvDuplicate').onclick = function () {
    var e = selEl(); if (!e) return;
    snapshot();
    var copy = JSON.parse(JSON.stringify(e));
    copy.id = uid(); copy.x += 24; copy.y += 24;
    ED.els.push(copy); ED.sel = copy.id;
    render(); refreshLayers(); refreshInspector();
  };

  document.addEventListener('keydown', function (ev) {
    var tag = (ev.target.tagName || '').toLowerCase();
    if (tag === 'input' || tag === 'textarea' || ev.target.isContentEditable) return;

    var mod = ev.metaKey || ev.ctrlKey;
    if (mod && ev.key.toLowerCase() === 'z') { ev.preventDefault(); ev.shiftKey ? doRedo() : doUndo(); return; }
    if (mod && ev.key.toLowerCase() === 'y') { ev.preventDefault(); doRedo(); return; }
    if (mod && ev.key.toLowerCase() === 'd') { ev.preventDefault(); $('cvDuplicate').click(); return; }
    if ((ev.key === 'Delete' || ev.key === 'Backspace') && ED.sel) {
      ev.preventDefault(); $('cvDelete').click(); return;
    }

    var e = selEl();
    if (e && ev.key.indexOf('Arrow') === 0) {
      ev.preventDefault();
      snapshot();
      var step = ev.shiftKey ? 10 : 1;
      if (ev.key === 'ArrowUp')    e.y -= step;
      if (ev.key === 'ArrowDown')  e.y += step;
      if (ev.key === 'ArrowLeft')  e.x -= step;
      if (ev.key === 'ArrowRight') e.x += step;
      render();
    }
  });

  window.addEventListener('resize', function () {
    clearTimeout(window._cvFit);
    window._cvFit = setTimeout(fitZoom, 200);
  });

  /* ── Boot ─────────────────────────────────────────────────── */
  function seedFromGeneration() {
    var s = Math.min(ED.W, ED.H);
    if (SEED_BADGE) {
      ED.els.push({
        id: uid(), type: 'badge',
        x: Math.round(ED.W * 0.05), y: Math.round(ED.H * 0.045),
        w: Math.round(s * 0.46), h: Math.round(s * 0.085),
        text: SEED_BADGE, color: '#c8ff00', textColor: '#0b111c',
        size: Math.round(s * 0.032), opacity: 100, rotate: 0, visible: true
      });
    }
    if (SEED_HEADLINE) {
      var e = {
        id: uid(), type: 'text',
        x: Math.round(ED.W * 0.05), y: Math.round(ED.H * 0.72),
        w: Math.round(ED.W * 0.90), h: 60,
        text: SEED_HEADLINE, font: 'Inter', size: Math.round(s * 0.058),
        weight: 800, color: '#ffffff', align: 'left',
        bg: 'rgba(5,8,15,0.62)', radius: Math.round(s * 0.025),
        opacity: 100, rotate: 0, visible: true
      };
      e.h = autoTextHeight(e);
      ED.els.push(e);
    }
  }

  function boot() {
    var bg = $('cvBg');
    function start() {
      setCanvasSize((bg && bg.naturalWidth) || 1024, (bg && bg.naturalHeight) || 1024);
      seedFromGeneration();
      fitZoom();
      showTool('templates');
      refreshInspector();
      refreshLayers();
      refreshHistoryButtons();
    }
    if (bg && !bg.complete) {
      bg.addEventListener('load', start);
      bg.addEventListener('error', function () {
        setCanvasSize(1024, 1024);
        fitZoom(); showTool('templates'); refreshInspector();
        toast('That image could not be loaded. Open another one from My images.', true);
      });
    } else {
      start();
    }
  }
  boot();
})();
