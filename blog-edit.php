<?php declare(strict_types=1);

require_once __DIR__ . '/config/site_setting_and_config.php';
require_once __DIR__ . '/includes/auth_guard.php';

$userId = current_user_id();
$user = current_user();
$isAdmin = ($user['role'] ?? 'user') === 'admin';

$postId = (int)($_GET['id'] ?? 0);
if ($postId <= 0) {
    redirect(url('blog-manage'));
}

$post = db()->fetchOne("SELECT * FROM blog_posts WHERE id = ?", [$postId]);
if (!$post) {
    set_flash('error', 'Article not found.');
    redirect(url('blog-manage'));
}

// Authorization check: must be author or platform admin
if (!$isAdmin && (int)$post['author_id'] !== $userId) {
    set_flash('error', 'You do not have permission to edit this article.');
    redirect(url('blog-manage'));
}

$errors = [];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && csrf_verify()) {
    $title     = trim($_POST['title'] ?? '');
    $slug      = trim($_POST['slug'] ?? '');
    $category  = trim($_POST['category'] ?? 'Advertising');
    $tags      = trim($_POST['tags'] ?? '');
    $excerpt   = trim($_POST['excerpt'] ?? '');
    $body      = trim($_POST['body'] ?? '');
    $status    = in_array($_POST['status'] ?? '', ['published', 'draft', 'pending'], true) ? $_POST['status'] : 'published';
    $imageUrl  = trim($_POST['image_url'] ?? '');

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

    // Check slug uniqueness excluding self
    $existing = db()->fetchOne("SELECT id FROM blog_posts WHERE slug = ? AND id != ?", [$slug, $postId]);
    if ($existing) {
        $slug .= '-' . time();
    }

    $featuredImage = !empty($imageUrl) ? $imageUrl : $post['featured_image'];

    // Handle file upload if provided
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
        $updateData = [
            'title'          => $title,
            'slug'           => $slug,
            'category'       => $category,
            'tags'           => $tags,
            'excerpt'        => $excerpt,
            'body'           => $body,
            'featured_image' => $featuredImage,
            'status'         => $status,
        ];

        if ($status === 'published' && empty($post['published_at'])) {
            $updateData['published_at'] = date('Y-m-d H:i:s');
        }

        db()->update('blog_posts', $updateData, 'id = ?', [$postId]);

        set_flash('success', "Article '{$title}' updated successfully!");
        redirect(url('blog-manage'));
    }
}

$pageTitle = 'Edit Article: ' . e($post['title']) . ' â€” ' . get_setting('site_name', 'Adsbooster.net');
$_appPage = 'blog-manage';
require_once __DIR__ . '/includes/app_header.php';
?>

<!-- CKEditor 5 Free Classic via CDN -->
<script src="https://cdn.ckeditor.com/ckeditor5/41.1.0/classic/ckeditor.js"></script>

<div class="container-fluid px-0">
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <h1 class="page-title text-white fw-bold mb-1">
                <i class="ph ph-note-pencil text-accent me-2"></i> Edit Article
            </h1>
            <p class="text-muted mb-0 font-size-14">Update content, SEO slugs, and publication status.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= url('blog-details') ?>?slug=<?= e($post['slug']) ?>" target="_blank" class="btn btn-outline-light rounded-pill px-3">
                <i class="ph ph-eye me-1"></i> View Live
            </a>
            <a href="<?= url('blog-manage') ?>" class="btn btn-outline-light rounded-pill px-4">
                <i class="ph ph-arrow-left me-1"></i> Back to Articles
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

    <form method="POST" enctype="multipart/form-data" class="row g-4" id="postForm">
        <?= csrf_field() ?>

        <!-- Left Column: Main Editor -->
        <div class="col-lg-8">
            <div class="card bg-card border-card p-4 rounded-4 mb-4">
                <!-- Post Title -->
                <div class="mb-3">
                    <label class="form-label text-white fw-semibold">Article Title <span class="text-danger">*</span></label>
                    <input type="text" id="postTitle" name="title" class="form-control form-control-lg bg-dark border-secondary text-white rounded-3" 
                           value="<?= e($_POST['title'] ?? $post['title']) ?>" required>
                </div>

                <!-- Custom Slug -->
                <div class="mb-3">
                    <label class="form-label text-muted font-size-13">URL Slug</label>
                    <div class="input-group">
                        <span class="input-group-text bg-dark border-secondary text-muted font-size-13"><?= BASE_URL ?>/blog-details.php?slug=</span>
                        <input type="text" id="postSlug" name="slug" class="form-control bg-dark border-secondary text-white font-size-13" 
                               value="<?= e($_POST['slug'] ?? $post['slug']) ?>">
                    </div>
                </div>

                <!-- Excerpt -->
                <div class="mb-3">
                    <label class="form-label text-white fw-semibold">Short Excerpt / Summary</label>
                    <textarea name="excerpt" class="form-control bg-dark border-secondary text-white rounded-3" rows="2"><?= e($_POST['excerpt'] ?? $post['excerpt']) ?></textarea>
                </div>

                <!-- CKEditor 5 Body -->
                <div class="mb-2">
                    <label class="form-label text-white fw-semibold">Article Body <span class="text-danger">*</span></label>
                    <textarea id="editor" name="body" class="form-control"><?= e($_POST['body'] ?? $post['body'] ?? $post['content'] ?? '') ?></textarea>
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
                    <?php $currStatus = $_POST['status'] ?? $post['status']; ?>
                    <select name="status" class="form-select bg-dark border-secondary text-white rounded-3">
                        <option value="published" <?= $currStatus === 'published' ? 'selected' : '' ?>>ðŸš€ Published (Live on Site)</option>
                        <option value="draft" <?= $currStatus === 'draft' ? 'selected' : '' ?>>ðŸ“ Draft</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label text-muted font-size-13">Category</label>
                    <?php $currCat = $_POST['category'] ?? $post['category']; ?>
                    <select name="category" class="form-select bg-dark border-secondary text-white rounded-3">
                        <option value="Advertising" <?= $currCat === 'Advertising' ? 'selected' : '' ?>>ðŸ“¢ Advertising &amp; Campaigns</option>
                        <option value="AI & Automation" <?= $currCat === 'AI & Automation' ? 'selected' : '' ?>>ðŸ¤– AI &amp; Copy Automation</option>
                        <option value="SEO & Growth" <?= $currCat === 'SEO & Growth' ? 'selected' : '' ?>>ðŸ“ˆ SEO &amp; Growth Hacking</option>
                        <option value="Social Media" <?= $currCat === 'Social Media' ? 'selected' : '' ?>>ðŸ“± Social Media Strategies</option>
                        <option value="Directory & Places" <?= $currCat === 'Directory & Places' ? 'selected' : '' ?>>ðŸ“ Directory &amp; Local Business</option>
                        <option value="Platform News" <?= $currCat === 'Platform News' ? 'selected' : '' ?>>âœ¨ Platform News &amp; Updates</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label text-muted font-size-13">Tags</label>
                    <input type="text" name="tags" class="form-control bg-dark border-secondary text-white rounded-3" 
                           value="<?= e($_POST['tags'] ?? $post['tags']) ?>">
                </div>

                <div class="text-muted font-size-12 mb-3">
                    <div><b>Views:</b> <?= number_format((int)($post['views_count'] ?? 0)) ?></div>
                    <div><b>Created:</b> <?= date('M d, Y H:i', strtotime($post['created_at'])) ?></div>
                </div>

                <hr class="border-secondary my-4">

                <button type="submit" class="btn btn-accent w-100 py-3 rounded-pill fw-bold text-dark d-flex align-items-center justify-content-center gap-2">
                    <i class="ph-bold ph-check"></i> Save Changes
                </button>
            </div>

            <!-- Featured Image Card -->
            <div class="card bg-card border-card p-4 rounded-4 mb-4">
                <h6 class="text-white fw-bold mb-3 d-flex align-items-center gap-2">
                    <i class="ph ph-image text-accent"></i> Featured Image
                </h6>

                <?php if (!empty($post['featured_image'])): ?>
                    <div class="mb-3 text-center">
                        <img src="<?= (str_starts_with($post['featured_image'], 'http') ? '' : ASSETS_URL . '/') . e($post['featured_image']) ?>" 
                             alt="Current Image" class="img-fluid rounded-3" style="max-height:160px; object-fit:cover;">
                        <div class="text-muted font-size-11 mt-1">Current Image</div>
                    </div>
                <?php endif; ?>

                <div class="mb-3">
                    <label class="form-label text-muted font-size-13">Replace Image File</label>
                    <input type="file" name="image_file" accept="image/*" class="form-control bg-dark border-secondary text-white font-size-13">
                </div>

                <div class="text-center text-muted font-size-12 my-2">â€” OR Image URL â€”</div>

                <div class="mb-2">
                    <input type="url" name="image_url" class="form-control bg-dark border-secondary text-white font-size-13" 
                           placeholder="https://..." value="<?= e($_POST['image_url'] ?? (str_starts_with($post['featured_image'] ?? '', 'http') ? $post['featured_image'] : '')) ?>">
                </div>
            </div>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    ClassicEditor
        .create(document.querySelector('#editor'), {
            toolbar: [
                'heading', '|',
                'bold', 'italic', 'link', 'bulletedList', 'numberedList', 'blockQuote', '|',
                'insertTable', 'undo', 'redo'
            ]
        })
        .catch(error => console.error('CKEditor error:', error));
});
</script>

<?php require_once __DIR__ . '/includes/app_footer.php'; ?>
