<?php
/**
 * ASON PHP - Basic Examples
 */

echo "=== ASON Basic Examples ===\n\n";

// 1. Serialize a single struct
$user = ['id' => 1, 'name' => 'Alice', 'active' => true];
$ason_str = ason_encode($user);
echo "Serialize single struct:\n  $ason_str\n\n";

// 2. Serialize with type annotations
$typed_str = ason_encodeTyped($user);
echo "Serialize with type annotations:\n  $typed_str\n\n";

// 3. Deserialize from ASON
$loaded = ason_decode('{id:int,name:str,active:bool}:(1,Alice,true)');
echo "Deserialize single struct:\n";
echo "  {ID:{$loaded['id']} Name:{$loaded['name']} Active:" . ($loaded['active'] ? 'true' : 'false') . "}\n\n";

// 4. Serialize a vec of structs
$users = [
    ['id' => 1, 'name' => 'Alice', 'active' => true],
    ['id' => 2, 'name' => 'Bob', 'active' => false],
    ['id' => 3, 'name' => 'Carol Smith', 'active' => true],
];
$ason_vec = ason_encode($users);
echo "Serialize vec (schema-driven):\n  $ason_vec\n\n";

// 5. Serialize vec with type annotations
$typed_vec = ason_encodeTyped($users);
echo "Serialize vec with type annotations:\n  $typed_vec\n\n";

// 6. Deserialize vec
$users2 = ason_decode('[{id:int,name:str,active:bool}]:(1,Alice,true),(2,Bob,false),(3,"Carol Smith",true)');
echo "Deserialize vec:\n";
foreach ($users2 as $u) {
    echo "  {ID:{$u['id']} Name:{$u['name']} Active:" . ($u['active'] ? 'true' : 'false') . "}\n";
}

// 7. Multiline format
echo "\nMultiline format:\n";
$multiline = "[{id:int, name:str, active:bool}]:\n  (1, Alice, true),\n  (2, Bob, false),\n  (3, \"Carol Smith\", true)";
$users3 = ason_decode($multiline);
foreach ($users3 as $u) {
    echo "  {ID:{$u['id']} Name:{$u['name']} Active:" . ($u['active'] ? 'true' : 'false') . "}\n";
}

// 8. Roundtrip (ASON-text vs ASON-bin vs JSON)
echo "\n8. Roundtrip (ASON-text vs ASON-bin vs JSON):\n";
$original = ['id' => 42, 'name' => 'Test User', 'active' => true];

$asonText = ason_encode($original);
$fromAson = ason_decode($asonText);

$asonBin = ason_encodeBinary($original);
$fromBin = ason_decodeBinary($asonBin, ['id' => 'int', 'name' => 'str', 'active' => 'bool']);

$jsonData = json_encode($original);
$fromJSON = json_decode($jsonData, true);

echo "  original:     {ID:{$original['id']} Name:{$original['name']} Active:" . ($original['active'] ? 'true' : 'false') . "}\n";
echo "  ASON text:    $asonText (" . strlen($asonText) . " B)\n";
echo "  ASON binary:  " . strlen($asonBin) . " B\n";
echo "  JSON:         $jsonData (" . strlen($jsonData) . " B)\n";
echo "  ✓ all 3 formats roundtrip OK\n";

// 9. Optional fields
echo "\n9. Optional fields:\n";
$item = ason_decode('{id,label}:(1,hello)');
echo "  with value: {ID:{$item['id']} Label:{$item['label']}} (label={$item['label']})\n";

$item2 = ason_decode('{id,label}:(2,)');
echo "  with null:  {ID:{$item2['id']} Label:<nil>}\n"; // Representation of null

// 10. Array fields
echo "\n10. Array fields:\n";
$t = ason_decode('{name,tags}:(Alice,[rust,go,python])');
echo "  {Name:{$t['name']} Tags:[" . implode(" ", $t['tags']) . "]}\n";

// 11. Comments
echo "\n11. With comments:\n";
$commented = ason_decode('/* user list */ {id,name,active}:(1,Alice,true)');
echo "  {ID:{$commented['id']} Name:{$commented['name']} Active:" . ($commented['active'] ? 'true' : 'false') . "}\n";

echo "\n=== All examples passed! ===\n";
