    </main><!-- /page -->
  </div><!-- /main -->
</div><!-- /app-layout -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Mobile Navigation & Sidebar Controls
function isMobileNav() {
    return window.matchMedia('(max-width: 991.98px)').matches;
}

function openMobileNav() {
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebarOverlay');
    if (sidebar) sidebar.classList.add('open');
    if (overlay) overlay.classList.add('active');
    document.body.classList.add('nav-open');
}

function closeMobileNav() {
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebarOverlay');
    if (sidebar) sidebar.classList.remove('open');
    if (overlay) overlay.classList.remove('active');
    document.body.classList.remove('nav-open');
}

function toggleSidebar() {
    if (isMobileNav()) {
        var sidebar = document.getElementById('sidebar');
        if (sidebar && sidebar.classList.contains('open')) {
            closeMobileNav();
        } else {
            openMobileNav();
        }
    } else {
        var layout = document.getElementById('appLayout');
        if (layout) {
            layout.classList.toggle('side-hidden');
            try {
                localStorage.setItem('ab-side', layout.classList.contains('side-hidden') ? 'hidden' : 'shown');
            } catch(e) {}
        }
    }
}

// User Profile Dropdown
function toggleUserDropdown(e) {
    if (e) { e.preventDefault(); e.stopPropagation(); }
    var wrap = document.getElementById('userDropdownWrap');
    if (wrap) {
        wrap.classList.toggle('open');
    }
}

// Fullscreen Toggle
function toggleFullScreen() {
    try {
        if (!document.fullscreenElement && !document.webkitFullscreenElement) {
            if (document.documentElement.requestFullscreen) {
                document.documentElement.requestFullscreen();
            } else if (document.documentElement.webkitRequestFullscreen) {
                document.documentElement.webkitRequestFullscreen();
            }
        } else {
            if (document.exitFullscreen) {
                document.exitFullscreen();
            } else if (document.webkitExitFullscreen) {
                document.webkitExitFullscreen();
            }
        }
    } catch(err) {
        console.warn('Fullscreen error:', err);
    }
}

function updateFullscreenIcon() {
    var btn = document.getElementById('fullscreenBtn');
    if (!btn) return;
    var icon = btn.querySelector('i');
    var isFull = !!(document.fullscreenElement || document.webkitFullscreenElement);
    if (icon) {
        icon.className = isFull ? 'ph ph-arrows-in' : 'ph ph-arrows-out';
    }
    btn.setAttribute('title', isFull ? 'Exit Fullscreen' : 'Enter Fullscreen');
}

document.addEventListener('fullscreenchange', updateFullscreenIcon);
document.addEventListener('webkitfullscreenchange', updateFullscreenIcon);

document.addEventListener('DOMContentLoaded', function() {
    // Restore desktop sidebar preference
    try {
        if (!isMobileNav() && localStorage.getItem('ab-side') === 'hidden') {
            var layout = document.getElementById('appLayout');
            if (layout) layout.classList.add('side-hidden');
        }
    } catch(e) {}

    // Auto-close mobile nav when any navigation item is tapped
    document.querySelectorAll('#sidebar .nav-item').forEach(function(a) {
        a.addEventListener('click', function() {
            if (isMobileNav()) closeMobileNav();
        });
    });

    // Close dropdowns on outside click
    document.addEventListener('click', function(e) {
        var userWrap = document.getElementById('userDropdownWrap');
        if (userWrap && !userWrap.contains(e.target)) {
            userWrap.classList.remove('open');
        }
        var notifWrap = document.getElementById('notifWrapper');
        if (notifWrap && !notifWrap.contains(e.target)) {
            notifWrap.classList.remove('open');
        }
        var currencyWrap = document.getElementById('currencyWrap');
        if (currencyWrap && !currencyWrap.contains(e.target)) {
            currencyWrap.classList.remove('open');
        }
    });

    // Keyboard Shortcuts (Esc closes drawers/dropdowns, Ctrl+B toggles sidebar)
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeMobileNav();
            var userWrap = document.getElementById('userDropdownWrap');
            if (userWrap) userWrap.classList.remove('open');
            var notifWrap = document.getElementById('notifWrapper');
            if (notifWrap) notifWrap.classList.remove('open');
            var currencyWrap = document.getElementById('currencyWrap');
            if (currencyWrap) currencyWrap.classList.remove('open');
        }
        if ((e.ctrlKey || e.metaKey) && (e.key === 'b' || e.key === 'B')) {
            e.preventDefault();
            toggleSidebar();
        }
    });

    // Swipe left on mobile sidebar to dismiss
    var sidebarEl = document.getElementById('sidebar');
    if (sidebarEl) {
        var touchStartX = 0;
        var touchStartY = 0;
        sidebarEl.addEventListener('touchstart', function(e) {
            touchStartX = e.changedTouches[0].screenX;
            touchStartY = e.changedTouches[0].screenY;
        }, { passive: true });

        sidebarEl.addEventListener('touchend', function(e) {
            var diffX = e.changedTouches[0].screenX - touchStartX;
            var diffY = Math.abs(e.changedTouches[0].screenY - touchStartY);
            if (diffX < -45 && diffY < 80) {
                closeMobileNav();
            }
        }, { passive: true });
    }

    // Window resize handler: reset mobile state if resized to desktop
    window.addEventListener('resize', function() {
        if (!isMobileNav()) {
            closeMobileNav();
        }
    });

    // Currency Switcher Dropdown Handler
    var currencyWrap = document.getElementById('currencyWrap');
    var currencyBtn = document.getElementById('currencyBtn');
    if (currencyWrap && currencyBtn) {
        currencyBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            currencyWrap.classList.toggle('open');
            var notifWrap = document.getElementById('notifWrapper');
            if (notifWrap) notifWrap.classList.remove('open');
            var userWrap = document.getElementById('userDropdownWrap');
            if (userWrap) userWrap.classList.remove('open');
        });
    }

    // Notifications Dropdown Handler
    var notifWrap = document.getElementById('notifWrapper');
    var notifBtn = document.getElementById('notifBtn');
    if (notifWrap && notifBtn) {
        notifBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            notifWrap.classList.toggle('open');
        });

        var markAll = document.getElementById('markAllRead');
        if (markAll) {
            markAll.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                fetch('<?= url('mark-notifications-read') ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
                })
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    if (d.success) {
                        document.querySelectorAll('.notif-item.unread').forEach(function(el) {
                            el.classList.remove('unread');
                        });
                        var badge = document.getElementById('notifBadge');
                        if (badge) badge.remove();
                        if (markAll) markAll.remove();
                    }
                }).catch(function() {});
            });
        }

        // Periodic polling
        if (typeof notifPollInterval === 'undefined') {
            var notifPollInterval = setInterval(function() {
                fetch('<?= url('notifications-poll') ?>')
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    var badge = document.getElementById('notifBadge');
                    if (d.unread_count > 0) {
                        if (!badge) {
                            badge = document.createElement('span');
                            badge.className = 'badge-count';
                            badge.id = 'notifBadge';
                            notifBtn.appendChild(badge);
                        }
                        badge.textContent = d.unread_count > 99 ? '99+' : d.unread_count;
                    } else if (badge) {
                        badge.remove();
                    }
                }).catch(function() {});
            }, 30000);
        }
    }
});

function markNotifRead(id, el) {
    if (el) el.classList.remove('unread');
    fetch('<?= url('mark-notifications-read') ?>?id=' + id, { method: 'POST' })
    .then(function(r) { return r.json(); })
    .then(function(d) {
        if (d.success) {
            var badge = document.getElementById('notifBadge');
            if (badge) {
                var count = parseInt(badge.textContent) || 0;
                if (count <= 1) badge.remove(); else badge.textContent = count - 1;
            }
        }
    }).catch(function() {});
}

// Media upload preview helper
function previewFile(input, previewId) {
    var container = document.getElementById(previewId);
    if (!container) return;
    container.innerHTML = '';
    var file = input.files[0];
    if (!file) return;
    if (file.type.startsWith('image/')) {
        var reader = new FileReader();
        reader.onload = function(e) {
            container.innerHTML = '<div style="position:relative;display:inline-block;border-radius:12px;overflow:hidden;border:1px solid var(--border);max-width:100%;"><img src="' + e.target.result + '" style="max-width:100%;max-height:300px;display:block;object-fit:cover;"><button type="button" onclick="this.parentElement.remove()" style="position:absolute;top:8px;right:8px;width:30px;height:30px;border-radius:50%;background:rgba(0,0,0,.75);color:#fff;border:none;cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center;">&times;</button></div>';
        };
        reader.readAsDataURL(file);
    } else if (file.type === 'application/pdf') {
        container.innerHTML = '<div style="display:flex;align-items:center;gap:12px;padding:16px;background:var(--bg2);border:1px solid var(--border);border-radius:12px;max-width:420px;"><div style="width:48px;height:48px;border-radius:10px;background:var(--red-dim);display:flex;align-items:center;justify-content:center;"><i class="ph ph-file-pdf" style="font-size:24px;color:var(--red);"></i></div><div style="flex:1;min-width:0;"><p style="margin:0;font-size:14px;font-weight:600;color:var(--fg);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">' + file.name + '</p><p style="margin:0;font-size:12px;color:var(--fg2);">' + (file.size/1024).toFixed(1) + ' KB</p></div><button type="button" onclick="this.parentElement.remove()" style="width:28px;height:28px;border-radius:50%;background:var(--red-dim);color:var(--red);border:none;cursor:pointer;font-size:16px;">&times;</button></div>';
    } else if (file.type.startsWith('video/')) {
        var url = URL.createObjectURL(file);
        container.innerHTML = '<div style="position:relative;display:inline-block;border-radius:12px;overflow:hidden;border:1px solid var(--border);max-width:100%;"><video src="' + url + '" controls style="max-width:100%;max-height:300px;display:block;"></video><button type="button" onclick="this.parentElement.remove();URL.revokeObjectURL(this.querySelector(\'video\')?.src)" style="position:absolute;top:8px;right:8px;width:30px;height:30px;border-radius:50%;background:rgba(0,0,0,.75);color:#fff;border:none;cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center;">&times;</button></div>';
    }
}
</script>
<?php
// ── First-login quick guide ──
// Shows once per session on the first app page after login or registration.
if (is_logged_in() && empty($_SESSION['quick_guide_seen'])):
    $_SESSION['quick_guide_seen'] = 1;
?>
<div id="quickGuideOverlay" style="position:fixed;inset:0;background:rgba(4,7,14,.78);backdrop-filter:blur(4px);z-index:100000;display:flex;align-items:center;justify-content:center;padding:20px;">
  <div style="background:var(--surface,#0d1524);border:1px solid var(--border);border-radius:20px;max-width:520px;width:100%;padding:30px;position:relative;box-shadow:0 30px 80px rgba(0,0,0,.6);">
    <button type="button" onclick="closeQuickGuide()" style="position:absolute;top:14px;right:14px;width:32px;height:32px;border-radius:50%;background:var(--bg);border:1px solid var(--border);color:var(--fg2);cursor:pointer;font-size:16px;">&times;</button>
    <div style="text-align:center;margin-bottom:22px;">
      <div style="width:58px;height:58px;border-radius:16px;background:rgba(200,255,0,.12);color:var(--accent);display:flex;align-items:center;justify-content:center;font-size:28px;margin:0 auto 12px;"><i class="ph-bold ph-rocket-launch"></i></div>
      <h3 style="color:var(--fg);font-weight:800;font-size:20px;margin:0 0 6px;">Welcome to <?= e(get_setting('site_name', 'Adsbooster.net')) ?> 🎉</h3>
      <p style="color:var(--fg2);font-size:13.5px;margin:0;">Launch your first ad in under 5 minutes — here's the fast path:</p>
    </div>
    <div style="display:flex;flex-direction:column;gap:12px;margin-bottom:24px;">
      <?php
      $guideSteps = [
          ['ph-link',            'Link your accounts',   'Connect Instagram, TikTok, X, LinkedIn or Snapchat with official OAuth — 20 seconds each.', url('social-connect')],
          ['ph-sparkle',         'Create with AI',       'AI writes the copy, Nano Banana 2 paints the images, Veo renders the video.', url('ugc-generator')],
          ['ph-rocket-launch',   'Publish everywhere',   'One click posts your content or launches ads live on every connected network.', url('create-ad')],
          ['ph-gift',            'Earn with referrals',  'Share your link — earn credit rewards when friends join and top up.', url('referrals')],
      ];
      foreach ($guideSteps as $i => $gs): ?>
        <a href="<?= $gs[3] ?>" onclick="closeQuickGuide()" style="display:flex;gap:13px;align-items:flex-start;background:var(--bg);border:1px solid var(--border);border-radius:13px;padding:13px 15px;text-decoration:none;transition:border-color .15s;" onmouseover="this.style.borderColor='var(--accent)'" onmouseout="this.style.borderColor='var(--border)'">
          <div style="width:36px;height:36px;border-radius:10px;background:rgba(200,255,0,.1);color:var(--accent);display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="ph-bold <?= $gs[0] ?>" style="font-size:18px;"></i></div>
          <div>
            <div style="color:var(--fg);font-weight:700;font-size:14px;"><?= ($i + 1) . '. ' . e($gs[1]) ?></div>
            <div style="color:var(--fg2);font-size:12.5px;line-height:1.45;margin-top:2px;"><?= e($gs[2]) ?></div>
          </div>
          <i class="ph ph-caret-right" style="color:var(--fg3);margin-left:auto;align-self:center;"></i>
        </a>
      <?php endforeach; ?>
    </div>
    <button type="button" onclick="closeQuickGuide()" class="btn-accent" style="width:100%;justify-content:center;padding:13px;font-size:14.5px;font-weight:700;display:flex;align-items:center;gap:8px;">
      <i class="ph-bold ph-lightning"></i> Start creating — got it!
    </button>
  </div>
</div>
<script>
function closeQuickGuide() {
  var o = document.getElementById('quickGuideOverlay');
  if (o) { o.style.opacity = '0'; o.style.transition = 'opacity .25s'; setTimeout(function(){ o.remove(); }, 260); }
  try { localStorage.setItem('ab_quick_guide_seen', '1'); } catch (e) {}
}
document.addEventListener('keydown', function(e) { if (e.key === 'Escape') closeQuickGuide(); });
</script>
<?php endif; ?>

<?php
// ── Onboarding popup + social connector (responsive, gateway-backed) ──
require_once __DIR__ . '/onboarding_popup.php';
?>
</body>
</html>
