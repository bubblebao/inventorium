<?php
// ─── i18n (TH/EN) ─────────────────────────────────────────────────────────
// ใช้: t('dashboard') → "Dashboard" หรือ "แดชบอร์ด"
// Switch: /?lang=en หรือ /?lang=th → เก็บ cookie 1 ปี

if (isset($_GET['lang']) && in_array($_GET['lang'], ['th', 'en'], true)) {
    setcookie('inv_lang', $_GET['lang'], time() + 86400 * 365, '/');
    $GLOBALS['LANG'] = $_GET['lang'];
} else {
    $GLOBALS['LANG'] = $_COOKIE['inv_lang'] ?? 'th';
}

$GLOBALS['TRANSLATIONS'] = [

    // ── Navigation / Menu ──
    'menu_main'         => ['th' => 'เมนูหลัก',         'en' => 'MAIN MENU'],
    'dashboard'         => ['th' => 'แดชบอร์ด',         'en' => 'Dashboard'],
    'po_list'           => ['th' => 'รายการ PO',         'en' => 'PO List'],
    'item_search'       => ['th' => 'ค้นหาสินค้า',       'en' => 'Items'],
    'vendor'            => ['th' => 'Vendor',            'en' => 'Vendor'],
    'dept_report'       => ['th' => 'รายแผนก',          'en' => 'By Location'],

    // ── Common UI ──
    'search'            => ['th' => 'ค้นหา',             'en' => 'Search'],
    'reset'             => ['th' => 'รีเซ็ต',            'en' => 'Reset'],
    'all'               => ['th' => 'ทั้งหมด',           'en' => 'All'],
    'today'             => ['th' => 'วันนี้',            'en' => 'Today'],
    'week'              => ['th' => 'สัปดาห์นี้',         'en' => 'This Week'],
    'month'             => ['th' => 'เดือนนี้',           'en' => 'This Month'],
    'last_month'        => ['th' => 'เดือนที่แล้ว',       'en' => 'Last Month'],
    'three_months'      => ['th' => '3 เดือน',           'en' => '3 Months'],
    'year'              => ['th' => 'ปีนี้',              'en' => 'This Year'],
    'date_from'         => ['th' => 'วันที่เริ่ม',         'en' => 'From'],
    'date_to'           => ['th' => 'วันที่สิ้นสุด',       'en' => 'To'],
    'period'            => ['th' => 'ช่วงเวลา',          'en' => 'Period'],
    'back'              => ['th' => 'ย้อนกลับ',          'en' => 'Back'],
    'view_all'          => ['th' => 'ดูทั้งหมด',          'en' => 'View All'],
    'no_data'           => ['th' => 'ไม่พบข้อมูล',        'en' => 'No data'],
    'loading'           => ['th' => 'กำลังโหลด...',       'en' => 'Loading...'],
    'records'           => ['th' => 'รายการ',            'en' => 'records'],
    'items_count'       => ['th' => 'รายการ',            'en' => 'items'],
    'baht'              => ['th' => 'บาท',               'en' => 'THB'],
    'days'              => ['th' => 'วัน',               'en' => 'days'],
    'recorded_by'       => ['th' => 'ผู้บันทึก',          'en' => 'Recorded by'],

    // ── Dashboard ──
    'po_this_month'     => ['th' => 'PO เดือนนี้',       'en' => 'POs This Month'],
    'total_this_month'  => ['th' => 'ยอดรวมเดือนนี้',    'en' => 'Total This Month'],
    'due_in_7_days'     => ['th' => 'ครบกำหนด 7 วัน',   'en' => 'Due in 7 Days'],
    'total_vendors'     => ['th' => 'Vendor ทั้งหมด',    'en' => 'Total Vendors'],
    'monthly_po_chart'  => ['th' => 'ยอด PO รายเดือน (12 เดือนล่าสุด)', 'en' => 'Monthly PO (Last 12 Months)'],
    'count_vs_total'    => ['th' => 'จำนวน / ยอด',       'en' => 'Count / Total'],
    'due_in_7'          => ['th' => 'ครบกำหนดใน 7 วัน',  'en' => 'Due in 7 Days'],
    'no_due_po'         => ['th' => 'ไม่มี PO ครบกำหนด',  'en' => 'No POs due'],
    'top_5_vendors'     => ['th' => 'Top 5 Vendor เดือนนี้', 'en' => 'Top 5 Vendors This Month'],
    'recent_10_pos'     => ['th' => 'PO ล่าสุด 10 รายการ', 'en' => 'Recent 10 POs'],
    'today_word'        => ['th' => 'วันนี้!',            'en' => 'Today!'],

    // ── Table columns ──
    'col_date'          => ['th' => 'วันที่',            'en' => 'Date'],
    'col_po_no'         => ['th' => 'เลข PO',           'en' => 'PO No.'],
    'col_ref'           => ['th' => 'Ref',              'en' => 'Ref'],
    'col_vendor'        => ['th' => 'Vendor',           'en' => 'Vendor'],
    'col_total'         => ['th' => 'ยอดรวม',          'en' => 'Total'],
    'col_due_date'      => ['th' => 'ครบกำหนด',        'en' => 'Due Date'],
    'col_loc'           => ['th' => 'Loc',              'en' => 'Loc'],
    'col_qty'           => ['th' => 'จำนวน',           'en' => 'Qty'],
    'col_unit'          => ['th' => 'หน่วย',           'en' => 'Unit'],
    'col_price'         => ['th' => 'ราคา/หน่วย',      'en' => 'Price/Unit'],
    'col_amount'        => ['th' => 'ยอดสุทธิ',         'en' => 'Amount'],

    // ── PO List ──
    'filter'            => ['th' => 'ค้นหา / กรอง',     'en' => 'Filter'],
    'placeholder_search'=> ['th' => 'ค้นหา...',         'en' => 'Search...'],
    'po_no_label'       => ['th' => 'เลข PO',          'en' => 'PO Number'],
    'location'          => ['th' => 'Location',         'en' => 'Location'],

    // ── Item History ──
    'search_item'       => ['th' => 'ค้นหาสินค้า',      'en' => 'Search Items'],
    'item_search_hint'  => ['th' => 'พิมพ์รหัสสินค้า, barcode หรือชื่อสินค้า เพื่อดูประวัติการซื้อ', 'en' => 'Type item code, barcode or name to view purchase history'],
    'item_search_sub'   => ['th' => 'เห็นได้ว่าซื้อจากใคร ตอนไหน กี่บาท', 'en' => 'See who you bought from, when, and at what price'],
    'view_history'      => ['th' => 'ดูประวัติ',         'en' => 'History'],
    'item_code'         => ['th' => 'รหัส',             'en' => 'Code'],
    'item_name'         => ['th' => 'ชื่อสินค้า',        'en' => 'Item Name'],
    'item_eng'          => ['th' => 'ชื่ออังกฤษ',        'en' => 'English Name'],
    'item_thai'         => ['th' => 'ชื่อไทย',          'en' => 'Thai Name'],
    'category'          => ['th' => 'หมวด',             'en' => 'Category'],
    'base_unit'         => ['th' => 'หน่วยฐาน',         'en' => 'Base Unit'],
    'default_vendor'    => ['th' => 'Vendor หลัก',      'en' => 'Default Vendor'],
    'default_price'     => ['th' => 'Default Price',    'en' => 'Default Price'],
    'last_cost'         => ['th' => 'Last Cost',        'en' => 'Last Cost'],
    'on_hand'           => ['th' => 'On Hand',          'en' => 'On Hand'],
    'last_update'       => ['th' => 'Last Update',      'en' => 'Last Update'],
    'total_purchase'    => ['th' => 'ซื้อทั้งหมด',       'en' => 'Total Purchases'],
    'times'             => ['th' => 'ครั้ง',             'en' => 'times'],
    'total_qty'         => ['th' => 'จำนวนรวม',         'en' => 'Total Qty'],
    'price_min_avg_max' => ['th' => 'ราคา min / avg / max', 'en' => 'Price min / avg / max'],
    'total_value'       => ['th' => 'มูลค่ารวม',         'en' => 'Total Value'],
    'top_vendors_item'  => ['th' => 'Top 5 Vendor ที่ขายสินค้านี้', 'en' => 'Top 5 Vendors for this Item'],
    'purchase_history'  => ['th' => 'ประวัติการซื้อ',     'en' => 'Purchase History'],
    'latest_200'        => ['th' => '200 ล่าสุด',        'en' => 'Latest 200'],
    'search_new'        => ['th' => 'ค้นหาสินค้าใหม่',   'en' => 'New Search'],
    'no_history'        => ['th' => 'ยังไม่มีประวัติการซื้อ', 'en' => 'No purchase history yet'],
    'price_legend'      => ['th' => 'สีของราคา: เขียว = ต่ำกว่าเฉลี่ย / แดง = สูงกว่าเฉลี่ย (เกณฑ์ ±5%)', 'en' => 'Price color: green = below avg / red = above avg (±5% threshold)'],
    'inactive'          => ['th' => 'Inactive',         'en' => 'Inactive'],

    // ── More page labels ──
    'po_list_records'   => ['th' => 'รายการ PO',        'en' => 'PO Records'],
    'po_credit'         => ['th' => 'เครดิต',           'en' => 'Credit'],
    'po_order_date'     => ['th' => 'วันที่สั่งซื้อ',    'en' => 'Order Date'],
    'po_delivery_date'  => ['th' => 'วันส่งของ',        'en' => 'Delivery Date'],
    'po_reference'      => ['th' => 'Reference',        'en' => 'Reference'],
    'po_remark'         => ['th' => 'หมายเหตุ',         'en' => 'Remark'],
    'po_pr_no'          => ['th' => 'เลข PR',          'en' => 'PR No.'],
    'po_items'          => ['th' => 'รายการสินค้า',     'en' => 'Items'],
    'po_invoice'        => ['th' => 'ใบสั่งซื้อ',        'en' => 'Purchase Order'],
    'po_total_all'      => ['th' => 'ยอดรวมทั้งหมด',    'en' => 'Grand Total'],
    'po_overdue'        => ['th' => 'เกินกำหนดส่งของ',  'en' => 'Delivery Overdue'],
    'po_due_soon'       => ['th' => 'ใกล้ครบกำหนด',     'en' => 'Due Soon'],
    'back_to_po_list'   => ['th' => 'กลับรายการ PO',    'en' => 'Back to PO List'],
    'view_vendor'       => ['th' => 'ประวัติ Vendor',   'en' => 'Vendor History'],
    'print'             => ['th' => 'พิมพ์',            'en' => 'Print'],
    'subtotal'          => ['th' => 'รวม',              'en' => 'Subtotal'],
    'col_seq'           => ['th' => '#',                'en' => '#'],
    'col_prdid'         => ['th' => 'รหัสสินค้า',       'en' => 'Item Code'],
    'col_desc'          => ['th' => 'รายละเอียด',       'en' => 'Description'],
    'col_before_tax'    => ['th' => 'ก่อนภาษี',         'en' => 'Before Tax'],
    'col_vat'           => ['th' => 'VAT',              'en' => 'VAT'],
    'col_discount'      => ['th' => 'ส่วนลด',           'en' => 'Discount'],
    'col_net'           => ['th' => 'ยอดสุทธิ',         'en' => 'Net Amount'],

    // ── Vendor List/Detail ──
    'vendor_list_title' => ['th' => 'รายชื่อ Vendor',   'en' => 'Vendor List'],
    'vendor_search'     => ['th' => 'ค้นหา Vendor',     'en' => 'Search Vendor'],
    'vendor_search_ph'  => ['th' => 'ชื่อ หรือ รหัส Vendor...', 'en' => 'Name or Vendor Code...'],
    'vendor_payee'      => ['th' => 'ผู้รับเงิน',        'en' => 'Payee'],
    'vendor_address'    => ['th' => 'ที่อยู่',            'en' => 'Address'],
    'vendor_phone'      => ['th' => 'โทรศัพท์',         'en' => 'Phone'],
    'vendor_mobile'     => ['th' => 'มือถือ',           'en' => 'Mobile'],
    'vendor_email'      => ['th' => 'Email',            'en' => 'Email'],
    'vendor_taxno'      => ['th' => 'Tax No',           'en' => 'Tax ID'],
    'vendor_balance'    => ['th' => 'ยอดค้างชำระ',      'en' => 'Outstanding'],
    'vendor_credit'     => ['th' => 'เครดิต (วัน)',     'en' => 'Credit (days)'],
    'vendor_last_close' => ['th' => 'Last Close',       'en' => 'Last Close'],
    'vendor_code'       => ['th' => 'รหัส',             'en' => 'Code'],
    'vendor_name'       => ['th' => 'ชื่อ Vendor',      'en' => 'Vendor Name'],
    'po_history'        => ['th' => 'ประวัติ PO',       'en' => 'PO History'],
    'latest_100'        => ['th' => '100 ล่าสุด',       'en' => 'Latest 100'],
    'back_to_vendors'   => ['th' => 'กลับรายชื่อ Vendor','en' => 'Back to Vendor List'],
    'po_this_year'      => ['th' => 'PO ปีนี้',         'en' => 'POs This Year'],
    'po_all_time'       => ['th' => 'ทั้งหมด',          'en' => 'All Time'],

    // ── Item History extras ──
    'item_history_title'=> ['th' => 'ค้นหาสินค้า',     'en' => 'Search Items'],
    'item_search_label' => ['th' => 'รหัสสินค้า / Barcode / ชื่อสินค้า', 'en' => 'Item Code / Barcode / Name'],
    'item_search_ph'    => ['th' => 'พิมพ์รหัส, barcode หรือชื่อสินค้า...', 'en' => 'Type code, barcode or item name...'],
    'item_results'      => ['th' => 'ผลค้นหา',          'en' => 'Results for'],
    'no_items'          => ['th' => 'ไม่พบสินค้า',       'en' => 'No items found'],
    'back_to_search'    => ['th' => 'กลับการค้นหา',     'en' => 'Back to Search'],

    // ── Dept Report ──
    'dept_report_title' => ['th' => 'รายแผนก (ตาม Location)','en' => 'Report by Location'],
    'date_range'        => ['th' => 'ช่วงวันที่',         'en' => 'Date Range'],
    'view_report'       => ['th' => 'ดูรายงาน',         'en' => 'View Report'],
    'no_data_period'    => ['th' => 'ไม่พบข้อมูลในช่วงวันที่เลือก','en' => 'No data in selected period'],
    'dept_pie'          => ['th' => 'สัดส่วนยอดแต่ละ Location','en' => 'Share by Location'],
    'dept_report_by'    => ['th' => 'รายงานตาม Location','en' => 'Report by Location'],
    'col_po_count'      => ['th' => 'จำนวน PO',         'en' => 'PO Count'],
    'col_total_pct'     => ['th' => '% ของรวม',         'en' => '% of Total'],
    'col_proportion'    => ['th' => 'สัดส่วน',          'en' => 'Proportion'],
    'col_total_all'     => ['th' => 'รวมทั้งหมด',       'en' => 'Total'],
    'not_specified'     => ['th' => '(ไม่ระบุ)',         'en' => '(N/A)'],

    // ── Historical Dashboard ──
    'total_pos'         => ['th' => 'PO ทั้งหมด',        'en' => 'Total POs'],
    'total_spending'    => ['th' => 'ยอดซื้อทั้งหมด',     'en' => 'Total Spending'],
    'latest_po'         => ['th' => 'PO ล่าสุด',          'en' => 'Latest PO'],
    'active_vendors'    => ['th' => 'Vendor ที่ใช้งาน',   'en' => 'Active Vendors'],
    'all_time'          => ['th' => 'ตลอดกาล',           'en' => 'All Time'],
    'last_12_months'    => ['th' => '12 เดือนล่าสุด',      'en' => 'Last 12 Months'],
    'months_ago'        => ['th' => 'เดือนที่แล้ว',        'en' => 'months ago'],
    'days_ago'          => ['th' => 'วันที่แล้ว',           'en' => 'days ago'],
    'just_now'          => ['th' => 'ล่าสุด',             'en' => 'recent'],
    'top_5_locations'   => ['th' => 'Top 5 Locations',   'en' => 'Top 5 Locations'],
    'top_5_vendors_12m' => ['th' => 'Top 5 Vendor (12 เดือนล่าสุด)', 'en' => 'Top 5 Vendors (Last 12 Months)'],
    'monthly_chart_12m' => ['th' => 'ยอด PO รายเดือน (12 เดือนล่าสุด)', 'en' => 'Monthly PO (Last 12 Months)'],
    'data_range'        => ['th' => 'ข้อมูลถึง',           'en' => 'Data through'],
    'data_freeze_note'  => ['th' => '⚠ ข้อมูลหยุดที่วันนี้ (อ่านอย่างเดียว)', 'en' => '⚠ Data frozen on this date (read-only)'],
    'all_locations'     => ['th' => 'ทุก Location',       'en' => 'All Locations'],
    'million_thb'       => ['th' => 'ล้านบาท',            'en' => 'Million THB'],
    'billion_thb'       => ['th' => 'พันล้านบาท',         'en' => 'Billion THB'],

    // ── Feature 2: Dept Report drill-down ──
    'view_pos_for_loc'  => ['th' => 'ดู PO ของ Location นี้', 'en' => 'View POs for this location'],
    'export_report'     => ['th' => 'Export รายงาน',      'en' => 'Export Report'],

    // ── Feature 3: Yearly Chart ──
    'yearly_chart'      => ['th' => 'ยอดซื้อรายปี (ทุกปี)', 'en' => 'Yearly Spending (All Years)'],
    'click_to_drill'    => ['th' => 'คลิกแท่งเพื่อดูรายละเอียดปีนั้น', 'en' => 'Click bar to view year detail'],

    // ── Feature 5: Admin Snapshot ──
    'snapshot_title'    => ['th' => 'สร้าง DB Snapshot ใหม่', 'en' => 'Generate DB Snapshot'],
    'snapshot_btn'      => ['th' => 'ดาวน์โหลด Snapshot ใหม่', 'en' => 'Download New Snapshot'],
    'snapshot_running'  => ['th' => 'กำลังสร้าง... โปรดรอ', 'en' => 'Generating... Please wait'],
    'snapshot_done'     => ['th' => 'เสร็จแล้ว! กำลังดาวน์โหลด', 'en' => 'Done! Downloading'],
    'snapshot_warning'  => ['th' => '⚠ อาจใช้เวลา 1–3 นาที (ข้อมูลเกือบล้านแถว)', 'en' => '⚠ May take 1–3 minutes (nearly 1M rows)'],
    'snapshot_note'     => ['th' => 'วิธีใช้งาน: นำไฟล์ที่ได้ไปเปลี่ยนที่ db-init/snapshot.sql.gz แล้ว push ขึ้น git', 'en' => 'Usage: Replace db-init/snapshot.sql.gz with downloaded file, then push to git'],
    'snapshot_tables'   => ['th' => 'ตารางที่ dump: invpo0, invpo1, gblvend, gblprod', 'en' => 'Tables: invpo0, invpo1, gblvend, gblprod'],
    'snapshot_nav'      => ['th' => 'Re-Snapshot DB',     'en' => 'Re-Snapshot DB'],
    'snapshot_stats'    => ['th' => 'สถิติข้อมูลปัจจุบัน', 'en' => 'Current Data Stats'],

    // ── Receiving (รับสินค้า) ──
    'recv_nav'          => ['th' => 'รับสินค้า',          'en' => 'Receiving'],
    'recv_list_title'   => ['th' => 'รายการรับสินค้า',     'en' => 'Receiving List'],
    'recv_records'      => ['th' => 'ใบรับสินค้า',         'en' => 'Receiving Records'],
    'recv_detail_title' => ['th' => 'รายละเอียดการรับสินค้า', 'en' => 'Receiving Detail'],
    'recv_items'        => ['th' => 'รายการสินค้าที่รับ',   'en' => 'Received Items'],
    'recv_no'           => ['th' => 'เลขที่รับ',           'en' => 'Receiving No.'],
    'recv_date'         => ['th' => 'วันที่รับ',           'en' => 'Receiving Date'],
    'recv_type'         => ['th' => 'ประเภทการรับ',        'en' => 'Receiving Type'],
    'recv_type_inv'     => ['th' => 'เข้าสต็อก',           'en' => 'Inventory'],
    'recv_type_direct'  => ['th' => 'ซื้อตรง',             'en' => 'Direct'],
    'recv_ext_cost'     => ['th' => 'ค่าใช้จ่ายเพิ่ม',      'en' => 'Extra Cost'],
    'back_to_recv'      => ['th' => 'กลับรายการรับสินค้า',  'en' => 'Back to Receiving List'],
    'col_recv_no'       => ['th' => 'เลขที่รับ',           'en' => 'Recv No.'],
    'col_po_ref'        => ['th' => 'PO / Ref',           'en' => 'PO / Ref'],
    'col_cost_unit'     => ['th' => 'ทุน/หน่วย',           'en' => 'Cost/Unit'],
    'col_unit_rate'     => ['th' => 'อัตราหน่วย',          'en' => 'Unit Rate'],
    'col_amt_extax'     => ['th' => 'ก่อนภาษี',           'en' => 'Amt Ex.Tax'],
    'col_tax_amt'       => ['th' => 'ภาษี',               'en' => 'Tax Amt'],
    'col_amount_incl'   => ['th' => 'รวมสุทธิ',           'en' => 'Amount'],
    'col_dep_acc'       => ['th' => 'แผนก / บัญชี',        'en' => 'Dept / Acc'],
    'product_label'     => ['th' => 'รหัสสินค้า',          'en' => 'Product'],
    'ref_no_label'      => ['th' => 'เลขอ้างอิง',          'en' => 'Reference #'],
    'recv_total_all'    => ['th' => 'ยอดรวมทั้งหมด',       'en' => 'Grand Total'],
];

function t(string $key): string {
    $lang = $GLOBALS['LANG'] ?? 'th';
    $tr = $GLOBALS['TRANSLATIONS'][$key] ?? null;
    if (!$tr) return $key; // fallback: return key if missing
    return $tr[$lang] ?? $tr['th'] ?? $key;
}
