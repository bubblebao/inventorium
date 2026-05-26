<?php
// ─── User Management ──────────────────────────────────────────────────────
// แก้ไขไฟล์นี้เพื่อเพิ่ม/ลบ/เปลี่ยนรหัสผ่าน user
//
// Password Format (2 แบบ — ใช้ได้ทั้งคู่):
//   1. Plain text (สำหรับ setup ครั้งแรก — เร็ว แต่ไม่ปลอดภัย)
//   2. bcrypt hash (แนะนำ — secure)
//
// วิธี generate bcrypt hash:
//   docker compose -f docker-compose.dev.yml exec web php -r "echo password_hash('your_password', PASSWORD_DEFAULT);"
//   แล้ว copy hash ที่ขึ้นต้นด้วย $2y$ มาแทนค่า pass
//
// Roles:
//   - 'admin'      : เห็น audit log + ทุกอย่าง
//   - 'accounting' : ทำงานทั่วไป
//   - 'purchase'   : ฝ่ายจัดซื้อ
//   - 'viewer'     : read-only

$USERS = [
    'tpp' => [
        'pass' => 'adminPav#6A',
        'name' => 'TPP Admin',
        'role' => 'admin',
    ],
    'account1' => [
        'pass' => 'cost@Pav#2B',
        'name' => 'บัญชี 1',
        'role' => 'accounting',
    ],
    'purchase' => [
        'pass' => 'cost@Pav#2B',
        'name' => 'Purchase',
        'role' => 'purchase',
    ],
    'cost' => [
        'pass' => 'cost@Pav#9C',
        'name' => 'Cost Control',
        'role' => 'accounting',
    ],
    'viewer' => [
        'pass' => 'inv2026view',
        'name' => 'Viewer',
        'role' => 'viewer',
    ],
];
