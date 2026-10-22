<?php
$fixture=require __DIR__.'/prototype-data.php';$c=$fixture['catalog'];
return [
    'localities'=>array_intersect_key($c,array_flip(['provinces','cities','barangays'])),
    'eligibility'=>['barangays'=>['TEST-B1'=>true,'TEST-B2'=>false]],
    'premises'=>['codes'=>$c['premises']+['studio'=>'Synthetic studio']],
    'occupancy'=>['codes'=>$c['occupancies']+['caretaker'=>'Synthetic caretaker']],
    'rules'=>['connections'=>$c['connections'],'connection_categories_by_premises'=>['shop'=>['ordinary','temporary'],'studio'=>['temporary']],'occupancy_other_codes'=>['other','caretaker']],
    'contract'=>['contract_version'=>NewConnectionApplicantContract::VERSION,'snapshot_schema'=>1,'workflow_version'=>1,'synthetic_only'=>true]
];
