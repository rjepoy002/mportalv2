<?php
require_once __DIR__.'/applicant-contract.php';
require_once __DIR__.'/applicant-contract-v2.php';
function newConnectionValidateContract(string $version,string $json,array $context,bool $complete=false):array {
    return match($version){NewConnectionApplicantContract::VERSION=>NewConnectionApplicantContract::validate($json,$context,$complete),NewConnectionApplicantContractV2::VERSION=>NewConnectionApplicantContractV2::validate($json,$context,$complete),default=>throw new InvalidArgumentException('Unsupported contract version')};
}
