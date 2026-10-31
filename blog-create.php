<?php declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';
require_once __DIR__ . '/includes/auth_guard.php';

$userId = current_user_id();
$user = current_user();

$errors = [];
$notice = [];
$success = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && csrf_verify()) {
    $title     = trim($_POST['title'] ?? '');
    $slug      = trim($_POST['slug'] ?? '');
    $category  = trim($_POST['category'] ?? 'Advertising');
    $tags      = trim($_POST['tags'] ?? '');
    $excerpt   = trim($_POST['excerpt'] ?? '');
    $body      = sanitize_html(trim($_POST['body'] ?? ''));
    $status    = in_array($_POST['status'] ?? '', ['published', 'draft'], true) ? $_POST['status'] : 'published';
    $imageUrl  = trim($_POST['image_url'] ?? '');

    // Only staff may publish to the public blog. Everyone else is queued as a draft.
    $canPublish = is_admin() || in_array(current_user_role() ?? '', ['editor', 'author'], true);
    if ($status === 'published' && !$canPublish) {
        $status = 'draft';
        $notice[] = 'Your article was saved as a draft â€” a reviewer will publish it shortly.';
    }

    if ($title === '') {
        $errors[] = 'Please provide an article title.';
    }

    if ($body === '') {
        $errors[] = 'Article body content cannot be empty.';
    }

    if ($slug === '') {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));
    } else {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $slug), '-'));
    }

    // Check slug uniqueness
    $existing = db()->fetchOne("SELECT id FROM blog_posts WHERE slug = ?", [$slug]);
    if ($existing) {
        $slug .= '-' . time();
    }

    // Handle file upload if provided
    $featuredImage = $imageUrl;
    if (!empty($_FILES['image_file']['name']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $_FILES['image_file']['tmp_name']);
        finfo_close($finfo);

        $ext = strtolower(pathinfo($_FILES['image_file']['name'], PATHINFO_EXTENSION));
        $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

        if (in_array($mime, $allowed, true) && in_array($ext, $allowedExts, true)) {
            $uploadDir = __DIR__ . '/assets/uploads/blog';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0755, true);
            }
            $filename = 'post_' . bin2hex(random_bytes(10)) . '.' . $ext;
            $destination = $uploadDir . '/' . $filename;
            if (move_uploaded_file($_FILES['image_file']['tmp_name'], $destination)) {
                $featuredImage = 'uploads/blog/' . $filename;
            }
        } else {
            $errors[] = 'Featured image must be a valid JPG, PNG, WebP, or GIF image.';
        }
    }

    if (empty($excerpt)) {
        $excerpt = mb_substr(strip_tags($body), 0, 180) . '...';
    }

    if (empty($errors)) {
        $postId = db()->insert('blog_posts', [
            'title'          => $title,
            'slug'           => $slug,
            'category'       => $category,
            'tags'           => $tags,
            'excerpt'        => $excerpt,
            'body'           => $body,
            'featured_image' => $featuredImage ?: 'images/thumbs/blog-page-img1.png',
            'author_id'      => $userId,
            'status'         => $status,
            'published_at'   => $status === 'published' ? date('Y-m-d H:i:s') : null,
            'created_at'     => date('Y-m-d H:i:s'),
            'views_count'    => 0,
        ]);

        set_flash('success', "Article '{$title}' created successfully!");
        redirect(url('blog-manage'));
    }
}

$pageTitle = 'Write New Blog Post â€” ' . get_setting('site_name', 'Adsbooster.net');
$_appPage = 'blog-manage';
require_once __DIR__ . '/includes/app_header.php';
?>

<!-- CKEditor 5 Free Classic via CDN -->
<script src="https://cdn.ckeditor.com/ckeditor5/41.1.0/classic/ckeditor.js"></script>

<div class="container-fluid px-0">
    <!-- Header Row -->
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <h1 class="page-title text-white fw-bold mb-1">
                <i class="ph ph-note-pencil text-accent me-2"></i> Write New Article
            </h1>
            <p class="text-muted mb-0 font-size-14">Craft high-converting marketing guides, product updates, and platform announcements.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= url('blog-manage') ?>" class="btn btn-outline-light rounded-pill px-4">
                <i class="ph ph-arrow-left me-1"></i> Back to Articles
            </a>
            <a href="<?= url('blog') ?>" target="_blank" class="btn btn-outline-light rounded-pill px-3" title="View Public Blog">
                <i class="ph ph-arrow-square-out me-1"></i> Public Blog
            </a>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger d-flex align-items-center gap-2 mb-4">
            <i class="ph ph-warning-circle text-danger fs-5"></i>
            <div>
                <ul class="mb-0 ps-3">
                    <?php foreach ($errors as $e): ?>
                        <li><?= e($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($notice)): ?>
        <div class="alert alert-info d-flex align-items-center gap-2 mb-4 py-2 px-3 small rounded-3">
            <i class="ph ph-info fs-5"></i>
            <div>
                <?php foreach ($notice as $n): ?><div><?= e($n) ?></div><?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="row g-4" id="postForm">
        <?= csrf_field() ?>

        <!-- Left Column: Main Editor -->
        <div class="col-lg-8">
            <div class="card bg-card border-card p-4 rounded-4 mb-4">
                <!-- Post Title -->
                <div class="mb-3">
                    <label class="form-label text-white fw-semibold">Article Title <span class="text-danger">*</span></label>
                    <input type="text" id="postTitle" name="title" class="form-control form-control-lg bg-dark border-secondary text-white rounded-3" 
                           placeholder="e.g. 5 Strategies to 3x Your ROAS Across Meta and TikTok in 2026" 
                           value="<?= e($_POST['title'] ?? '') ?>" required>
                </div>

                <!-- Custom Slug -->
                <div class="mb-3">
                    <label class="form-label text-muted font-size-13">URL Slug</label>
                    <div class="input-group">
                        <span class="input-group-text bg-dark border-secondary text-muted font-size-13"><?= BASE_URL ?>/blog-details.php?slug=</span>
                        <input type="text" id="postSlug" name="slug" class="form-control bg-dark border-secondary text-white font-size-13" 
                               placeholder="auto-generated-from-title" value="<?= e($_POST['slug'] ?? '') ?>">
                    </div>
                </div>

                <!-- Excerpt -->
                <div class="mb-3">
                    <label class="form-label text-white fw-semibold">Short Excerpt / Summary</label>
                    <textarea name="excerpt" class="form-control bg-dark border-secondary text-white rounded-3" rows="2" 
                              placeholder="Brief 1-2 sentence preview for search results and social cards..."><?= e($_POST['excerpt'] ?? '') ?></textarea>
                </div>

                <!-- CKEditor 5 Body -->
                <div class="mb-2">
                    <label class="form-label text-white fw-semibold">Article Body <span class="text-danger">*</span></label>
                    <textarea id="editor" name="body" class="form-control"><?= e($_POST['body'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <!-- Right Column: Settings & Publishing -->
        <div class="col-lg-4">
            <!-- Publishing Controls -->
            <div class="card bg-card border-card p-4 rounded-4 mb-4">
                <h6 class="text-white fw-bold mb-3 d-flex align-items-center gap-2">
                    <i class="ph ph-paper-plane-tilt text-accent"></i> Publishing Options
                </h6>

                <div class="mb-3">
                    <label class="form-label text-muted font-size-13">Status</label>
                    <select name="status" class="form-select bg-dark border-secondary text-white rounded-3">
                        <option value="published" selected>ðŸš€ Published (Live on Site)</option>
                        <option value="draft">ðŸ“ Save as Draft</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label text-muted font-size-13">Category</label>
                    <select name="category" class="form-select bg-dark border-secondary text-white rounded-3">
                        <option value="Advertising" selected>ðŸ“¢ Advertising &amp; Campaigns</option>
                        <option value="AI & Automation">ðŸ¤– AI &amp; Copy Automation</option>
                        <option value="SEO & Growth">ðŸ“ˆ SEO &amp; Growth Hacking</option>
                        <option value="Social Media">ðŸ“± Social Media Strategies</option>
                        <option value="Directory & Places">ðŸ“ Directory &amp; Local Business</option>
                        <option value="Platform News">âœ¨ Platform News &amp; Updates</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label text-muted font-size-13">Tags (comma separated)</label>
                    <input type="text" name="tags" class="form-control bg-dark border-secondary text-white rounded-3" 
                           placeholder="meta, roas, marketing, leads" value="<?= e($_POST['tags'] ?? '') ?>">
                </div>

                <hr class="border-secondary my-4">

                <button type="submit" class="btn btn-accent w-100 py-3 rounded-pill fw-bold text-dark d-flex align-items-center justify-content-center gap-2">
                    <i class="ph-bold ph-check"></i> Publish Article Now
                </button>
            </div>

            <!-- Featured Image Card -->
            <div class="card bg-card border-card p-4 rounded-4 mb-4">
                <h6 class="text-white fw-bold mb-3 d-flex align-items-center gap-2">
                    <i class="ph ph-image text-accent"></i> Featured Image
                </h6>

                <div class="mb-3">
                    <label class="form-label text-muted font-size-13">Upload Image</label>
                    <input type="file" name="image_file" accept="image/*" class="form-control bg-dark border-secondary text-white font-size-13">
                </div>

                <div class="text-center text-muted font-size-12 my-2">â€” OR Image URL â€”</div>

                <div class="mb-2">
                    <input type="url" name="image_url" class="form-control bg-dark border-secondary text-white font-size-13" 
                           placeholder="https://images.unsplash.com/..." value="<?= e($_POST['image_url'] ?? '') ?>">
                </div>
                <small class="text-muted font-size-11">Recommended resolution: 1200x630 (16:9 aspect ratio)</small>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize CKEditor 5
    ClassicEditor
        .create(document.querySelector('#editor'), {
            toolbar: [
                'heading', '|',
                'bold', 'italic', 'link', 'bulletedList', 'numberedList', 'blockQuote', '|',
                'insertTable', 'undo', 'redo'
            ]
        })
        .then(editor => {
            window.blogEditor = editor;
        })
        .catch(error => {
            console.error('CKEditor error:', error);
        });

    // Auto slug generator from title
    const titleInput = document.getElementById('postTitle');
    const slugInput = document.getElementById('postSlug');
    if (titleInput && slugInput) {
        titleInput.addEventListener('input', function() {
            if (!slugInput.dataset.manual) {
                slugInput.value = this.value
                    .toLowerCase()
                    .trim()
                    .replace(/[^a-z0-9 -]/g, '')
                    .replace(/\s+/g, '-')
                    .replace(/-+/g, '-');
            }
        });
        slugInput.addEventListener('input', function() {
            this.dataset.manual = '1';
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/app_footer.php'; ?>
