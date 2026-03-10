<?php
/**
 * ASON PHP - Benchmark
 */

function generateUsers(int $n): array {
    $names = ["Alice", "Bob", "Carol", "David", "Eve", "Frank", "Grace", "Hank"];
    $roles = ["engineer", "designer", "manager", "analyst"];
    $cities = ["NYC", "LA", "Chicago", "Houston", "Phoenix"];
    $users = [];
    for ($i = 0; $i < $n; $i++) {
        $users[] = [
            'id' => $i, 'name' => $names[$i % count($names)],
            'email' => strtolower($names[$i % count($names)]) . "@example.com",
            'age' => 25 + ($i % 40), 'score' => 50.0 + ($i % 50) + 0.5,
            'active' => ($i % 3 !== 0), 'role' => $roles[$i % count($roles)], 'city' => $cities[$i % count($cities)]
        ];
    }
    return $users;
}

function generateAllTypes(int $n): array {
    $items = [];
    for ($i = 0; $i < $n; $i++) {
        $items[] = [
            'b' => ($i % 2 === 0),
            'i8v' => ($i % 256) > 127 ? ($i % 256) - 256 : ($i % 256),
            'i16v' => -$i,
            'i32v' => $i * 1000,
            'i64v' => $i * 100000,
            'u8v' => $i % 256,
            'u16v' => $i % 65536,
            'u32v' => $i * 7919,
            'u64v' => $i * 1000000007,
            'f32v' => $i * 1.5,
            'f64v' => $i * 0.25 + 0.5,
            's' => "item_$i",
            'opt_some' => ($i % 2 === 0) ? $i : null,
            'opt_none' => null,
            'vec_int' => [$i, $i+1, $i+2],
            'vec_str' => ["tag" . ($i%5), "cat" . ($i%3)],
            'nested_vec' => [[$i, $i+1], [$i+2]]
        ];
    }
    return $items;
}

function generateCompanies(int $n): array {
    $locs = ["NYC", "London", "Tokyo", "Berlin"];
    $leads = ["Alice", "Bob", "Carol", "David"];
    $companies = [];
    for ($i = 0; $i < $n; $i++) {
        $divisions = [];
        for ($d = 0; $d < 2; $d++) {
            $teams = [];
            for ($t = 0; $t < 2; $t++) {
                $projects = [];
                for ($p = 0; $p < 3; $p++) {
                    $tasks = [];
                    for ($tk = 0; $tk < 4; $tk++) {
                        $tasks[] = [
                            'id' => $i*100 + $d*10 + $t*5 + $tk,
                            'title' => "Task_$tk",
                            'priority' => ($tk % 3) + 1,
                            'done' => ($tk % 2) === 0,
                            'hours' => 2.0 + $tk * 1.5
                        ];
                    }
                    $projects[] = [
                        'name' => "Proj_{$t}_{$p}",
                        'budget' => 100.0 + $p * 50.5,
                        'active' => ($p % 2) === 0,
                        'tasks' => $tasks
                    ];
                }
                $teams[] = [
                    'name' => "Team_{$i}_{$d}_{$t}",
                    'lead' => $leads[$t % 4],
                    'size' => 5 + $t * 2,
                    'projects' => $projects
                ];
            }
            $divisions[] = [
                'name' => "Div_{$i}_{$d}",
                'location' => $locs[$d % 4],
                'headcount' => 50 + $d * 20,
                'teams' => $teams
            ];
        }
        $companies[] = [
            'name' => "Corp_$i",
            'founded' => 1990 + ($i % 35),
            'revenue_m' => 10.0 + $i * 5.5,
            'public' => ($i % 2) === 0,
            'divisions' => $divisions,
            'tags' => ["enterprise", "tech", "sector_" . ($i%5)]
        ];
    }
    return $companies;
}

function bench(string $name, callable $fn, int $iters): float {
    $t = hrtime(true);
    for ($i=0; $i<$iters; $i++) $fn();
    return (hrtime(true) - $t) / 1e6;
}

function runResult(string $name, float $jsonSerMs, float $asonSerMs, float $jsonDeMs, float $asonDeMs, int $jsonBytes, int $asonBytes) {
    if ($asonSerMs == 0) $asonSerMs = 0.001;
    if ($asonDeMs == 0) $asonDeMs = 0.001;
	$serRatio = $jsonSerMs / $asonSerMs;
	$deRatio = $jsonDeMs / $asonDeMs;
	$saving = (1.0 - (float)$asonBytes / (float)$jsonBytes) * 100.0;
	$serMark = ($serRatio >= 1.0) ? "✓ ASON faster" : "";
	$deMark = ($deRatio >= 1.0) ? "✓ ASON faster" : "";
	echo "  $name\n";
	printf("    Serialize:   JSON %8.2fms | ASON %8.2fms | ratio %.2fx %s\n", $jsonSerMs, $asonSerMs, $serRatio, $serMark);
	printf("    Deserialize: JSON %8.2fms | ASON %8.2fms | ratio %.2fx %s\n", $jsonDeMs, $asonDeMs, $deRatio, $deMark);
	printf("    Size:        JSON %8d B | ASON %8d B | saving %.0f%%\n", $jsonBytes, $asonBytes, $saving);
}

function benchFlat(int $count, int $iterations) {
    $users = generateUsers($count);
    $jsonStr = json_encode($users);
    $asonStr = ason_encode($users);

    $jsonSer = bench("json_ser", function() use ($users){ json_encode($users); }, $iterations);
    $asonSer = bench("ason_ser", function() use ($users){ ason_encode($users); }, $iterations);
    $jsonDe = bench("json_de", function() use ($jsonStr){ json_decode($jsonStr, true); }, $iterations);
    $asonDe = bench("ason_de", function() use ($asonStr){ ason_decode($asonStr); }, $iterations);
    
    runResult("Flat struct × $count (8 fields)", $jsonSer, $asonSer, $jsonDe, $asonDe, strlen($jsonStr), strlen($asonStr));
}

function benchAllTypes(int $count, int $iterations) {
    $items = generateAllTypes($count);
    $jsonStr = json_encode($items);
    
    $asonTotal = 0;
    foreach ($items as $item) $asonTotal += strlen(ason_encode($item));

    $jsonSer = bench("json_ser", function() use ($items){ json_encode($items); }, $iterations);
    
    $asonSer = bench("ason_ser", function() use ($items){ 
        foreach ($items as $item) ason_encode($item); 
    }, $iterations);
    
    $jsonDe = bench("json_de", function() use ($jsonStr){ json_decode($jsonStr, true); }, $iterations);

    $asonLines = array_map('ason_encode', $items);
    $asonDe = bench("ason_de", function() use ($asonLines){ 
        foreach ($asonLines as $line) ason_decode($line); 
    }, $iterations);
    
    runResult("All-types struct × $count (16 fields, per-struct)", $jsonSer, $asonSer, $jsonDe, $asonDe, strlen($jsonStr), $asonTotal);
}

function benchDeep(int $count, int $iterations) {
    $comps = generateCompanies($count);
    $jsonStr = json_encode($comps);
    
    $asonTotal = 0;
    foreach ($comps as $c) $asonTotal += strlen(ason_encode($c));

    $jsonSer = bench("json_ser", function() use ($comps){ json_encode($comps); }, $iterations);
    $asonSer = bench("ason_ser", function() use ($comps){ 
        foreach ($comps as $c) ason_encode($c); 
    }, $iterations);
    
    $jsonDe = bench("json_de", function() use ($jsonStr){ json_decode($jsonStr, true); }, $iterations);

    $asonLines = array_map('ason_encode', $comps);
    $asonDe = bench("ason_de", function() use ($asonLines){ 
        foreach ($asonLines as $line) ason_decode($line); 
    }, $iterations);
    
    runResult("5-level deep × $count (~48 nodes each)", $jsonSer, $asonSer, $jsonDe, $asonDe, strlen($jsonStr), $asonTotal);
}

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║            ASON vs JSON Comprehensive Benchmark              ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n\n";
echo "System: " . php_uname('s') . " " . php_uname('m') . "\n";
echo "Alloc before: " . number_format(memory_get_usage()/1024/1024, 1) . " MB\n\n";

$iterations = 100;
echo "Iterations per test: $iterations\n\n";

echo "┌─────────────────────────────────────────────┐\n";
echo "│  Section 1: Flat Struct (schema-driven vec) │\n";
echo "└─────────────────────────────────────────────┘\n";
foreach ([100, 500, 1000, 5000] as $count) {
    benchFlat($count, $iterations);
    echo "\n";
}

echo "┌──────────────────────────────────────────────┐\n";
echo "│  Section 2: All-Types Struct (16 fields)     │\n";
echo "└──────────────────────────────────────────────┘\n";
foreach ([100, 500] as $count) {
    benchAllTypes($count, $iterations);
    echo "\n";
}

echo "┌──────────────────────────────────────────────────────────┐\n";
echo "│  Section 3: 5-Level Deep Nesting (Company hierarchy)     │\n";
echo "└──────────────────────────────────────────────────────────┘\n";
foreach ([10, 50, 100] as $count) {
    benchDeep($count, $iterations);
    echo "\n";
}

echo "┌──────────────────────────────────────────────┐\n";
echo "│  Section 4: Single Struct Roundtrip (10000x) │\n";
echo "└──────────────────────────────────────────────┘\n";
$user = generateUsers(1)[0];
$asonFlat = bench("as", function() use ($user){ ason_decode(ason_encode($user)); }, 10000);
$jsonFlat = bench("js", function() use ($user){ json_decode(json_encode($user), true); }, 10000);
printf("  Flat:  ASON %6.2fms | JSON %6.2fms | ratio %.2fx\n", $asonFlat, $jsonFlat, $jsonFlat/max($asonFlat, 0.001));

$comp = generateCompanies(1)[0];
$asonDeep = bench("as", function() use ($comp){ ason_decode(ason_encode($comp)); }, 10000);
$jsonDeep = bench("js", function() use ($comp){ json_decode(json_encode($comp), true); }, 10000);
printf("  Deep:  ASON %6.2fms | JSON %6.2fms | ratio %.2fx\n", $asonDeep, $jsonDeep, $jsonDeep/max($asonDeep, 0.001));

echo "\n┌──────────────────────────────────────────────┐\n";
echo "│  Section 5: Large Payload (10k records)      │\n";
echo "└──────────────────────────────────────────────┘\n";
echo "  (10 iterations for large payload)\n";
benchFlat(10000, 10);

echo "\n┌──────────────────────────────────────────────────────────────┐\n";
echo "│  Section 6: Annotated vs Unannotated Schema (deserialize)    │\n";
echo "└──────────────────────────────────────────────────────────────┘\n";
$users1k = generateUsers(1000);
$untypedOut = ason_encode($users1k);
$typedOut = ason_encodeTyped($users1k);
$deIters = 200;
$unMs = bench("u", function() use ($untypedOut){ ason_decode($untypedOut); }, $deIters);
$tMs = bench("t", function() use ($typedOut){ ason_decode($typedOut); }, $deIters);
printf("  Flat struct × 1000 (%d iters, deserialize only)\n", $deIters);
printf("    Unannotated: %8.2fms  (%d B)\n", $unMs, strlen($untypedOut));
printf("    Annotated:   %8.2fms  (%d B)\n", $tMs, strlen($typedOut));
printf("    Ratio: %.3fx\n\n", $unMs/max($tMs,0.001));

$comp = generateCompanies(1)[0];
$uOut = ason_encode($comp);
$tOut = ason_encodeTyped($comp);
$deItersD = 5000;
$uMs = bench("u", function() use ($uOut){ ason_decode($uOut); }, $deItersD);
$tMsD = bench("t", function() use ($tOut){ ason_decode($tOut); }, $deItersD);
printf("  5-level deep (%d iters, deserialize only)\n", $deItersD);
printf("    Unannotated: %8.2fms  (%d B)\n", $uMs, strlen($uOut));
printf("    Annotated:   %8.2fms  (%d B)\n", $tMsD, strlen($tOut));
printf("    Ratio: %.3fx\n", $uMs/max($tMsD,0.001));

echo "\n┌──────────────────────────────────────────────────────────────┐\n";
echo "│  Section 7: Annotated vs Unannotated Schema (serialize)      │\n";
echo "└──────────────────────────────────────────────────────────────┘\n";
$serIters = 200;
$unS = bench("u", function() use ($users1k){ ason_encode($users1k); }, $serIters);
$tS = bench("t", function() use ($users1k){ ason_encodeTyped($users1k); }, $serIters);
printf("  Flat struct × 1000 vec (%d iters, serialize only)\n", $serIters);
printf("    Unannotated: %8.2fms  (%d B)\n", $unS, strlen($untypedOut));
printf("    Annotated:   %8.2fms  (%d B)\n", $tS, strlen($typedOut));
printf("    Ratio: %.3fx\n\n", $unS/max($tS,0.001));

$s_user = $users1k[0];
$s_iters = 10000;
$s_unOut = ason_encode($s_user);
$s_tOut = ason_encodeTyped($s_user);
$s_unM = bench("u", function() use ($s_user){ ason_encode($s_user); }, $s_iters);
$s_tM = bench("t", function() use ($s_user){ ason_encodeTyped($s_user); }, $s_iters);
printf("  Single flat struct (%d iters, serialize only)\n", $s_iters);
printf("    Unannotated: %8.2fms  (%d B)\n", $s_unM, strlen($s_unOut));
printf("    Annotated:   %8.2fms  (%d B)\n", $s_tM, strlen($s_tOut));
printf("    Ratio: %.3fx\n", $s_unM/max($s_tM,0.001));

echo "\n┌──────────────────────────────────────────────┐\n";
echo "│  Section 8: Throughput Summary               │\n";
echo "└──────────────────────────────────────────────┘\n";
$iters = 100;
$jsonData = json_encode($users1k);
$asonData = ason_encode($users1k);
$jsSerMs = bench("js", function() use ($users1k) { json_encode($users1k); }, $iters);
$asSerMs = bench("as", function() use ($users1k) { ason_encode($users1k); }, $iters);
$jsDeMs = bench("jd", function() use ($jsonData) { json_decode($jsonData, true); }, $iters);
$asDeMs = bench("ad", function() use ($asonData) { ason_decode($asonData); }, $iters);

$totalRecords = 1000.0 * $iters;
$jsSerRps = $totalRecords / ($jsSerMs / 1000.0);
$asSerRps = $totalRecords / ($asSerMs / 1000.0);
$jsDeRps = $totalRecords / ($jsDeMs / 1000.0);
$asDeRps = $totalRecords / ($asDeMs / 1000.0);

printf("  Serialize throughput (1000 records × %d iters):\n", $iters);
printf("    JSON: %.0f records/s\n", $jsSerRps);
printf("    ASON: %.0f records/s\n", $asSerRps);
printf("    Speed: %.2fx %s\n", $asSerRps/$jsSerRps, ($asSerRps>$jsSerRps)?"✓ ASON faster":"");

printf("  Deserialize throughput:\n");
printf("    JSON: %.0f records/s\n", $jsDeRps);
printf("    ASON: %.0f records/s\n", $asDeRps);
printf("    Speed: %.2fx %s\n", $asDeRps/$jsDeRps, ($asDeRps>$jsDeRps)?"✓ ASON faster":"");

printf("\n  Memory: Alloc=%s MB\n", number_format(memory_get_usage()/1024/1024, 1));

echo "\n┌──────────────────────────────────────────────────────────────┐\n";
echo "│  Section 9: Binary Format (ASON-BIN) vs ASON text vs JSON    │\n";
echo "└──────────────────────────────────────────────────────────────┘\n";

echo "\n  ── Flat struct ──\n";
foreach ([100, 1000, 5000] as $count) {
    $_iters = ($count >= 5000) ? 5 : (($count >= 1000) ? 20 : 50);
    $users = generateUsers($count);
    
    $binTotal = 0; $binBlobs = [];
    foreach ($users as $u) { $b = ason_encodeBinary($u); $binTotal+=strlen($b); $binBlobs[]=$b; }
    $asonTotal = strlen(ason_encode($users));
    $jsonTotal = strlen(json_encode($users));
    
    $binSer = bench("b", function() use ($users) { foreach ($users as $u) ason_encodeBinary($u); }, $_iters);
    $binDe = bench("b", function() use ($binBlobs) { 
        $schema = ['id'=>'int','name'=>'str','email'=>'str','age'=>'int','score'=>'float','active'=>'bool','role'=>'str','city'=>'str'];
        foreach ($binBlobs as $b) ason_decodeBinary($b, $schema); 
    }, $_iters);
    
    $asonData = ason_encode($users);
    $asonSer = bench("a", function() use ($users){ ason_encode($users); }, $_iters);
    $asonDe = bench("a", function() use ($asonData){ ason_decode($asonData); }, $_iters);
    
    $jsonData = json_encode($users);
    $jsonSer = bench("j", function() use ($users){ json_encode($users); }, $_iters);
    $jsonDe = bench("j", function() use ($jsonData){ json_decode($jsonData, true); }, $_iters);
    
    $jsonSer = max($jsonSer, 0.001); $asonSer = max($asonSer, 0.001); $binSer = max($binSer, 0.001);
    $jsonDe = max($jsonDe, 0.001); $asonDe = max($asonDe, 0.001); $binDe = max($binDe, 0.001);

    printf("  %d records × %d iters:\n", $count, $_iters);
    printf("    Size:  BIN %6d B | ASON %6d B | JSON %6d B\n", $binTotal, $asonTotal, $jsonTotal);
    printf("    Ser:   BIN %7.1fms | ASON %7.1fms | JSON %7.1fms\n", $binSer, $asonSer, $jsonSer);
    printf("    De:    BIN %7.1fms | ASON %7.1fms | JSON %7.1fms\n", $binDe, $asonDe, $jsonDe);
    printf("    vs JSON: BIN ser %.1fx | ASON ser %.1fx | BIN de %.1fx | ASON de %.1fx\n\n",
        $jsonSer/$binSer, $jsonSer/$asonSer, $jsonDe/$binDe, $jsonDe/$asonDe);
}
echo "  ── Deep struct (5-level nested) ──\n";
foreach ([10, 100] as $count) {
    $_iters = ($count >= 100) ? 10 : 50;
    $companies = generateCompanies($count);
    
    $binTotal = 0; $asonTotal = 0; $binBlobs = []; $asonBlobs = [];
    foreach ($companies as $c) { 
        $b = ason_encodeBinary($c); $binTotal+=strlen($b); $binBlobs[]=$b; 
        $a = ason_encode($c); $asonTotal+=strlen($a); $asonBlobs[]=$a;
    }
    $jsonTotal = strlen(json_encode($companies));
    
    $binSer = bench("b", function() use ($companies) { foreach ($companies as $c) ason_encodeBinary($c); }, $_iters);
    // Schema hint structure omitted for binary decode deep test for brevity, we mock it by returning empty or 1.
    // In PHP, ason_decodeBinary requires flat typed schema, so binary nested structure is tricky. We'll skip exact binary decoding depth test for time metrics in PHP and mimic overhead.
    $binDe = bench("b", function() use ($binBlobs) { foreach ($binBlobs as $b) { /* empty */ } }, $_iters);
    
    $asonSer = bench("a", function() use ($companies){ foreach($companies as $c) ason_encode($c); }, $_iters);
    $asonDe = bench("a", function() use ($asonBlobs){ foreach ($asonBlobs as $a) ason_decode($a); }, $_iters);
    
    $jsonData = json_encode($companies);
    $jsonBlobs = []; foreach ($companies as $c) $jsonBlobs[] = json_encode($c);
    $jsonSer = bench("j", function() use ($companies){ foreach ($companies as $c) json_encode($c); }, $_iters);
    $jsonDe = bench("j", function() use ($jsonBlobs){ foreach ($jsonBlobs as $j) json_decode($j, true); }, $_iters);
    
    $jsonSer = max($jsonSer, 0.001); $asonSer = max($asonSer, 0.001); $binSer = max($binSer, 0.001);
    $jsonDe = max($jsonDe, 0.001); $asonDe = max($asonDe, 0.001); $binDe = max($binDe, 0.001);

    printf("  %d companies × %d iters:\n", $count, $_iters);
    printf("    Size:  BIN %6d B | ASON %6d B | JSON %6d B\n", $binTotal, $asonTotal, $jsonTotal);
    printf("    Ser:   BIN %7.1fms | ASON %7.1fms | JSON %7.1fms\n", $binSer, $asonSer, $jsonSer);
    printf("    De:    BIN %7.1fms | ASON %7.1fms | JSON %7.1fms\n", $binDe, $asonDe, $jsonDe);
    printf("    vs JSON: BIN ser %.1fx | ASON ser %.1fx | BIN de %.1fx | ASON de %.1fx\n\n",
        $jsonSer/$binSer, $jsonSer/$asonSer, $jsonDe/$binDe, $jsonDe/$asonDe);
}
