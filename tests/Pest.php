<?php

use LBHurtado\TruthQr\Core\ProfileRegistry;

function electionProfileRegistry(): ProfileRegistry
{
    $registry = new ProfileRegistry;
    $registry->loadYaml(dirname(__DIR__).'/profiles/election.yaml');

    return $registry;
}
