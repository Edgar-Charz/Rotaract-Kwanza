<?php
function e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function avatar_palette(int $i): array
{
    $p = [
        ['bg' => 'linear-gradient(135deg,var(--pink-100),var(--pink-200))', 'circle' => 'linear-gradient(135deg,var(--pink-600),var(--pink-800))'],
        ['bg' => 'linear-gradient(135deg,#FFF0E6,#FFD4AA)', 'circle' => 'linear-gradient(135deg,var(--gold),#C26B0A)'],
        ['bg' => 'linear-gradient(135deg,#EDF7F0,#C8EDD5)', 'circle' => 'linear-gradient(135deg,#27AE60,#1A6B3B)'],
        ['bg' => 'linear-gradient(135deg,#EEF4FF,#CCDAFF)', 'circle' => 'linear-gradient(135deg,#4A7FE8,#2252B8)'],
        ['bg' => 'linear-gradient(135deg,#FFF5E6,#FFDDAA)', 'circle' => 'linear-gradient(135deg,#E07B20,#A45310)'],
        ['bg' => 'linear-gradient(135deg,#F0EDFF,#D5CCFF)', 'circle' => 'linear-gradient(135deg,#7B5EA7,#4A2E88)'],
        ['bg' => 'linear-gradient(135deg,var(--pink-50),var(--pink-100))', 'circle' => 'linear-gradient(135deg,var(--pink-500),var(--pink-700))'],
    ];
    return $p[$i % count($p)];
}
