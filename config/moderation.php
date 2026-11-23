<?php
// config/moderation.php - semua angka batas moderasi ada di sini
return [
    // jeda (detik) dan batas per jam, per peran
    'limits' => [
        'guest'  => ['post_gap' => 60, 'post_hour' => 3,  'reply_gap' => 15, 'reply_hour' => 10],
        'user'   => ['post_gap' => 30, 'post_hour' => 10, 'reply_gap' => 5,  'reply_hour' => 30],
        'lawyer' => ['post_gap' => 15, 'post_hour' => 20, 'reply_gap' => 3,  'reply_hour' => 60],
    ],
    'score_pending'    => 3,   // skor 3-5: tamu jadi pending
    'score_reject'     => 6,   // skor >= 6: ditolak
    'min_form_seconds' => 3,
    'max_form_seconds' => 7200,
    'limited_gap'      => 300, // jeda saat poin efektif 4-5
    'points' => ['profanity' => 2, 'spam' => 2, 'pending' => 1, 'duplicate' => 1, 'bot' => 2],
    'mute_at' => 6, 'suspend_at' => 10,
];
