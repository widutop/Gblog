<?php
if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
/** 全站唯一评论零件：外观只改主题 style/layout.css，禁止复制零件另写一套。 */
$comments      = $comments      ?? [];
$comment_open  = $comment_open  ?? true;
$comment_msg   = $comment_msg   ?? '';
$comment_ok    = $comment_ok    ?? false;
$post          = $post          ?? [];
$commentAction = $commentAction ?? ('index.php?do=post&id=' . (int)($post['id'] ?? 0) . '#comments');
?>
<section class="comments" id="comments">
  <h2 class="comments-title">评论（<?= count($comments) ?>）</h2>

  <?php if ($comment_msg !== ''): ?>
    <div class="comment-msg <?= $comment_ok ? 'ok' : 'err' ?>"><?= e($comment_msg) ?></div>
  <?php endif; ?>

  <?php if ($comment_open): ?>
    <form class="comment-form" method="post" action="<?= e($commentAction) ?>">
      <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
      <input type="hidden" name="comment_submit" value="1">
      <input type="hidden" name="parent_id" id="comment-parent-id" value="0">
      <div id="comment-reply-to" class="comment-reply-to" style="display:none;margin-bottom:10px;font-size:14px;color:var(--text-muted);">
        回复 <b id="comment-reply-name" style="color:var(--primary);"></b> · <a href="javascript:void(0)" id="comment-reply-cancel" style="color:var(--primary);">取消</a>
      </div>
      <div class="comment-form-row">
        <input type="text" name="cname" placeholder="昵称 *" required maxlength="30">
        <input type="email" name="cemail" placeholder="邮箱（不公开，可空）" maxlength="80">
      </div>
      <textarea name="ccontent" rows="4" placeholder="写下你的评论…" required></textarea>
      <button type="submit" class="btn btn-primary">发表评论</button>
    </form>
  <?php else: ?>
    <p class="empty">评论功能已关闭。</p>
  <?php endif; ?>

  <?php if ($comments): ?>
    <?php $commentNames = []; foreach ($comments as $c) { $commentNames[(int)$c['id']] = $c['name']; } ?>
    <ol class="comment-list">
      <?php foreach ($comments as $cm): ?>
        <li class="comment-item<?= $cm['parent_id'] ? ' reply' : '' ?>" id="comment-<?= (int)$cm['id'] ?>">
          <div class="comment-body">
            <b class="comment-name"><?= e($cm['name']) ?></b>
            <span class="comment-date"><?= e(fmt_date($cm['created_at'])) ?></span>
            <?php if ($cm['parent_id'] && isset($commentNames[$cm['parent_id']])): ?><span class="comment-reply">回复 @<?= e($commentNames[$cm['parent_id']]) ?></span><?php endif; ?>
            <a class="comment-reply-btn" href="javascript:void(0)" data-id="<?= (int)$cm['id'] ?>" data-name="<?= e($cm['name']) ?>">回复</a>
            <p class="comment-content"><?= nl2br(e($cm['content'])) ?></p>
          </div>
        </li>
      <?php endforeach; ?>
    </ol>
  <?php else: ?>
    <p class="empty">还没有评论，来说两句吧。</p>
  <?php endif; ?>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
  var form = document.querySelector('.comment-form');
  if (!form) return;
  form.addEventListener('submit', function () {
    var btn = form.querySelector('button[type=submit]');
    if (btn) { btn.disabled = true; setTimeout(function () { btn.disabled = false; }, 4000); }
  });
  var pidInput = document.getElementById('comment-parent-id');
  var box = document.getElementById('comment-reply-to');
  var nameEl = document.getElementById('comment-reply-name');
  var cancel = document.getElementById('comment-reply-cancel');
  document.querySelectorAll('.comment-reply-btn').forEach(function (b) {
    b.addEventListener('click', function () {
      pidInput.value = b.getAttribute('data-id');
      nameEl.textContent = b.getAttribute('data-name');
      box.style.display = '';
      var ta = form.querySelector('textarea[name=ccontent]');
      if (ta) ta.focus();
      box.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });
  });
  if (cancel) cancel.addEventListener('click', function (e) {
    e.preventDefault();
    pidInput.value = 0;
    box.style.display = 'none';
  });
});
</script>
