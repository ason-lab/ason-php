<?php
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

$comp = generateCompanies(1)[0];
$js = json_encode($comp);
$as = ason_encode($comp);
echo "JSON length: " . strlen($js) . "\n";
echo "ASON length: " . strlen($as) . "\n";
// echo "JSON: " . $js . "\n\n";
echo "ASON: " . $as . "\n";
