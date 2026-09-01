<?php
/**
 * Builds the address lookup tables the employee profile form types against.
 *
 * Source is the PSGC API (psgc.gitlab.io), the published Philippine Standard
 * Geographic Code. Run it again whenever PSGC publishes a new release:
 *
 *     php scripts/build-psgc.php
 *
 * It writes public/psgc/, which is served as plain static files — no route, no
 * controller, no database. The full barangay set is 11 MB, far too much to hand
 * a browser, so it is split per province: choosing a province fetches one file
 * (~20 KB) that already holds every barangay in it, keyed by city.
 */

const API = 'https://psgc.gitlab.io/api';
const OUT = __DIR__ . '/../public/psgc';

/** Cities outside any province are administered by their region. PSGC leaves
 *  their provinceCode empty, so they need a bucket of their own or they vanish
 *  from a province-first form. Metro Manila is the one that matters daily. */
const REGION_AS_PROVINCE = [
    '130000000' => 'Metro Manila',
    '090000000' => 'Zamboanga Peninsula',
    '120000000' => 'SOCCSKSARGEN',
];

function fetchJson(string $name): array {
    $url = API . "/$name.json";
    fwrite(STDERR, "fetching $url\n");
    $raw = file_get_contents($url);
    if ($raw === false) { fwrite(STDERR, "FAILED: $url\n"); exit(1); }
    return json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
}

function put(string $path, $data): int {
    @mkdir(dirname($path), 0775, true);
    $json = json_encode($data, JSON_UNESCAPED_UNICODE);
    file_put_contents($path, $json);
    return strlen($json);
}

$provinces = fetchJson('provinces');
$cities    = fetchJson('cities-municipalities');
$barangays = fetchJson('barangays');

// ── Provinces ────────────────────────────────────────────────────────────────
$provList = [];
foreach ($provinces as $p) {
    $provList[] = ['c' => $p['code'], 'n' => $p['name']];
}
foreach (REGION_AS_PROVINCE as $code => $name) {
    $provList[] = ['c' => $code, 'n' => $name];
}
usort($provList, fn ($a, $b) => strcmp($a['n'], $b['n']));

// ── Cities / municipalities, grouped by province ─────────────────────────────
$byProvince = [];
foreach ($cities as $c) {
    // Empty provinceCode means the region is the parent; see REGION_AS_PROVINCE.
    $prov = $c['provinceCode'] ?: $c['regionCode'];
    $byProvince[$prov][] = ['c' => $c['code'], 'n' => $c['name']];
}

// ── Barangays, grouped by province then by city ──────────────────────────────
$brgyByProvince = [];
$orphans = 0;
foreach ($barangays as $b) {
    // Exactly one of these is set; sub-municipalities are Manila's districts,
    // whose barangays still belong to the city above them.
    $city = $b['cityCode'] ?: ($b['municipalityCode'] ?: $b['subMunicipalityCode']);
    $prov = $b['provinceCode'] ?: $b['regionCode'];
    if (! $city) { $orphans++; continue; }
    $brgyByProvince[$prov][$city][] = $b['name'];
}

// ── Write ────────────────────────────────────────────────────────────────────
$bytes = put(OUT . '/provinces.json', $provList);
printf("provinces.json        %5d entries  %7d bytes\n", count($provList), $bytes);

$cityBytes = 0; $cityFiles = 0;
foreach ($byProvince as $prov => $list) {
    usort($list, fn ($a, $b) => strcmp($a['n'], $b['n']));
    $cityBytes += put(OUT . "/cities/$prov.json", $list);
    $cityFiles++;
}
printf("cities/*.json         %5d files    %7d bytes\n", $cityFiles, $cityBytes);

$brgyBytes = 0; $brgyFiles = 0; $brgyCount = 0;
foreach ($brgyByProvince as $prov => $cityMap) {
    foreach ($cityMap as $city => $names) { sort($names); $cityMap[$city] = $names; $brgyCount += count($names); }
    $brgyBytes += put(OUT . "/barangays/$prov.json", $cityMap);
    $brgyFiles++;
}
printf("barangays/*.json      %5d files    %7d bytes  (%d barangays, %d orphaned)\n",
    $brgyFiles, $brgyBytes, $brgyCount, $orphans);
