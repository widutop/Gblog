/* Gblog 通用表格排序：数值 / 日期列的表头可点击。
 * 默认按当前显示方向，点一次翻转（升序列表 -> 降序 9、8、7、6、5）。
 * 作用于当前页可见行；排序方向按表格结构存入 sessionStorage，翻页后仍保持。
 * 无依赖，前后台共用同一份实现（admin/assets/table_sort.js 为副本）。 */
(function () {
    'use strict';

    var CSS = [
        'th.gb-sortable{cursor:pointer;user-select:none;-webkit-user-select:none;white-space:nowrap;}',
        'th.gb-sortable::after{content:"\\21c5";display:inline-block;margin-left:5px;opacity:.38;font-weight:400;}',
        'th.gb-sortable[data-dir="asc"]::after{content:"\\2191";}',
        'th.gb-sortable[data-dir="desc"]::after{content:"\\2193";}',
        'th.gb-sortable.gb-sort-active::after{opacity:1;}',
        'th.gb-sortable:hover::after,th.gb-sortable:focus-visible::after{opacity:.95;}'
    ].join('');

    // 纯文本列即使偶尔带数字也不排序，避免误把标题 / 操作列做成排序列
    var SKIP_HEADER = /操作|编辑|删除|管理|网址|链接|标题|名称|内容|说明|原因|项目|图标|封面|应用|版本|文件|菜单名|slug|状态|角色|权限|栏目|分类|类型|作者|所属作品|网站|核心要求|首页菜单|绑定域名|首页形态/i;
    var EMPTY = /^(?:|—|–|-|N\/A|暂无|未知)$/u;
    var UNIT = /^(?:万|亿|千|百|字|章|条|次|人|个|元|金币|积分|张|篇|KB|MB|GB|K|M|G|B|%|岁|天|小时|分钟|秒)+$/i;

    function text(el) {
        return ((el && el.textContent) ? el.textContent : '').replace(/\s+/g, ' ').trim();
    }

    function headCells(table) {
        if (!table.tHead || !table.tHead.rows.length) return [];
        return Array.prototype.slice.call(table.tHead.rows[table.tHead.rows.length - 1].cells);
    }

    // 数字：支持 1,234 / +100 / -10 金币 / 1.2万字 / 12 KB / 第 6 章
    function numValue(s) {
        var t = s.replace(/,/g, '').replace(/\u00a0/g, ' ');
        var m = t.match(/^[^0-9+\-]*([+\-]?[0-9]+(?:\.[0-9]+)?)\s*([^0-9]*)$/);
        if (!m) return null;
        var tail = (m[2] || '').trim();
        if (tail !== '' && !UNIT.test(tail)) return null;
        var v = parseFloat(m[1]);
        if (!isFinite(v)) return null;
        var k = 1;
        if (tail.indexOf('亿') >= 0) k = 100000000;
        else if (tail.indexOf('万') >= 0) k = 10000;
        else if (tail.indexOf('千') >= 0) k = 1000;
        else if (/GB/i.test(tail)) k = 1073741824;
        else if (/MB/i.test(tail)) k = 1048576;
        else if (/KB/i.test(tail)) k = 1024;
        return v * k;
    }

    // 日期：2026年11月14日 / 2026-11-14 12:34 / 2026/11/14 / 26/11/14
    function dateValue(s) {
        var m = s.match(/([0-9]{4})\s*[年\-/.]\s*([0-9]{1,2})\s*[月\-/.]\s*([0-9]{1,2})/);
        var y, mo, d;
        if (m) {
            y = +m[1]; mo = +m[2]; d = +m[3];
        } else {
            m = s.match(/([0-9]{2})\/([0-9]{1,2})\/([0-9]{1,2})/);
            if (!m) return null;
            y = 2000 + (+m[1]); mo = +m[2]; d = +m[3];
        }
        if (mo < 1 || mo > 12 || d < 1 || d > 31) return null;
        var hm = s.match(/([0-9]{1,2}):([0-9]{2})(?::([0-9]{2}))?/);
        var h = hm ? +hm[1] : 0, mi = hm ? +hm[2] : 0, se = (hm && hm[3]) ? +hm[3] : 0;
        var ts = Date.UTC(y, mo - 1, d, h, mi, se);
        return isFinite(ts) ? ts : null;
    }

    // 逐列判断是否为「数值 / 日期」列；任何一格不是同类数值即放弃该列
    function buildInfos(rows, n) {
        var out = [];
        for (var c = 0; c < n; c++) {
            var vals = new Array(rows.length);
            var ok = true, hasDate = false, hasNum = false, cnt = 0;
            for (var i = 0; i < rows.length; i++) {
                vals[i] = { v: null };
                if (rows[i].cells.length !== n) continue;
                var t = text(rows[i].cells[c]);
                if (EMPTY.test(t)) continue;
                var dv = dateValue(t);
                if (dv !== null) { vals[i] = { v: dv, k: 'date' }; hasDate = true; cnt++; continue; }
                var nv = numValue(t);
                if (nv !== null) { vals[i] = { v: nv, k: 'num' }; hasNum = true; cnt++; continue; }
                ok = false; break;
            }
            out[c] = (ok && cnt >= 2 && !(hasDate && hasNum)) ? { vals: vals, init: detectDir(vals) } : null;
        }
        return out;
    }

    // 当前显示方向：非降序=asc，非递增=desc，杂乱=mixed
    function detectDir(vals) {
        var last = null, asc = true, desc = true, seen = 0;
        for (var i = 0; i < vals.length; i++) {
            var v = vals[i].v;
            if (v === null) continue;
            if (last !== null) {
                if (v < last) asc = false;
                if (v > last) desc = false;
            }
            last = v; seen++;
        }
        if (seen < 2) return 'mixed';
        if (asc) return 'asc';
        if (desc) return 'desc';
        return 'mixed';
    }

    function storageKey(table) {
        return 'gb-sort:' + location.pathname + ':' + headCells(table).map(text).join('|');
    }

    function save(table, col, dir) {
        try { sessionStorage.setItem(storageKey(table), col + ':' + dir); } catch (e) { /* 隐私模式忽略 */ }
    }

    function apply(table, col, dir) {
        var ths = headCells(table), body = table.tBodies[0];
        if (!body || !ths.length) return;
        var rows = Array.prototype.slice.call(body.rows);
        var infos = buildInfos(rows, ths.length);
        if (!infos[col]) return;
        var pairs = [], tail = [];
        rows.forEach(function (r, i) {
            if (r.cells.length !== ths.length) { tail.push(r); return; }
            pairs.push({ r: r, v: infos[col].vals[i].v, i: i });
        });
        pairs.sort(function (a, b) {
            if (a.v === null && b.v === null) return a.i - b.i;
            if (a.v === null) return 1;      // 空值恒排最后
            if (b.v === null) return -1;
            if (a.v === b.v) return a.i - b.i;
            return dir === 'asc' ? a.v - b.v : b.v - a.v;
        });
        var frag = document.createDocumentFragment();
        pairs.forEach(function (p) { frag.appendChild(p.r); });
        tail.forEach(function (r) { frag.appendChild(r); });
        body.appendChild(frag);

        var after = buildInfos(Array.prototype.slice.call(body.rows), ths.length);
        ths.forEach(function (th, i) {
            if (!th.classList.contains('gb-sortable')) return;
            th.classList.toggle('gb-sort-active', i === col);
            th.setAttribute('data-dir', i === col ? dir : (after[i] ? (after[i].init === 'mixed' ? '' : after[i].init) : ''));
        });
        table._gbSort = { col: col, dir: dir };
        save(table, col, dir);
    }

    function toggle(table, col) {
        var st = table._gbSort || null;
        if (st && st.col === col) {
            // 同列再点：降序 ↔ 升序翻转
            apply(table, col, st.dir === 'desc' ? 'asc' : 'desc');
            return;
        }
        // 新列首次点击一律「从大到小」（9、8、7、6、5），再点回「从小到大」。
        // 后台列表默认按时间 / ID 倒序（最新在前），不能用当前 DOM 顺序推断首击方向，
        // 否则点一次反而变成升序，与约定相反。默认 SQL 顺序保持不变。
        apply(table, col, 'desc');
    }

    function initTable(table) {
        if (table.getAttribute('data-gb-sort-init') === '1') return;
        var ths = headCells(table), body = table.tBodies[0];
        if (!ths.length || !body || body.rows.length < 2) {
            table.setAttribute('data-gb-sort-init', '1');
            return;
        }
        var infos = buildInfos(Array.prototype.slice.call(body.rows), ths.length);
        var any = false;
        ths.forEach(function (th, c) {
            if (!infos[c] || SKIP_HEADER.test(text(th))) return;
            any = true;
            th.classList.add('gb-sortable');
            th.setAttribute('data-dir', infos[c].init === 'mixed' ? '' : infos[c].init);
            th.setAttribute('title', '点击按此列排序（当前页）');
            th.setAttribute('tabindex', '0');
            th.setAttribute('role', 'button');
            th.addEventListener('click', function () { toggle(table, c); });
            th.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggle(table, c); }
            });
        });
        table.setAttribute('data-gb-sort-init', '1');
        if (!any) return;
        try {
            var raw = sessionStorage.getItem(storageKey(table));
            if (raw) {
                var p = raw.split(':');
                var col = parseInt(p[0], 10), dir = p[1];
                if (infos[col] && (dir === 'asc' || dir === 'desc')) apply(table, col, dir);
            }
        } catch (e) { /* 忽略 */ }
    }

    function scan() {
        var list = document.querySelectorAll('table');
        for (var i = 0; i < list.length; i++) initTable(list[i]);
    }

    function boot() {
        if (!document.getElementById('gb-sort-style')) {
            var s = document.createElement('style');
            s.id = 'gb-sort-style';
            s.textContent = CSS;
            (document.head || document.documentElement).appendChild(s);
        }
        scan();
        // 无刷新 tab 替换表格时重新初始化（已初始化的表会直接跳过）
        if (typeof MutationObserver !== 'undefined') {
            var pending = null;
            var mo = new MutationObserver(function () {
                if (pending) return;
                pending = setTimeout(function () { pending = null; scan(); }, 80);
            });
            mo.observe(document.documentElement, { childList: true, subtree: true });
        }
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();
})();
