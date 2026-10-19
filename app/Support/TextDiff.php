<?php

namespace App\Support;

/** Word-level diff (LCS) rendered as escaped HTML with <del>/<ins>. */
class TextDiff
{
    public static function html(string $old, string $new): string
    {
        $a = preg_split('/(\s+)/u', $old, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];
        $b = preg_split('/(\s+)/u', $new, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) ?: [];

        // Guard against pathological sizes: fall back to block replace.
        if (count($a) * count($b) > 4_000_000) {
            return '<del>'.nl2br(e($old)).'</del><ins>'.nl2br(e($new)).'</ins>';
        }

        $n = count($a);
        $m = count($b);
        $lcs = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $lcs[$i][$j] = $a[$i] === $b[$j] ? $lcs[$i + 1][$j + 1] + 1 : max($lcs[$i + 1][$j], $lcs[$i][$j + 1]);
            }
        }

        $out = '';
        $i = $j = 0;
        while ($i < $n && $j < $m) {
            if ($a[$i] === $b[$j]) {
                $out .= e($a[$i]);
                $i++;
                $j++;
            } elseif ($lcs[$i + 1][$j] >= $lcs[$i][$j + 1]) {
                $out .= '<del>'.e($a[$i++]).'</del>';
            } else {
                $out .= '<ins>'.e($b[$j++]).'</ins>';
            }
        }
        while ($i < $n) {
            $out .= '<del>'.e($a[$i++]).'</del>';
        }
        while ($j < $m) {
            $out .= '<ins>'.e($b[$j++]).'</ins>';
        }

        return nl2br(str_replace('</del><del>', '', str_replace('</ins><ins>', '', $out)));
    }
}
