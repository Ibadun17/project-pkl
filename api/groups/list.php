<?php
require_once __DIR__ . '/../_bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') fail('Method not allowed', 405);

$pdo = db();
$q = trim($_GET['q'] ?? '');

$params = [];
$sql = "
  SELECT g.id, g.nama_group, g.lokasi, g.area_zone, g.created_at,
  COUNT(l.id) AS total_lampu,
  MIN(l.device_id) AS device_id
  FROM device_groups g
  LEFT JOIN group_lamps l ON l.group_id = g.id
";

if ($q !== '') {
  $sql .= " WHERE (g.nama_group LIKE ? OR g.lokasi LIKE ? OR g.area_zone LIKE ? OR EXISTS (
              SELECT 1 FROM group_lamps l2
              WHERE l2.group_id = g.id AND (l2.device_id LIKE ? OR l2.lampu_nama LIKE ? OR l2.tuya_code LIKE ?)
            ))";
  $like = "%{$q}%";
  $params = [$like,$like,$like,$like,$like,$like];
}

$sql .= " GROUP BY g.id ORDER BY g.id DESC";

$st = $pdo->prepare($sql);
$st->execute($params);
$groups = $st->fetchAll(PDO::FETCH_ASSOC);

$ids = array_map(fn($g) => (int)$g['id'], $groups);
$lampsByGroup = [];

if ($ids) {
  $in = implode(',', array_fill(0, count($ids), '?'));
  $st = $pdo->prepare("
    SELECT id, group_id, device_id, lampu_nama, tuya_code, created_at
    FROM group_lamps
    WHERE group_id IN ($in)
    ORDER BY id ASC
  ");
  $st->execute($ids);
  $rows = $st->fetchAll(PDO::FETCH_ASSOC);

  foreach ($rows as $r) {
    $gid = (int)$r['group_id'];
    if (!isset($lampsByGroup[$gid])) $lampsByGroup[$gid] = [];
    $lampsByGroup[$gid][] = $r;
  }
}

foreach ($groups as &$g) {
  $gid = (int)$g['id'];
  $g['lamps'] = $lampsByGroup[$gid] ?? [];
}

ok($groups, 'OK');
