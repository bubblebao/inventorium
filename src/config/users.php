<?php
// ─── User Management ──────────────────────────────────────────────────────
// แก้ไขไฟล์นี้เพื่อเพิ่ม/ลบ/เปลี่ยนรหัสผ่าน user
//
// Password Format (2 แบบ — ใช้ได้ทั้งคู่):
//   1. Plain text (สำหรับ setup ครั้งแรก — เร็ว แต่ไม่ปลอดภัย)
//   2. bcrypt hash (แนะนำ — secure) ← ใช้อยู่ตอนนี้
//
// วิธี generate bcrypt hash ใหม่:
//   docker exec inventorium-web-1 php -r "echo password_hash('your_password', PASSWORD_DEFAULT), PHP_EOL;"
//   แล้ว copy hash ที่ขึ้นต้นด้วย $2y$ มาแทนค่า pass
//
// Roles:
//   - 'admin'      : เห็น audit log + ทุกอย่าง
//   - 'accounting' : ทำงานทั่วไป
//   - 'purchase'   : ฝ่ายจัดซื้อ
//   - 'viewer'     : read-only

$USERS = [
    'tpp' => [
        'pass' => '$2y$10$xcEgz.Nyqof2GKYbv64gjeLrOOKIvwCOCFo253.osj4Q1Yf3y1A0W',  // adminPav#6A
        'name' => 'TPP Admin',
        'role' => 'admin',
    ],
    'account1' => [
        'pass' => '$2y$10$aKQ2Bi2.Pq/Yi2YYRQgUoeQJePJD8H4QPD16fTAW4qEyxfx80kcsW',  // cost@Pav#2B
        'name' => 'บัญชี 1',
        'role' => 'accounting',
    ],
    'purchase' => [
        'pass' => '$2y$10$aKQ2Bi2.Pq/Yi2YYRQgUoeQJePJD8H4QPD16fTAW4qEyxfx80kcsW',  // cost@Pav#2B
        'name' => 'Purchase',
        'role' => 'purchase',
    ],
    'cost' => [
        'pass' => '$2y$10$N4BsM/ucGAH8tAO3b9yCpOrBanwHw3PmYtQAgzT4umsYV6oSegz/.',  // cost@Pav#9C
        'name' => 'Cost Control',
        'role' => 'accounting',
    ],
    'viewer' => [
        'pass' => '$2y$10$KT8wO8b8aegPPd/uBXf1cuNZWHf5jofpp9LY3KAiXPgsDvuZ4QHde',  // inv2026view
        'name' => 'Viewer',
        'role' => 'viewer',
    ],
];
