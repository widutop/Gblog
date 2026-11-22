<?php

if (!function_exists('gblog_music_player_palette')) {
    function gblog_color_darken(string $hex, float $factor): string {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        if (strlen($hex) !== 6) return $hex;
        $r = max(0, min(255, (int)(hexdec(substr($hex, 0, 2)) * $factor)));
        $g = max(0, min(255, (int)(hexdec(substr($hex, 2, 2)) * $factor)));
        $b = max(0, min(255, (int)(hexdec(substr($hex, 4, 2)) * $factor)));
        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }
    function gblog_color_luminance(string $hex): float {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        if (strlen($hex) !== 6) return 0.0;
        $c = hexdec($hex);
        $rf = (($c >> 16) & 0xff) / 255; $gf = (($c >> 8) & 0xff) / 255; $bf = ($c & 0xff) / 255;
        $lin = function ($x) { return $x <= 0.03928 ? $x / 12.92 : pow(($x + 0.055) / 1.055, 2.4); };
        return 0.2126 * $lin($rf) + 0.7152 * $lin($gf) + 0.0722 * $lin($bf);
    }

    function gblog_music_player_palette(string $theme, ?string $customColor = null): array {
        if ($theme === 'custom' && $customColor && preg_match('/^#[0-9a-fA-F]{6}$/', $customColor)) {
            $c = strtolower($customColor);
            $light = gblog_color_luminance($c) > 0.5;
            $fg = $light ? '#1f2329' : '#ffffff';
            return [
                'bg'     => $c,
                'bg2'    => gblog_color_darken($c, 0.85),
                'fg'     => $fg,
                'fgDim'  => $light ? 'rgba(31,35,41,.6)' : 'rgba(255,255,255,.8)',
                'chip'   => '#ffffff',
                'chipFg' => $c,
                'track'  => $light ? 'rgba(0,0,0,.12)' : 'rgba(255,255,255,.3)',
                'bar'    => $light ? 'rgba(0,0,0,.3)' : 'rgba(255,255,255,.55)',
                'panel'  => $light ? 'rgba(0,0,0,.06)' : 'rgba(255,255,255,.15)',
            ];
        }
        $palettes = [
            'mono' => [
                'bg'     => '#f4f4f5',
                'bg2'    => '#e4e4e7',
                'fg'     => '#1f2329',
                'fgDim'  => 'rgba(31,35,41,.55)',
                'chip'   => '#ffffff',
                'chipFg' => '#1f2329',
                'track'  => 'rgba(0,0,0,.12)',
                'bar'    => 'rgba(0,0,0,.30)',
                'panel'  => 'rgba(0,0,0,.06)',
            ],
            'blue' => [
                'bg'     => '#1d6fe0',
                'bg2'    => '#155bb5',
                'fg'     => '#ffffff',
                'fgDim'  => 'rgba(255,255,255,.8)',
                'chip'   => '#ffffff',
                'chipFg' => '#1d6fe0',
                'track'  => 'rgba(255,255,255,.3)',
                'bar'    => 'rgba(255,255,255,.55)',
                'panel'  => 'rgba(255,255,255,.15)',
            ],
            'purple' => [
                'bg'     => '#7c3aed',
                'bg2'    => '#6d28d9',
                'fg'     => '#ffffff',
                'fgDim'  => 'rgba(255,255,255,.8)',
                'chip'   => '#ffffff',
                'chipFg' => '#7c3aed',
                'track'  => 'rgba(255,255,255,.3)',
                'bar'    => 'rgba(255,255,255,.55)',
                'panel'  => 'rgba(255,255,255,.15)',
            ],
            'green' => [
                'bg'     => '#16a34a',
                'bg2'    => '#15803d',
                'fg'     => '#ffffff',
                'fgDim'  => 'rgba(255,255,255,.8)',
                'chip'   => '#ffffff',
                'chipFg' => '#16a34a',
                'track'  => 'rgba(255,255,255,.3)',
                'bar'    => 'rgba(255,255,255,.55)',
                'panel'  => 'rgba(255,255,255,.15)',
            ],
            'glass' => [
                'bg'     => 'transparent',
                'bg2'    => 'transparent',
                'fg'     => '#1f2329',
                'fgDim'  => 'rgba(31,35,41,.6)',
                'chip'   => 'transparent',
                'chipFg' => '#1f2329',
                'track'  => 'rgba(0,0,0,.15)',
                'bar'    => 'rgba(0,0,0,.35)',
                'panel'  => 'transparent',
                'border' => 'rgba(0,0,0,.22)',
            ],
            'white' => [
                'bg'     => '#ffffff',
                'bg2'    => '#f1f3f5',
                'fg'     => '#1f2329',
                'fgDim'  => 'rgba(31,35,41,.55)',
                'chip'   => '#1f2329',
                'chipFg' => '#ffffff',
                'track'  => 'rgba(0,0,0,.1)',
                'bar'    => 'rgba(0,0,0,.32)',
                'panel'  => 'rgba(0,0,0,.05)',
            ],
        ];
        return $palettes[$theme] ?? $palettes['mono'];
    }
}

if (!function_exists('gblog_theme_primary_color')) {
    function gblog_theme_primary_color(): string
    {
        static $cache = null;
        if ($cache !== null) return $cache;
        $cache = '#1d6fe0';
        if (!defined('THEMES_DIR') || !function_exists('gblog_active_theme_chain')) {
            return $cache;
        }
        foreach (gblog_active_theme_chain() as $theme) {
            $file = THEMES_DIR . '/' . $theme . '/assets/css/style.css';
            if (!is_file($file)) continue;
            $css = @file_get_contents($file);
            if ($css && preg_match('/--primary\s*:\s*#([0-9a-fA-F]{3,6})/', $css, $m)) {
                $hex = $m[1];
                if (strlen($hex) === 3) {
                    $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
                }
                $cache = '#' . strtolower($hex);
                break;
            }
        }
        return $cache;
    }
}
