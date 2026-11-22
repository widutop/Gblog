<?php
if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
$ucTabs = [
    'overview' => ['概览', 'home'],
    'content'  => ['我的内容', 'file'],
    'wallet'   => ['我的钱包', 'data'],
    'security' => ['账号安全', 'security'],
];
$ucUrl = static fn(string $t): string => 'index.php?do=profile&tab=' . $t;
// 右侧子 tab：控制器按 $tab 白名单定 $ucSub，此处只做标签与链接映射。
$ucSubTabMap = [
    'overview' => ['history' => '最近浏览', 'gold' => '金币动态', 'comments' => '最近评论'],
    'content'  => ['posts' => '我的文章', 'history' => '浏览历史', 'comments' => '我的评论', 'downloads' => '下载记录', 'favorites' => '我的收藏', 'likes' => '我的点赞'],
    'wallet'   => ['logs' => '金币明细', 'purchases' => '消费记录'],
    'security' => ['profile' => '基本资料', 'password' => '修改密码'],
];
$ucSubTabs = $ucSubTabMap[$tab] ?? [];
if (!$canPost) unset($ucSubTabs['posts']);
$ucSubLink = static fn(string $t, string $s): string => 'index.php?do=profile&tab=' . $t . '&sub=' . $s;
$ucSubUrl = static fn(string $s): string => $ucSubLink($tab, $s);
$ucPostUrl = $ucSubLink('security', $ucSub);
?>
<section class="uc">
  <div class="uc-body">
    <nav class="uc-nav">
      <div class="uc-hero">
        <div class="uc-avatar"><?= e(mb_substr((string)($me['nickname'] ?: $me['username']), 0, 1)) ?></div>
        <div class="uc-hero-id">
          <div class="uc-name"><?= e($me['nickname'] ?: $me['username']) ?><span class="uc-role"><?= e($roleLabel) ?></span></div>
          <div class="uc-sub">@<?= e($me['username']) ?> · <?= gblog_icon('star', 13) ?> 金币 <?= (int)($me['gold'] ?? 0) ?></div>
        </div>
        <div class="uc-hero-act">
          <a class="uc-btn uc-btn-primary" href="<?= e($ucUrl('security')) ?>"><?= gblog_icon('settings', 15) ?> 编辑资料</a>
          <?php if ($signed): ?>
            <span class="uc-btn uc-btn-done" aria-disabled="true"><?= gblog_icon('star', 15) ?> 今日已签到</span>
          <?php else: ?>
            <form class="uc-sign-form" method="post" action="<?= e($ucUrl('wallet') . '&act=sign') ?>">
              <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
              <button class="uc-btn" type="submit"><?= gblog_icon('star', 15) ?> 每日签到</button>
            </form>
          <?php endif; ?>
        </div>
      </div>
      <span class="gb-tab-group">
      <?php foreach ($ucTabs as $k => $t): ?>
        <a class="uc-nav-item<?= $tab === $k ? ' active' : '' ?>" href="<?= e($ucUrl($k)) ?>"><?= gblog_icon($t[1], 16) ?><span><?= e($t[0]) ?></span></a>
      <?php endforeach; ?>
      <a class="uc-nav-item uc-nav-exit" href="index.php?do=logout"><?= gblog_icon('logout', 16) ?><span>退出登录</span></a>
      </span>
    </nav>

    <div class="uc-panel">
      <?php if ($msg !== ''): ?><div class="uc-alert<?= $msgType === 'err' ? ' uc-alert-err' : '' ?>"><?= e($msg) ?></div><?php endif; ?>
      <?php if (!empty($_GET['forcepw']) || !empty($_SESSION['force_pw_change'])): ?>
        <div class="uc-alert uc-alert-warn">管理员已重置你的密码，请立即在<a href="<?= e($ucSubLink('security', 'password')) ?>">账号安全</a>中设置一个至少 8 位且含大小写字母的新密码。</div>
      <?php endif; ?>

      <?php if ($tab === 'overview'): ?>
        <div class="uc-stats">
          <div class="uc-stat"><span>我的文章</span><b><?= (int)$stats['posts'] ?></b><small>投稿总数</small></div>
          <div class="uc-stat"><span>我的评论</span><b><?= (int)$stats['comments'] ?></b><small>参与讨论</small></div>
          <div class="uc-stat"><span>我的收藏</span><b><?= (int)$stats['favorites'] ?></b><small>收藏内容</small></div>
          <div class="uc-stat"><span>下载记录</span><b><?= (int)$stats['downloads'] ?></b><small>已获取资源</small></div>
          <div class="uc-stat uc-stat-gold"><span>金币余额</span><b><?= (int)$stats['gold'] ?></b><small><a href="<?= e($ucSubLink('wallet', 'logs')) ?>">查看明细 →</a></small></div>
        </div>
      <?php elseif ($tab === 'security'): ?>
        <div class="uc-block uc-account">
          <h3>账号信息</h3>
          <div class="uc-kv-grid">
            <div class="uc-kv"><span>用户名</span><b><?= e($me['username']) ?></b></div>
            <div class="uc-kv"><span>身份</span><b><?= e($roleLabel) ?></b></div>
            <div class="uc-kv"><span>注册时间</span><b><?= e(fmt_date($me['created_at'] ?? '')) ?: '—' ?></b></div>
            <div class="uc-kv"><span>登录密码</span><b>已设置 · 建议定期更换</b></div>
          </div>
        </div>
      <?php endif; ?>

      <div class="uc-content-card" data-gb-region>
        <nav class="uc-content-tabs" aria-label="内容分类">
          <span class="gb-tab-group">
            <?php foreach ($ucSubTabs as $sk => $sv): ?>
            <a class="gb-list-tab<?= $ucSub === $sk ? ' is-active' : '' ?>" href="<?= e($ucSubUrl($sk)) ?>"<?= $ucSub === $sk ? ' aria-current="page"' : '' ?>><?= e($sv) ?></a>
            <?php endforeach; ?>
          </span>
          <?php if ($tab === 'wallet'): ?>
            <span class="uc-tab-aside">
              <span class="uc-tab-aside-gold">金币余额 <b><?= (int)($me['gold'] ?? 0) ?></b></span>
              <small>金币可用于下载站内付费资源</small>
            </span>
          <?php endif; ?>
        </nav>
        <div class="uc-tab-panel">
          <?php if ($tab === 'overview' && $ucSub === 'history'): ?>
            <div class="uc-block">
              <h3>最近浏览 <a class="uc-more" href="<?= e($ucSubLink('content', 'history')) ?>">查看全部 →</a></h3>
              <?php if ($recentHistory): ?>
                <ul class="uc-list">
                  <?php foreach ($recentHistory as $vh): ?>
                    <li><?= gblog_icon('eye', 14) ?><a href="index.php?do=post&id=<?= (int)$vh['id'] ?>"><?= e($vh['title']) ?></a><time><?= e(fmt_date($vh['viewed_at'])) ?></time></li>
                  <?php endforeach; ?>
                </ul>
              <?php else: ?><?= $ucEmpty('还没有浏览过内容。', 'index.php', '去首页逛逛') ?><?php endif; ?>
            </div>
          <?php elseif ($tab === 'overview' && $ucSub === 'gold'): ?>
            <div class="uc-block">
              <h3>金币动态 <a class="uc-more" href="<?= e($ucSubLink('wallet', 'logs')) ?>">查看全部 →</a></h3>
              <?php if ($recentGold): ?>
                <ul class="uc-list">
                  <?php foreach ($recentGold as $gl): ?>
                    <li><?= gblog_icon('clock', 14) ?><span class="uc-reason"><?= e($gl['reason']) ?></span><b class="<?= (int)$gl['amount'] >= 0 ? 'uc-plus' : 'uc-minus' ?>"><?= (int)$gl['amount'] >= 0 ? '+' : '' ?><?= (int)$gl['amount'] ?></b><time><?= e(fmt_date($gl['created_at'])) ?></time></li>
                  <?php endforeach; ?>
                </ul>
              <?php else: ?><?= $ucEmpty('暂无金币变动。', $ucUrl('wallet'), '去签到') ?><?php endif; ?>
            </div>
          <?php elseif ($tab === 'overview' && $ucSub === 'comments'): ?>
            <div class="uc-block">
              <h3>最近评论 <a class="uc-more" href="<?= e($ucSubLink('content', 'comments')) ?>">查看全部 →</a></h3>
              <?php if ($recentComments): ?>
                <ul class="uc-list uc-list-comment">
                  <?php foreach ($recentComments as $mc): ?>
                    <li>
                      <a href="<?= e(($mc['target_type'] ?? 'post') === 'song' ? 'index.php?do=song&id=' . (int)$mc['target_id'] : 'index.php?do=post&id=' . (int)($mc['target_id'] ?? 0)) ?>"><?= e($mc['title'] ?: '内容已删除') ?></a>
                      <span class="uc-badge uc-badge-<?= e((string)$mc['status']) ?>"><?= e($ucStatusComment($mc['status'])) ?></span>
                      <time><?= e(fmt_date($mc['created_at'])) ?></time>
                      <p><?= e(mb_substr(trim(strip_tags((string)$mc['content'])), 0, 90)) ?></p>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php else: ?><?= $ucEmpty('还没有发表过评论。', 'index.php', '去看看文章') ?><?php endif; ?>
            </div>

          <?php elseif ($tab === 'content' && $ucSub === 'posts' && $canPost): ?>
            <div class="uc-block">
              <?php if ($myPosts): ?>
                <table class="uc-table">
                  <thead><tr><th>标题</th><th>状态</th><th>浏览</th><th>日期</th></tr></thead>
                  <tbody>
                  <?php foreach ($myPosts as $mp): ?>
                    <tr>
                      <td><a href="index.php?do=post&id=<?= (int)$mp['id'] ?>"><?= e($mp['title']) ?></a></td>
                      <td><span class="uc-badge uc-badge-<?= e((string)$mp['status']) ?>"><?= e($ucStatusPost($mp['status'])) ?></span></td>
                      <td><?= (int)$mp['view_count'] ?></td>
                      <td class="uc-dim"><?= e(fmt_date($mp['created_at'])) ?></td>
                    </tr>
                  <?php endforeach; ?>
                  </tbody>
                </table>
                <?php if ($myPostsPages > 1): ?>
                  <div class="uc-pager">
                    <span class="uc-pager-info">共 <?= (int)$myPostsTotal ?> 条 · 第 <?= (int)$myPostsPage ?> / <?= (int)$myPostsPages ?> 页</span>
                    <span class="uc-pager-nav">
                      <?php if ($myPostsPage > 1): ?>
                        <a class="uc-pager-btn" href="<?= e($ucSubLink('content', 'posts') . '&page=' . ($myPostsPage - 1)) ?>">← 上一页</a>
                      <?php else: ?><span class="uc-pager-btn is-disabled">← 上一页</span><?php endif; ?>
                      <?php if ($myPostsPage < $myPostsPages): ?>
                        <a class="uc-pager-btn" href="<?= e($ucSubLink('content', 'posts') . '&page=' . ($myPostsPage + 1)) ?>">下一页 →</a>
                      <?php else: ?><span class="uc-pager-btn is-disabled">下一页 →</span><?php endif; ?>
                    </span>
                  </div>
                <?php endif; ?>
              <?php else: ?><?= $ucEmpty('还没有发布文章。', 'index.php', '去首页看看') ?><?php endif; ?>
            </div>
          <?php elseif ($tab === 'content' && $ucSub === 'history'): ?>
            <div class="uc-block">
              <h3><?= gblog_icon('eye', 15) ?> 浏览历史 <span class="uc-count"><?= count($viewHistory) ?></span></h3>
              <?php if ($viewHistory): ?>
                <ul class="uc-list"><?php foreach ($viewHistory as $vh): ?>
                  <li><?= gblog_icon('clock', 14) ?><a href="index.php?do=post&id=<?= (int)$vh['id'] ?>"><?= e($vh['title']) ?></a><time><?= e(fmt_date($vh['viewed_at'])) ?></time></li>
                <?php endforeach; ?></ul>
              <?php else: ?><?= $ucEmpty('还没有浏览记录。', 'index.php', '去首页逛逛') ?><?php endif; ?>
            </div>
          <?php elseif ($tab === 'content' && $ucSub === 'comments'): ?>
            <div class="uc-block">
              <h3>我的评论 <span class="uc-count"><?= count($myComments) ?></span></h3>
              <?php if ($myComments): ?>
                <ul class="uc-list uc-list-comment">
                  <?php foreach ($myComments as $mc): ?>
                    <li>
                      <a href="<?= e(($mc['target_type'] ?? 'post') === 'song' ? 'index.php?do=song&id=' . (int)$mc['target_id'] : 'index.php?do=post&id=' . (int)($mc['target_id'] ?? 0)) ?>"><?= e($mc['title'] ?: '内容已删除') ?></a>
                      <span class="uc-badge uc-badge-<?= e((string)$mc['status']) ?>"><?= e($ucStatusComment($mc['status'])) ?></span>
                      <time><?= e(fmt_date($mc['created_at'])) ?></time>
                      <p><?= e(mb_substr(trim(strip_tags((string)$mc['content'])), 0, 120)) ?></p>
                    </li>
                  <?php endforeach; ?>
                </ul>
              <?php else: ?><?= $ucEmpty('还没有发表过评论。', 'index.php', '去看看文章') ?><?php endif; ?>
            </div>
          <?php elseif ($tab === 'content' && $ucSub === 'downloads'): ?>
            <div class="uc-block">
              <h3><?= gblog_icon('download', 15) ?> 下载记录 <span class="uc-count"><?= count($downloads) ?></span></h3>
              <?php if ($downloads): ?>
                <ul class="uc-list"><?php foreach ($downloads as $dl): ?>
                  <li><?= gblog_icon('inbox', 14) ?><a href="index.php?do=post&id=<?= (int)$dl['id'] ?>"><?= e($dl['title']) ?></a><time><?= e(fmt_date($dl['created_at'])) ?></time></li>
                <?php endforeach; ?></ul>
              <?php else: ?><?= $ucEmpty('暂无下载记录。', 'index.php', '去看看资源') ?><?php endif; ?>
            </div>
          <?php elseif ($tab === 'content' && $ucSub === 'favorites'): ?>
            <div class="uc-block">
              <h3><?= gblog_icon('star', 15) ?> 我的收藏 <span class="uc-count"><?= count($favorites) ?></span></h3>
              <?php if ($favorites): ?>
                <ul class="uc-list"><?php foreach ($favorites as $f): ?>
                  <li><?= gblog_icon('file', 14) ?><a href="index.php?do=post&id=<?= (int)$f['id'] ?>"><?= e($f['title']) ?></a></li>
                <?php endforeach; ?></ul>
              <?php else: ?><?= $ucEmpty('还没有收藏内容。', 'index.php', '去逛逛') ?><?php endif; ?>
            </div>
          <?php elseif ($tab === 'content' && $ucSub === 'likes'): ?>
            <div class="uc-block">
              <h3><?= gblog_icon('like', 15) ?> 我的点赞 <span class="uc-count"><?= count($likes) ?></span></h3>
              <?php if ($likes): ?>
                <ul class="uc-list"><?php foreach ($likes as $lk): ?>
                  <li><?= gblog_icon('file', 14) ?><a href="index.php?do=post&id=<?= (int)$lk['id'] ?>"><?= e($lk['title']) ?></a></li>
                <?php endforeach; ?></ul>
              <?php else: ?><?= $ucEmpty('还没有点赞内容。', 'index.php?do=home', '去全站列表') ?><?php endif; ?>
            </div>

          <?php elseif ($tab === 'wallet' && $ucSub === 'logs'): ?>
            <div class="uc-block">
              <h3>金币明细 <span class="uc-count"><?= count($goldLogs) ?></span></h3>
              <?php if ($goldLogs): ?>
                <table class="uc-table">
                  <thead><tr><th>变动</th><th>原因</th><th>时间</th></tr></thead>
                  <tbody>
                  <?php foreach ($goldLogs as $gl): ?>
                    <tr>
                      <td><b class="<?= (int)$gl['amount'] >= 0 ? 'uc-plus' : 'uc-minus' ?>"><?= (int)$gl['amount'] >= 0 ? '+' : '' ?><?= (int)$gl['amount'] ?></b></td>
                      <td><?= e($gl['reason']) ?></td>
                      <td class="uc-dim"><?= e(fmt_date($gl['created_at'])) ?></td>
                    </tr>
                  <?php endforeach; ?>
                  </tbody>
                </table>
              <?php else: ?><?= $ucEmpty('暂无金币记录，签到即可获得金币。') ?><?php endif; ?>
            </div>
          <?php elseif ($tab === 'wallet' && $ucSub === 'purchases'): ?>
            <div class="uc-block">
              <h3>消费记录 <span class="uc-count"><?= count($purchases) ?></span></h3>
              <?php if ($purchases): ?>
                <table class="uc-table">
                  <thead><tr><th>项目</th><th>花费</th><th>时间</th></tr></thead>
                  <tbody>
                  <?php foreach ($purchases as $pc): ?>
                    <tr>
                      <td><?php if (!empty($pc['post_id'])): ?><a href="index.php?do=post&id=<?= (int)$pc['post_id'] ?>"><?= e($pc['post_title'] ?: $pc['reason']) ?></a><?php else: ?><?= e($pc['reason']) ?><?php endif; ?></td>
                      <td class="uc-minus">-<?= (int)$pc['amount'] ?></td>
                      <td class="uc-dim"><?= e(fmt_date($pc['created_at'])) ?></td>
                    </tr>
                  <?php endforeach; ?>
                  </tbody>
                </table>
              <?php else: ?><?= $ucEmpty('暂无金币消费记录。', 'index.php?do=home', '去全站列表') ?><?php endif; ?>
            </div>

          <?php elseif ($tab === 'security' && $ucSub === 'profile'): ?>
            <div class="uc-block">
              <h3>基本资料</h3>
              <form method="post" action="<?= e($ucPostUrl) ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="act" value="profile">
                <div class="uc-form-grid">
                  <label class="uc-field"><span>昵称</span><input type="text" name="nickname" value="<?= e($me['nickname']) ?>" placeholder="显示在前台的名称"></label>
                  <label class="uc-field"><span>邮箱</span><input type="email" name="email" value="<?= e($me['email']) ?>" placeholder="用于联系"></label>
                  <label class="uc-field"><span>手机号</span><input type="text" name="phone" value="<?= e($me['phone']) ?>" placeholder="用于找回密码"></label>
                  <label class="uc-field"><span>QQ号</span><input type="text" name="qq" value="<?= e($me['qq']) ?>" placeholder="用于找回密码"></label>
                  <label class="uc-field"><span>微信号</span><input type="text" name="wechat" value="<?= e($me['wechat']) ?>" placeholder="用于找回密码"></label>
                </div>
                <div class="uc-form-foot"><button type="submit" class="uc-btn uc-btn-primary">保存资料</button><span>留空的字段会被清空</span></div>
              </form>
            </div>
          <?php elseif ($tab === 'security' && $ucSub === 'password'): ?>
            <div class="uc-block">
              <h3>修改密码</h3>
              <form method="post" action="<?= e($ucPostUrl) ?>">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="act" value="password">
                <label class="uc-field"><span>旧密码</span><input type="password" name="oldpassword" autocomplete="current-password" placeholder="当前使用的密码"></label>
                <label class="uc-field"><span>新密码</span><input type="password" name="newpassword" autocomplete="new-password" placeholder="至少 8 位，含大小写字母"></label>
                <div class="uc-form-foot"><button type="submit" class="uc-btn uc-btn-primary">修改密码</button></div>
              </form>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</section>
