<?php
/** "Our work, in pictures": the event gallery on the home page. Data comes from the database (dashboard) or events.json. */
require_once __DIR__ . '/content.php';
const VISIBLE_PICS = 6;

function events_data(): array
{
    return content_events();
}

function events_html(): string
{
    $o = '';
    foreach (events_data() as $area) {
        $o .= '<div class="area reveal" id="' . e($area['id']) . '"><h3>' . e($area['title']) . '</h3>' . ($area['intro'] !== '' ? '<p class="intro">' . e($area['intro']) . '</p>' : '');
        foreach ($area['programs'] as $p) {
            $n = count($p['images']);
            $o .= '<div class="event"><h4>' . e($p['title']) . '</h4>' . ($p['text'] !== '' ? '<p>' . e($p['text']) . '</p>' : '');
            if ($n) {
                $o .= '<div class="pics">';
                foreach ($p['images'] as $k => $im) {
                    $o .= '<a href="' . e($im['src']) . '"' . ($k >= VISIBLE_PICS ? ' class="extra"' : '') . '><img src="' . e($im['thumb']) . '"'
                        . ($im['w'] && $im['h'] ? ' width="' . (int)$im['w'] . '" height="' . (int)$im['h'] . '"' : '') . ' alt="' . e($im['alt'] ?: $p['title']) . '" loading="lazy"></a>';
                }
                $o .= '</div>';
                if ($n > VISIBLE_PICS) {
                    $label = 'Show all ' . $n . ' photos';
                    $o .= '<button class="more" type="button" data-label="' . $label . '">' . $label . '</button>';
                }
            }
            $o .= '</div>';
        }
        $o .= '</div>';
    }
    return $o;
}
