<?php
// ─── User Management ──────────────────────────────────────────────────────
// แก้ไขไฟล์นี้เพื่อเพิ่ม/ลบ/เปลี่ยนรหัสผ่าน user
//
// Password Format (2 แบบ — ใช้ได้ทั้งคู่):
//   1. Plain text (สำหรับ setup ครั้งแรก — เร็ว แต่ไม่ปลอดภัย)
//   2. bcrypt hash (ใช้อยู่ตอนนี้)
//
// วิธี generate bcrypt hash ใหม่:
//   docker exec inventorium-web-1 php -r "echo password_hash('your_password', PASSWORD_DEFAULT), PHP_EOL;"
//
// Roles:
//   - 'admin'      : เห็น audit log + ทุกอย่าง
//   - 'accounting' : ทำงานทั่วไป
//   - 'purchase'   : ฝ่ายจัดซื้อ
//   - 'viewer'     : read-only

$USERS = [
    'tpp' => [
        'pass' => '$2y$10$Sej4O6lxQ6ipjPJNlH/Ju.TEw9UkMjSxumhkmiri3v7ltXhNqyppa', // adminPav#6A
        'name' => 'TPP Admin',
        'role' => 'admin',
    ],
    'account' => [
        'pass' => '$2y$10$t2YKZFRecPsPSRvsunhZYeNDb.cQp1VC1Viq3.9trvWfaCgKdS.3e', // acc@Pav#2B
        'name' => 'Accounting',
        'role' => 'accounting',
    ],
    'purchase' => [
        'pass' => '$2y$10$eFSgZkd3SPhQmwwvs8PI2eV4iis1TtouryWsktHKO8eigGs/S48q6', // pc@Pav#2B
        'name' => 'Purchase',
        'role' => 'purchase',
    ],
    'cost' => [
        'pass' => '$2y$10$bfTvekDB9Dy9UYLu/NjE2.hGbkS16d6hYBiXx4ng3adqI5WOWJrge', // cost@Pav#9C
        'name' => 'Cost Control',
        'role' => 'accounting',
    ],
    'viewer' => [
        'pass' => '$2y$10$k2K1tbP9WuDc7jHPwwVNfublz5gQdQwUf177eZukq8FkXutxrG0gC', // inv2026view
        'name' => 'Viewer',
        'role' => 'viewer',
    ],
];
