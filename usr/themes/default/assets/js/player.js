(function () {
    'use strict';

    function parseLrc(text) {
        var lines = text.split('\n');
        var items = [];
        var plain = [];

        for (var i = 0; i < lines.length; i++) {
            var line = lines[i].replace(/\r$/, '');
            var m = line.match(/^\s*\[(\d{1,2}):(\d{1,2})(?:\.(\d{1,3}))?\]\s*(.*)$/);
            if (m) {
                var sec = parseInt(m[1], 10) * 60 + parseInt(m[2], 10);
                if (m[3]) {
                    var frac = parseInt(m[3], 10);
                    var digits = m[3].length;
                    sec += frac / Math.pow(10, digits);
                }
                var lyric = m[4].trim();
                if (lyric !== '') {
                    items.push({ time: sec, text: lyric });
                }
            } else if (line.trim() !== '') {
                plain.push(line.trim());
            }
        }

        items.sort(function (a, b) { return a.time - b.time; });
        return { timed: items, plain: plain, isLrc: items.length > 0 };
    }

    function initPlayer(player) {
        var audio = player.querySelector('.mp-audio');
        var lyricsEl = player.querySelector('.mp-lyrics');
        if (!audio) { return; }

        if (lyricsEl) {
            var srcEl = lyricsEl.querySelector('.mp-lyrics-src');
            var inner = lyricsEl.querySelector('.mp-lyrics-inner');
            var parsed = srcEl ? parseLrc(srcEl.textContent) : { timed: [], plain: [] };

            if (parsed.isLrc) {
                var frag = document.createDocumentFragment();
                parsed.timed.forEach(function (item) {
                    var div = document.createElement('div');
                    div.className = 'mp-lyric-line';
                    div.setAttribute('data-time', item.time.toFixed(3));
                    div.textContent = item.text;
                    frag.appendChild(div);
                });
                inner.appendChild(frag);

                var lines = Array.prototype.slice.call(inner.querySelectorAll('.mp-lyric-line'));
                var currentIndex = -1;

                audio.addEventListener('timeupdate', function () {
                    var t = audio.currentTime;
                    var idx = -1;
                    for (var i = 0; i < lines.length; i++) {
                        if (parseFloat(lines[i].getAttribute('data-time')) <= t) { idx = i; } else { break; }
                    }
                    if (idx !== currentIndex) {
                        if (currentIndex >= 0 && lines[currentIndex]) {
                            lines[currentIndex].classList.remove('active');
                        }
                        if (idx >= 0) {
                            lines[idx].classList.add('active');
                            var lineEl = lines[idx];
                            var top = lineEl.offsetTop;
                            var box = lyricsEl;
                            var mid = box.clientHeight / 2 - lineEl.clientHeight / 2;
                            box.scrollTop = top - mid;
                        }
                        currentIndex = idx;
                    }
                });
            } else {
                var frag2 = document.createDocumentFragment();
                parsed.plain.forEach(function (line) {
                    var div = document.createElement('div');
                    div.className = 'mp-lyric-line';
                    div.textContent = line;
                    frag2.appendChild(div);
                });
                inner.appendChild(frag2);
            }
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        var players = document.querySelectorAll('.music-player');
        for (var i = 0; i < players.length; i++) {
            initPlayer(players[i]);
        }
    });
})();
