<?php
/**
 * includes/sanitize.php — makes blog HTML safe. Only a short list of tags and attributes survives; scripts, styles,
 * frames, forms, event handlers (onclick=...) and javascript: links are removed. Used on everything staff type in the editor.
 */

function sanitize_html(string $html): string
{
    $html = trim($html);
    if ($html === '') return '';
    static $tags = ['p','br','hr','h2','h3','h4','h5','h6','blockquote','pre','code','strong','b','em','i','u','s','sub','sup','mark','small',
        'ul','ol','li','a','img','figure','figcaption','table','thead','tbody','tfoot','tr','th','td','caption','span','div'];
    static $drop = ['script','style','iframe','object','embed','form','input','button','textarea','select','option','link','meta','noscript','svg','math','base','template','frame','frameset','applet','audio','video','canvas'];
    static $attrs = ['a' => ['href', 'title', 'target', 'rel'], 'img' => ['src', 'alt', 'title', 'width', 'height', 'loading'],
                     'th' => ['colspan', 'rowspan', 'scope'], 'td' => ['colspan', 'rowspan']];

    $prev = libxml_use_internal_errors(true);
    $dom = new DOMDocument('1.0', 'UTF-8');
    $dom->loadHTML('<?xml encoding="UTF-8"><div id="sanitize-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    $root = $dom->getElementById('sanitize-root');
    if (!$root) return '';

    $safeUrl = static function (string $u): string {
        $u = trim(html_entity_decode($u, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $probe = preg_replace('/[\x00-\x20\x7f]+/', '', $u) ?? '';
        if ($probe === '') return '';
        if (preg_match('/^([a-z][a-z0-9+.\-]*):/i', $probe, $m)) return in_array(strtolower($m[1]), ['http', 'https', 'mailto', 'tel'], true) ? $u : '';
        return $u;                                   // relative link, #anchor or /path
    };

    $walk = static function (DOMNode $node) use (&$walk, $tags, $drop, $attrs, $safeUrl): void {
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMComment || $child instanceof DOMProcessingInstruction) { $node->removeChild($child); continue; }
            if (!($child instanceof DOMElement)) continue;
            $name = strtolower($child->nodeName);
            if (in_array($name, $drop, true)) { $node->removeChild($child); continue; }
            $walk($child);                           // clean the inside first
            if (!in_array($name, $tags, true)) {     // unknown tag: keep its text, drop the tag
                while ($child->firstChild) $node->insertBefore($child->firstChild, $child);
                $node->removeChild($child);
                continue;
            }
            $allowed = $attrs[$name] ?? [];
            foreach (iterator_to_array($child->attributes) as $a) {
                $an = strtolower($a->nodeName);
                if ($an === 'class' && preg_match('/^[A-Za-z0-9_\- ]{0,80}$/', $a->nodeValue)) continue;   // plain class names only
                if (!in_array($an, $allowed, true)) { $child->removeAttribute($a->nodeName); continue; }
                if ($an === 'href' || $an === 'src') {
                    $v = $safeUrl($a->nodeValue);
                    if ($v === '') $child->removeAttribute($a->nodeName); else $child->setAttribute($a->nodeName, $v);
                } elseif ($an === 'target' && $a->nodeValue !== '_blank') {
                    $child->removeAttribute('target');
                } elseif ($an === 'loading' && !in_array($a->nodeValue, ['lazy', 'eager'], true)) {
                    $child->removeAttribute('loading');
                } elseif (in_array($an, ['width', 'height', 'colspan', 'rowspan'], true) && !ctype_digit((string)$a->nodeValue)) {
                    $child->removeAttribute($a->nodeName);
                }
            }
            if ($name === 'a' && $child->getAttribute('target') === '_blank') $child->setAttribute('rel', 'noopener noreferrer');
            if ($name === 'img' && !$child->hasAttribute('src')) $node->removeChild($child);
        }
    };
    $walk($root);

    $out = '';
    foreach ($root->childNodes as $n) $out .= $dom->saveHTML($n);
    return trim($out);
}

/** Plain text of some HTML (for word counts and excerpts). */
function html_text(string $html): string
{
    $t = html_entity_decode(strip_tags(preg_replace('/<(script|style)[\s\S]*?<\/\1>/i', ' ', $html) ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return trim(preg_replace('/\s+/u', ' ', $t) ?? '');
}

function make_excerpt(string $html, int $len = 180): string
{
    $t = html_text($html);
    return mb_strlen($t) <= $len ? $t : rtrim(mb_substr($t, 0, $len - 1)) . '…';
}

function reading_minutes(string $html): int
{
    return max(1, (int)round(str_word_count(html_text($html)) / 200));
}

function slugify_title(string $s): string
{
    $s = strtolower(trim(str_replace('&', ' and ', $s)));
    $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
    return substr(trim($s, '-'), 0, 160) ?: 'post';
}
