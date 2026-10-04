<?php

return [

    // Mapping alias nama wilayah pada sheet "detail tagihan" → nama wilayah dari
    // sheet "rekap wilayah". Di-sinkronkan ke tabel `wilayah_aliases` setiap import
    // sehingga pencocokan wilayah selalu berjalan via database (bukan hardcode
    // di controller/service parser). Format: 'ALIAS' => 'NAMA WILAYAH REKAP'.
    'aliases' => [
        'MAY SUNGKONO' => 'MY. SUNGKONO',
        'LET. SINGOSASTRO' => 'L. SINGOSASTRO',
        'NILAM' => 'NILAM PERMAI',
        'CHANDRA LAND' => 'CANDRA LAND',
        'SAHARA' => 'SAHARA REGENCY',
        'MANGUN' => 'MANGUN PERSADA',
        'GRAHA MENTARI' => 'GRAHA MENTARI MLAJAH',
        'ABD MUIN' => 'MUIN',
        'TEUKU UMAR' => 'T. UMAR',
        'GRAND ROSE' => 'PERUM GRAND ROSE',
        'JL ANGGREK' => 'ANGGREK',
        'KHAYANGAN' => 'KHAYANGAN REGENCY',
        'PESONA PESALAKAN' => 'PERUM PESONA PESALAKAN',
        'S KADIRUN' => 'A. KADIRUN',
        'PANIDI' => 'BY PANIDI',
        'KARTINI' => 'RA. KARTINI',
        'CENDANA' => 'CENDANA ASRI',
        'LAVENDER' => 'GRAHA CANDRA LAVENDER',
        'ABDULLAH' => 'L. ABDULLAH',
        'GRIYA UTAMA I' => 'GRIYA UTAMA',
        'LET. MESTU' => 'L. MESTU',
        'PEMUDA KAFFA' => 'P. KAFFA',
        'HALIM PERDANA KUSUSMA' => 'HALIM PERDANA KS',
        'HANDOKO' => 'HANDOKO PERMAI',
        'PONDOK SENEN' => 'P. SENEN',
        'PURI' => 'PURI GRAHA LAND',
    ],
];