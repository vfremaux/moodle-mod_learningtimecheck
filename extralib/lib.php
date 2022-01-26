<?php

function mod_learningtimecheck_eval($expression, $inputs) {

    extract($inputs);

    if (isset($op)) {
        $expression = str_replace('[op]', $op);
    }

    eval($expression);

    return $result;
}