<?php
/** Past-events gallery. Data lives in events.json (images in assets/img/<slug>/NN.webp + NN-t.webp). */
const VISIBLE_PICS = 6;

function events_data(): array
{
    return json_decode(file_get_contents(__DIR__ . '/events.json'), true)['areas'];
}

function events_html(): string
{
    $o = '';
    foreach (events_data() as $area) {
        $o .= '<div class="area reveal" id="' . e($area['id']) . '"><h3>' . e($area['title']) . '</h3><p class="intro">' . e($area['intro']) . '</p>';
        foreach ($area['programs'] as $p) {
            $n = count($p['images']);
            $o .= '<div class="event"><h4>' . e($p['title']) . '</h4><p>' . e($p['text']) . '</p><div class="pics">';
            foreach ($p['images'] as $k => $im) {
                $base = 'assets/img/' . $p['slug'] . '/' . $im['n'];
                $o .= '<a href="' . $base . '.webp"' . ($k >= VISIBLE_PICS ? ' class="extra"' : '') . '><img src="' . $base . '-t.webp" width="' . (int)$im['w'] . '" height="' . (int)$im['h'] . '" alt="' . e($im['alt']) . '" loading="lazy"></a>';
            }
            $o .= '</div>';
            if ($n > VISIBLE_PICS) {
                $label = 'Show all ' . $n . ' photos';
                $o .= '<button class="more" type="button" data-label="' . $label . '">' . $label . '</button>';
            }
            $o .= '</div>';
        }
        $o .= '</div>';
    }
    return $o;
}
