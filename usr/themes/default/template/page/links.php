<?php
/**
 * Template Name: 友情链接
 * View: links
 * Context: $linkGroups（按友情链接分类分组）
 */

if (!defined('GBLOG_CORE')) { http_response_code(403); exit; }
$linkGroups = $linkGroups ?? [];
?>
<section class="content">
  <?php if (!$linkGroups): ?>
    <div class="gb-generic-list"><p class="empty">暂无友情链接分类。</p></div>
  <?php else: ?>
    <nav class="gb-list-tabs" role="tablist" aria-label="友情链接分类">
      <span class="gb-tab-group">
        <?php foreach ($linkGroups as $i => $group): ?>
          <button type="button" class="gb-list-tab<?= $i === 0 ? ' is-active' : '' ?>" id="link-tab-<?= (int)$group['id'] ?>" role="tab" aria-selected="<?= $i === 0 ? 'true' : 'false' ?>" aria-controls="link-panel-<?= (int)$group['id'] ?>" data-link-tab="<?= (int)$group['id'] ?>"><?= e($group['name']) ?></button>
        <?php endforeach; ?>
      </span>
    </nav>
    <div class="gb-generic-list link-directory">
      <?php foreach ($linkGroups as $i => $group): ?>
        <section class="links-tab-panel" id="link-panel-<?= (int)$group['id'] ?>" role="tabpanel" aria-labelledby="link-tab-<?= (int)$group['id'] ?>" data-link-panel="<?= (int)$group['id'] ?>" <?= $i === 0 ? '' : 'hidden' ?>>
          <?php if (!$group['links']): ?>
            <p class="empty">此分类下暂无友情链接。</p>
          <?php else: ?>
            <ul class="link-dir-list">
              <?php foreach ($group['links'] as $l): ?>
                <li><a href="<?= e($l['url']) ?>" target="_blank" rel="noopener" title="<?= e($l['description'] ?: $l['name']) ?>"><?= e($l['name']) ?></a></li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </section>
      <?php endforeach; ?>
    </div>
    <script>
    (function(){
      var root=document.currentScript&&document.currentScript.closest('.content');
      if(!root)return;
      var tabs=root.querySelectorAll('[data-link-tab]'),panels=root.querySelectorAll('[data-link-panel]');
      function activate(id,focus){
        Array.prototype.forEach.call(tabs,function(tab){var on=tab.getAttribute('data-link-tab')===id;tab.classList.toggle('is-active',on);tab.setAttribute('aria-selected',on?'true':'false');tab.tabIndex=on?0:-1;if(on&&focus)tab.focus();});
        Array.prototype.forEach.call(panels,function(panel){panel.hidden=panel.getAttribute('data-link-panel')!==id;});
      }
      Array.prototype.forEach.call(tabs,function(tab){tab.addEventListener('click',function(){activate(tab.getAttribute('data-link-tab'),false);});tab.addEventListener('keydown',function(event){if(event.key!=='ArrowRight'&&event.key!=='ArrowLeft')return;event.preventDefault();var list=Array.prototype.slice.call(tabs),i=list.indexOf(tab),next=(i+(event.key==='ArrowRight'?1:list.length-1))%list.length;activate(list[next].getAttribute('data-link-tab'),true);});});
      if(tabs.length)activate(tabs[0].getAttribute('data-link-tab'),false);
    })();
    </script>
  <?php endif; ?>
</section>
