<?php
/**
 * ONE FILE TO EDIT PER NGO.
 * Every template reads from this array, so you only change the details here
 * and pick whichever design you like. No database needed.
 */
$ngo = [
    'name'     => 'Aagahi Foundation',
    'short'    => 'Aagahi',
    'logo'     => '🌱',                       // emoji, or leave and use 'logo_img'
    'logo_img' => '',                         // e.g. 'assets/logo.png' (optional)
    'tagline'  => 'Every child deserves a chance to rise.',
    'mission'  => 'We work with underserved communities to bring quality education, healthcare, '
                . 'clean water and livelihoods to families who need it most.',
    'hero_image' => '',                       // optional full URL/path to a real photo
    'email'    => 'hello@example.org',
    'phone'    => '+91 98765 43210',
    'address'  => 'Delhi, India',
    'donate_url' => '#donate',                // payment page / UPI / Razorpay link
    'reg_note' => 'Non-profit organisation • Delhi, India',

    'stats' => [
        ['value' => 12500, 'suffix' => '+', 'label' => 'Lives touched'],
        ['value' => 48,    'suffix' => '',  'label' => 'Villages served'],
        ['value' => 320,   'suffix' => '',  'label' => 'Volunteers'],
        ['value' => 15,    'suffix' => '',  'label' => 'Years of service'],
    ],

    'programs' => [
        ['icon' => '📚', 'title' => 'Education',   'text' => 'Free learning centres, scholarships and school kits for first-generation learners.'],
        ['icon' => '🩺', 'title' => 'Healthcare',  'text' => 'Mobile clinics, health camps and nutrition support for women and children.'],
        ['icon' => '💧', 'title' => 'Clean Water', 'text' => 'Hand pumps, filters and sanitation so every home has safe drinking water.'],
        ['icon' => '🧵', 'title' => 'Livelihood',  'text' => 'Skill training and micro-enterprise help that turns learning into income.'],
        ['icon' => '👩‍🌾', 'title' => 'Women Empowerment', 'text' => 'Self-help groups, legal aid and leadership programmes run by local women.'],
        ['icon' => '🌳', 'title' => 'Environment', 'text' => 'Tree plantation and solar lighting for climate-resilient communities.'],
    ],

    'stories' => [
        ['name' => 'Meena, 14',        'role' => 'Scholar, Class 9',   'quote' => 'I was the first girl in my family to hold a school bag. Now I want to be a doctor.'],
        ['name' => 'Ramesh Kumar',     'role' => 'Tailor & trainer',   'quote' => 'The training gave me a machine and the confidence to start my own shop.'],
        ['name' => 'Sunita Devi',      'role' => 'Self-help group lead','quote' => 'Today our village women decide how our money is used. That is real change.'],
    ],

    'donate_amounts' => [500, 1000, 2500, 5000],
    'donate_goal'    => 500000,
    'donate_raised'  => 342000,

    'social' => [
        'Facebook'  => '#',
        'Instagram' => '#',
        'YouTube'   => '#',
        'X'         => '#',
    ],
];
