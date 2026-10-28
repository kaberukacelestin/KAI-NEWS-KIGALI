<?php
require_once 'auth.php';
requirePermission('manage_ai_news');
require_once '../includes/ai-news.php';

if (empty($_SESSION['ai_news_csrf'])) {
    $_SESSION['ai_news_csrf'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['ai_news_csrf'];
$setupError = '';
$flash = $_SESSION['ai_news_flash'] ?? null;
unset($_SESSION['ai_news_flash']);

try {
    aiNewsEnsureSchema();
} catch (RuntimeException $error) {
    $setupError = $error->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$setupError) {
    if (!hash_equals($csrf, (string)($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        $flash = ['type' => 'error', 'messages' => ['Your form expired. Refresh the page and try again.']];
    } else {
        try {
            $action = (string)($_POST['action'] ?? '');
            if ($action === 'scan') {
                aiNewsSaveFeeds((string)($_POST['feeds'] ?? ''));
                set_time_limit(240);
                $result = aiNewsWithScanLock(static fn(): array => aiNewsScanFeeds());
                $messages = [
                    'Checked ' . $result['feeds'] . ' feeds and found ' . $result['items'] . ' new stories.',
                ];
                $messages = array_merge($messages, $result['errors']);
                $_SESSION['ai_news_flash'] = ['type' => empty($result['errors']) ? 'success' : 'error', 'messages' => $messages];
            } elseif ($action === 'generate') {
                set_time_limit(180);
                $result = aiNewsWithScanLock(static fn(): array => aiNewsGeneratePendingDrafts(3));
                $messages = [
                    'Created ' . $result['generated'] . ' AI-assisted drafts; skipped ' . $result['skipped'] . ' stories with insufficient source detail.',
                ];
                $messages = array_merge($messages, $result['errors']);
                $_SESSION['ai_news_flash'] = ['type' => empty($result['errors']) ? 'success' : 'error', 'messages' => $messages];
            } elseif ($action === 'draft') {
                $newsId = aiNewsCreateArticleDraft((int)($_POST['item_id'] ?? 0), (int)$admin_id);
                $_SESSION['ai_news_flash'] = [
                    'type' => 'success',
                    'messages' => ['Unpublished article draft created. Review the source and edit the article before publishing.'],
                    'news_id' => $newsId,
                ];
            } elseif ($action === 'dismiss') {
                $itemId = (int)($_POST['item_id'] ?? 0);
                $stmt = db()->prepare("UPDATE ai_news_items SET status='ignored' WHERE id=? AND status IN ('new','ready')");
                if (!$stmt) {
                    throw new RuntimeException('Could not prepare story dismissal: ' . db()->error);
                }
                $stmt->bind_param('i', $itemId);
                if (!$stmt->execute()) {
                    throw new RuntimeException('Could not dismiss story: ' . $stmt->error);
                }
                if ($stmt->affected_rows !== 1) {
                    throw new RuntimeException('This story is no longer in the review queue.');
                }
                $_SESSION['ai_news_flash'] = ['type' => 'success', 'messages' => ['Story dismissed from the review queue.']];
            } else {
                throw new InvalidArgumentException('Unknown AI news action.');
            }
        } catch (Throwable $error) {
            $_SESSION['ai_news_flash'] = ['type' => 'error', 'messages' => [$error->getMessage()]];
        }
        header('Location: ai-news.php');
        exit;
    }
}

$counts = ['new' => 0, 'ready' => 0, 'drafted' => 0, 'ignored' => 0];
$stories = [];
if (!$setupError) {
    $countRows = db()->query("SELECT status, COUNT(*) AS total FROM ai_news_items GROUP BY status")->fetch_all(MYSQLI_ASSOC);
    foreach ($countRows as $row) {
        $counts[$row['status']] = (int)$row['total'];
    }
    $stories = db()->query("SELECT * FROM ai_news_items ORDER BY first_seen_at DESC LIMIT 100")->fetch_all(MYSQLI_ASSOC);
}

adminHeader('AI News Desk');
?>
<div class="admin-section">
  <?php if ($setupError): ?>
  <div class="alert alert-error"><i class="fa fa-triangle-exclamation"></i> <?= e($setupError) ?></div>
  <?php endif; ?>
  <?php if ($flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?>">
      <div>
        <?php foreach ($flash['messages'] as $message): ?><div><?= e($message) ?></div><?php endforeach; ?>
        <?php if (!empty($flash['news_id'])): ?>
        <a href="news-edit.php?id=<?= (int)$flash['news_id'] ?>">Review the unpublished article draft</a>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>

  <div class="alert alert-info">
    <div><strong>Human review is required.</strong> This desk only creates unpublished drafts; it never publishes stories. Verify the reporting, add an appropriate thumbnail, and review the source before changing an article's status to Published.</div>
  </div>

  <div class="dash-stats">
    <div class="stat-card blue"><i class="fa fa-rss"></i><div><span><?= number_format($counts['new'] + $counts['ready']) ?></span><small>Items to review</small></div></div>
    <div class="stat-card orange"><i class="fa fa-file-pen"></i><div><span><?= number_format($counts['drafted']) ?></span><small>Editor drafts</small></div></div>
    <div class="stat-card green"><i class="fa fa-check"></i><div><span><?= number_format($counts['ignored']) ?></span><small>Dismissed / insufficient detail</small></div></div>
  </div>

  <div class="admin-form-page">
    <div class="form-card">
      <h3>Sources and location coverage</h3>
      <p>Google News RSS feeds are preconfigured for Rwanda, East Africa, and world headlines. Add trusted publisher RSS feeds as additional lines. Use <code>Label | https://public-host/feed.xml</code>; feeds must use HTTPS.</p>
      <form method="POST">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="action" value="scan">
        <div class="form-group">
          <label for="feeds">RSS feeds</label>
          <textarea class="form-control" id="feeds" name="feeds" rows="7" required><?= e(aiNewsFeedConfigText()) ?></textarea>
        </div>
        <div class="alert alert-<?= aiNewsApiKey() !== '' ? 'success' : 'error' ?>">
          <div><strong>OpenAI:</strong> <?= aiNewsApiKey() !== '' ? 'API key detected. A scan may create up to 3 drafts.' : 'API key not detected. RSS scanning works, but AI drafting needs OPENAI_API_KEY in the PHP server environment.' ?></div>
        </div>
        <button class="btn-publish" type="submit" <?= $setupError ? 'disabled' : '' ?>><i class="fa fa-magnifying-glass"></i> Save feeds and scan only</button>
      </form>
      <form method="POST" style="margin-top:12px">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="action" value="generate">
        <button class="btn-publish" type="submit" <?= $setupError || aiNewsApiKey() === '' ? 'disabled' : '' ?>><i class="fa fa-wand-magic-sparkles"></i> Generate up to 3 bilingual story drafts</button>
      </form>
    </div>
  </div>

  <?php if (aiNewsApiKey() === ''): ?>
  <div class="admin-form-page">
    <div class="form-card">
      <h3>Configure OpenAI securely</h3>
      <p>Set <code>OPENAI_API_KEY</code> as a private system environment variable on the web server, then restart Apache. Never paste the key into this page, chat, or project files. Optionally set <code>OPENAI_MODEL</code>; the default is <code>gpt-4o-mini</code>.</p>
    </div>
  </div>
  <?php endif; ?>

  <div class="admin-form-page">
    <div class="form-card">
      <h3>Automatic monitoring</h3>
      <p>The scheduled scanner checks feeds and prepares no more than 3 drafts per run. For this local XAMPP installation, create a Windows Task Scheduler task that runs every 15 minutes with:</p>
      <pre><code><?= e('"' . PHP_BINARY . '" "' . dirname(__DIR__) . DIRECTORY_SEPARATOR . 'cron' . DIRECTORY_SEPARATOR . 'ai-news-scan.php' . '"') ?></code></pre>
      <p>Keep <code>OPENAI_API_KEY</code> available to the scheduled task's account as well as Apache. The task runs in CLI mode and cannot be triggered through a public web request.</p>
    </div>
  </div>

  <div class="admin-form-page">
    <div class="form-card">
      <h3>Recent monitored stories</h3>
      <?php if (!$stories): ?>
      <p>No RSS stories have been collected yet. Save the feed list and start a scan.</p>
      <?php endif; ?>
      <?php foreach ($stories as $story): ?>
      <article class="ai-news-story">
        <div class="ai-news-story-heading">
          <span class="badge badge-<?= in_array($story['status'], ['new', 'ready'], true) ? 'pending' : ($story['status'] === 'drafted' ? 'draft' : 'rejected') ?>"><?= e($story['status'] === 'ready' ? 'ready for editor' : $story['status']) ?></span>
          <span><?= e($story['location_label']) ?> · <?= e($story['source_name']) ?></span>
          <time><?= e($story['source_published_at'] ?: $story['first_seen_at']) ?></time>
        </div>
        <h4><?= e($story['source_title']) ?></h4>
        <?php if ($story['source_excerpt']): ?><p><?= e($story['source_excerpt']) ?></p><?php endif; ?>
        <p><a href="<?= e($story['source_url']) ?>" target="_blank" rel="noopener noreferrer">Open original source <i class="fa fa-arrow-up-right-from-square"></i></a></p>
        <?php if ($story['title_rw']): ?>
        <div class="ai-news-generated">
          <strong><?= e($story['title_rw']) ?></strong>
          <?php if ($story['title_en']): ?><p><?= e($story['title_en']) ?></p><?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if ($story['error_message']): ?><p class="ai-news-error"><?= e($story['error_message']) ?></p><?php endif; ?>
        <?php if ($story['status'] === 'ready' && $story['title_rw'] && $story['description_rw'] && $story['title_en'] && $story['description_en']): ?>
        <div class="ai-news-actions">
          <form method="POST">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="action" value="draft">
            <input type="hidden" name="item_id" value="<?= (int)$story['id'] ?>">
            <button class="btn-publish" type="submit"><i class="fa fa-file-pen"></i> Create unpublished draft for review</button>
          </form>
          <form method="POST">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="action" value="dismiss">
            <input type="hidden" name="item_id" value="<?= (int)$story['id'] ?>">
            <button class="btn-cancel" type="submit">Dismiss</button>
          </form>
        </div>
        <?php elseif ($story['status'] === 'new'): ?>
        <form method="POST">
          <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
          <input type="hidden" name="action" value="dismiss">
          <input type="hidden" name="item_id" value="<?= (int)$story['id'] ?>">
          <button class="btn-cancel" type="submit">Dismiss</button>
        </form>
        <?php elseif ($story['status'] === 'drafted' && $story['news_id']): ?>
        <p><a class="btn-action btn-edit" href="news-edit.php?id=<?= (int)$story['news_id'] ?>"><i class="fa fa-pen"></i> Review / edit unpublished article</a></p>
        <?php endif; ?>
      </article>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php adminFooter(); ?>
